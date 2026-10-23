<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ТД «АТМОС» online price (price.teplota.by): ATMOS, ZOTA, BURNiT, НОРД-ВЕСТ,
 * MORA-TOP, TERMICA, DRAZICE, TESY, HANSA, REGULUS and other brands. Every
 * «section + brand» page is a table with name, description, supplier
 * article, retail and wholesale BYN price and a «на складе» mark.
 *
 * Rows are linked to catalogue cards by an existing ATMOS link, the
 * manufacturer article (catalogue SKU or another supplier's article), the
 * exact name or the model key inside the same brand. Unlinked rows are only
 * reported (--audit); new cards are created separately.
 *
 *   php artisan supplier:sync-atmos --audit                       # dry run
 *   php artisan supplier:sync-atmos --apply --sync-retail-prices
 */
class SyncAtmosCommand extends Command
{
    protected $signature = 'supplier:sync-atmos
        {--apply : Save supplier links, wholesale prices and stock}
        {--sync-retail-prices : Copy the supplier retail price to cards that have no other operational supplier}
        {--audit : Print one public line per row: section, brand, row, product id/slug and match reason}';

    protected $description = 'ТД «АТМОС» (price.teplota.by): wholesale/retail prices and stock for linked catalogue cards';

    public const SUPPLIER_CODE = 'atmos';

    private const SUPPLIER_NAME = 'ТД «АТМОС»';

    private const BASE_URL = 'https://price.teplota.by';

    /** price.teplota.by section ids. */
    public const SECTIONS = [
        1 => 'Твердотопливные котлы', 2 => 'Газовые котлы', 3 => 'Электрические котлы', 4 => 'Инженерные решения',
        5 => 'Бойлеры', 6 => 'Солнечные коллекторы', 7 => 'Радиаторы', 8 => 'Насосы', 9 => 'Аккумулирующие ёмкости',
        10 => 'Тепловые насосы', 11 => 'Теплоноситель',
    ];

    /** Brand family => spellings in the price and in the catalogue. */
    public const BRAND_FAMILIES = [
        'zota' => ['zota', 'зота'],
        'atmos' => ['atmos', 'атмос'],
        'burnit' => ['burnit'],
        'nordwest' => ['норд-вест', 'норд вест', 'nord-west', 'nordwest'],
        'drazice' => ['drazice', 'дражице', 'družstevní'],
        'tesy' => ['tesy', 'тези'],
        'hansa' => ['hansa', 'ханса'],
        'regulus' => ['regulus', 'регулус'],
        'altstream' => ['altstream'],
        'termica' => ['termica', 'термика'],
        'moratop' => ['mora-top', 'mora top', 'moratop', 'мора-топ'],
        'ondo' => ['ondo'],
        'tebo' => ['tebo'],
        'radena' => ['radena', 'cn-ra'],
        'evolution' => ['evolution'],
        'thermos' => ['thermos'],
        'yasolar' => ['яsolar', 'ясолар', 'yasolar'],
    ];

    public function handle(): int
    {
        $lock = Cache::lock('supplier:sync-atmos', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация АТМОС уже выполняется.');

            return self::FAILURE;
        }

        try {
            $rows = $this->loadRows();
            if (count($rows) < 100) {
                throw new RuntimeException('Прайс АТМОС вернул слишком мало строк: '.count($rows));
            }
            [$rows, $counts] = $this->classify($rows, (bool) $this->option('audit'));
            foreach ($counts as $key => $value) {
                $this->line("metric.atmos.{$key}={$value}");
            }
            if ($this->option('audit')) {
                $this->audit($rows);
            }
            if (! $this->option('apply')) {
                return self::SUCCESS;
            }

            $stats = DB::transaction(fn (): array => $this->applyRows($rows));
            foreach ($stats as $key => $value) {
                $this->line("metric.atmos.{$key}={$value}");
            }

            return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function loadRows(): array
    {
        $home = $this->fetch(self::BASE_URL.'/');
        preg_match_all('~href\s*=\s*"/prices\?section=(\d+)&brand=(\d+)\s*"\s*>([^<]+)<~u', $home, $links, PREG_SET_ORDER);
        $rows = [];
        $seen = [];
        foreach ($links as [, $section, $brandId, $brand]) {
            if (isset($seen[$section.':'.$brandId])) {
                continue;
            }
            $seen[$section.':'.$brandId] = true;
            foreach ($this->parsePage($this->fetch(self::BASE_URL."/prices?section={$section}&brand={$brandId}"), (int) $section, trim(html_entity_decode($brand))) as $row) {
                $rows[] = $row;
            }
        }

        return $this->uniqueArticleKeys($rows);
    }

    /**
     * The price reuses one article for different models («D 21 P» and «DC 18 GD»
     * are both 1067). One link per article merged them and gave a card the
     * other model's price, so such rows get the article plus a name hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function uniqueArticleKeys(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $names[$row['article_key']][$this->nameKey($row['name'])] = true;
        }
        foreach ($rows as &$row) {
            if (count($names[$row['article_key']]) > 1) {
                $row['shared_article_key'] = $row['article_key'];
                $row['article_key'] .= 'N'.strtoupper(substr(sha1($this->nameKey($row['name'])), 0, 6));
            }
        }
        unset($row);

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function parsePage(string $html, int $section, string $brand): array
    {
        $rows = [];
        $number = 0;
        $parts = preg_split('~<h3 id\s*=\s*"[^"]*"\s*class\s*=\s*"text-center">(.*?)</h3>~su', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $subsection = $this->clean($parts[$i]);
            preg_match_all('~<tr>(.*?)</tr>~su', $parts[$i + 1] ?? '', $trs);
            foreach ($trs[1] as $tr) {
                if (! preg_match('~data-label\s*=\s*"Наименование"[^>]*>(.*?)</td>~su', $tr, $nameCell)) {
                    continue;
                }
                $link = preg_match('~<a href\s*=\s*"([^"]*)"[^>]*>(.*?)</a>~su', $nameCell[1], $a) ? $a : null;
                $cell = fn (string $label): string => preg_match('~data-label\s*=\s*"'.$label.'[^"]*"[^>]*>(.*?)</td>~su', $tr, $m) ? $this->clean($m[1]) : '';
                $rawName = $this->clean($link[2] ?? $nameCell[1]);
                $number++;
                $retail = $this->money($cell('Цена\('));
                $wholesale = $this->money($cell('Цена опт'));
                $article = $cell('Артикул');
                $rows[] = [
                    'section' => $section,
                    'section_name' => self::SECTIONS[$section] ?? (string) $section,
                    'brand' => $brand,
                    'family' => $this->family($brand.' '.$rawName),
                    'subsection' => $subsection,
                    'row' => $number,
                    'raw_name' => $rawName,
                    'name' => $this->cleanName($rawName),
                    'url' => isset($link[1]) && trim($link[1]) !== '' ? trim($link[1]) : null,
                    'image_id' => preg_match("~showImage\('(\d+)'\)~", $nameCell[1], $img) ? $img[1] : null,
                    'in_stock' => preg_match('~class\s*=\s*"is_store store"~', $nameCell[1]) === 1,
                    'description' => $cell('Описание'),
                    'article' => $article,
                    'article_key' => $this->key($article) !== '' ? $this->key($article) : 'ATMOS'.strtoupper(substr(sha1($brand.'|'.$this->cleanName($rawName)), 0, 14)),
                    'retail' => $retail,
                    'wholesale' => $wholesale,
                ];
            }
        }

        return $rows;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<string, int>} */
    public function classify(array $rows, bool $diagnostics = false): array
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article_normalized')
            : collect();
        $products = Product::query()->where('is_archived', false)->with('brand:id,name')->get(['id', 'sku', 'name', 'slug', 'brand_id']);
        $productFamily = [];
        $bySku = [];
        $byName = [];
        $byModel = [];
        $familyProducts = [];
        $modelTokens = [];
        $keyer = new SyncThermokraftCommand;
        foreach ($products as $product) {
            $family = $this->family(($product->brand?->name ?? '').' '.$product->name);
            $productFamily[(int) $product->id] = $family;
            if ($family !== '') {
                $familyProducts[$family][] = $product;
                $modelTokens[(int) $product->id] = array_filter(explode(' ', $keyer->modelKey($this->cleanName($product->name), (string) $product->brand?->name)));
            }
            if ($family !== '' && ($modelKey = $keyer->modelKey($this->cleanName($product->name), (string) $product->brand?->name)) !== '') {
                $byModel[$family][$modelKey][] = (int) $product->id;
            }
            if (($sku = $this->key((string) $product->sku)) !== '') {
                $bySku[$sku][] = (int) $product->id;
            }
            $byName[$this->nameKey(($product->brand?->name ?? '').' '.$product->name)][] = (int) $product->id;
            $byName[$this->nameKey($product->name)][] = (int) $product->id;
        }
        // Manufacturer articles known from other suppliers' price lists.
        $byArticle = [];
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article_normalized', 'product_id']) as $link) {
            if (isset($productFamily[$link->product_id]) && ($article = $this->key((string) $link->supplier_article_normalized)) !== '') {
                $byArticle[$article][] = (int) $link->product_id;
            }
        }
        $claimed = $links->flip();

        $counts = ['rows' => count($rows), 'matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'in_stock' => 0, 'no_price' => 0];
        foreach ($rows as &$row) {
            $counts[$row['in_stock'] ? 'in_stock' : 'not_in_stock'] = ($counts[$row['in_stock'] ? 'in_stock' : 'not_in_stock'] ?? 0) + 1;
            $productId = (int) ($links[$row['article_key']] ?? 0);
            $reason = $productId ? 'supplier_article' : null;
            $unique = fn (array $ids): ?int => count(array_unique($ids)) === 1 ? (int) $ids[0] : null;
            // Manufacturer articles only: short internal numbers (1001…) are not unique across brands.
            $articleKey = $this->key($row['article']);
            if (! $productId && strlen($articleKey) >= 5 && preg_match('/\d/', $articleKey) === 1) {
                if ($id = $unique($bySku[$articleKey] ?? [])) {
                    [$productId, $reason] = [$id, 'catalogue_sku'];
                } elseif (($id = $unique($byArticle[$articleKey] ?? [])) && $row['family'] !== '' && ($productFamily[$id] ?? '') === $row['family']) {
                    // Another supplier's article counts only inside the same brand.
                    [$productId, $reason] = [$id, 'supplier_article_other'];
                }
            }
            foreach ([$this->nameKey($row['brand'].' '.$row['name']), $this->nameKey($row['name'])] as $nameKey) {
                if (! $productId && $nameKey !== '' && ($id = $unique($byName[$nameKey] ?? []))) {
                    [$productId, $reason] = [$id, 'exact_name'];
                }
            }
            if (! $productId && $row['family'] !== '' && ($modelKey = $keyer->modelKey($row['name'], $row['brand'])) !== '') {
                $candidates = array_values(array_unique(array_filter(
                    $byModel[$row['family']][$modelKey] ?? [],
                    fn (int $id): bool => ! isset($claimed[$id]) || $claimed[$id] === $row['article_key'],
                )));
                if (count($candidates) === 1) {
                    [$productId, $reason] = [$candidates[0], 'model_key'];
                } elseif (count($candidates) > 1) {
                    $reason = 'ambiguous_model';
                    $counts['ambiguous']++;
                }
            }
            // The card may add descriptive Cyrillic words («бойлер», «аккумулирующий»), never another model code.
            if (! $productId && $reason !== 'ambiguous_model' && $row['family'] !== '') {
                $tokens = array_filter(explode(' ', $keyer->modelKey($row['name'], $row['brand'])));
                $candidates = [];
                foreach ($familyProducts[$row['family']] ?? [] as $product) {
                    $card = $modelTokens[(int) $product->id] ?? [];
                    $extra = array_diff($card, $tokens);
                    if ($tokens !== [] && array_diff($tokens, $card) === []
                        && array_filter($extra, fn (string $t): bool => preg_match('/\p{Cyrillic}/u', $t) !== 1 || preg_match('/\d/', $t) === 1) === []
                        && (! isset($claimed[$product->id]) || $claimed[$product->id] === $row['article_key'])) {
                        $candidates[] = (int) $product->id;
                    }
                }
                $candidates = array_values(array_unique($candidates));
                if (count($candidates) === 1) {
                    [$productId, $reason] = [$candidates[0], 'model_words'];
                }
            }
            if ($row['retail'] === null && $row['wholesale'] === null) {
                $counts['no_price']++;
            }
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason ?? 'none';
            if (! $productId && $diagnostics) {
                $row['near'] = $this->nearest($row, $familyProducts[$row['family']] ?? [], $keyer);
            }
            $counts[$productId ? 'matched' : 'unmatched']++;
        }
        unset($row);
        foreach (array_keys(self::BRAND_FAMILIES) as $family) {
            $counts['catalogue_'.$family] = count($familyProducts[$family] ?? []);
        }

        return [$rows, $counts];
    }

    /** @return array<string, int> */
    private function applyRows(array $rows): array
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => '+375 29 374-13-45 (A1, Viber), +375 17 323-69-47, tdatmos@mail.ru',
            'communication_channels' => [
                ['type' => 'phone', 'value' => '+375 29 374-13-45', 'label' => 'A1, Viber'],
                ['type' => 'phone', 'value' => '+375 17 323-69-47', 'label' => 'Тел/факс'],
                ['type' => 'email', 'value' => 'tdatmos@mail.ru'],
                ['type' => 'site', 'value' => 'https://www.atmos.by'],
            ],
            'notes' => 'Торговый дом «АТМОС», Минск, ул. Каменногорская, 47, офис 4. Онлайн-прайс: '.self::BASE_URL,
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'atmos_live_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'АТМОС: котлы, бойлеры, насосы, инженерные решения',
            'description' => 'Опт, рекомендованная розница и наличие из онлайн-прайса price.teplota.by.',
            'command' => 'supplier:sync-atmos',
            'source_url' => self::BASE_URL,
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        // Links of articles shared by several models merged two rows into one card.
        $shared = array_values(array_unique(array_filter(array_column($rows, 'shared_article_key'))));
        $removed = $shared ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereIn('supplier_article_normalized', $shared)->delete() : 0;
        $stats = ['shared_article_links_removed' => $removed, 'offers' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'price_holds_released' => 0, 'skipped' => 0, 'errors' => 0];
        $touched = [];
        foreach ($rows as $row) {
            if (! $row['product_id'] || ($row['wholesale'] === null && $row['retail'] === null)) {
                $stats['skipped']++;

                continue;
            }
            try {
                $product = Product::query()->find($row['product_id']);
                if (! $product) {
                    $stats['skipped']++;

                    continue;
                }
                SupplierProduct::query()->updateOrCreate([
                    'supplier_id' => $supplier->id,
                    'supplier_article_normalized' => $row['article_key'],
                ], [
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article' => $row['article'] !== '' ? $row['article'] : $row['article_key'],
                    'supplier_article_compact' => $row['article_key'],
                    'supplier_name' => trim($row['brand'].' '.$row['name']),
                    'source_url' => $row['url'] ?: self::BASE_URL."/prices?section={$row['section']}",
                    'price' => $row['wholesale'] ?? $row['retail'],
                    'price_byn' => $row['wholesale'] ?? $row['retail'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $row['in_stock'],
                    'stock_quantity' => null,
                    // Rows without «на складе» are ordered from the supplier: «под заказ», never hidden.
                    'stock_status' => $row['in_stock'] ? 'in_stock' : 'preorder',
                    'stock_text' => $row['in_stock'] ? 'На складе' : 'Под заказ',
                    'warehouse_name' => 'Склад АТМОС, Минск',
                    'delivery_days' => $row['in_stock'] ? 1 : null,
                    'last_stock_synced_at' => now(),
                    'match_status' => 'matched',
                    'match_confidence' => $row['match_reason'],
                    'raw' => $row,
                    'last_synced_at' => now(),
                ]);
                if ($this->option('sync-retail-prices') && $row['retail'] !== null) {
                    // One card, one price: another operational supplier keeps its retail.
                    $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                    if ($otherSupplier) {
                        $stats['retail_kept_other_supplier']++;
                    } else {
                        if (abs((float) $product->price - $row['retail']) > 0.005) {
                            $product->forceFill(['price' => $row['retail']])->save();
                            $stats['retail_updated']++;
                        }
                        if ($product->requires_price_confirmation) {
                            $product->forceFill(['requires_price_confirmation' => false])->save();
                            $stats['price_holds_released']++;
                        }
                    }
                }
                if ($product->brand_id) {
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                }
                $stats['offers']++;
                $touched[] = (int) $product->id;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($row['name'].': '.$e->getMessage());
            }
        }

        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();

        return $stats;
    }

    private function audit(array $rows): void
    {
        $slugs = Product::query()->whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            $this->line(sprintf('audit.atmos.row=%d:%s:%d:%d:%s:%s:%d', $row['section'], $row['family'] ?: 'other', $row['row'], $id, $id ? (string) ($slugs[$id] ?? '') : '', $row['match_reason'], (int) $row['in_stock']));
            if (! empty($row['near'])) {
                $this->line(sprintf('audit.atmos.near=%d:%s:%d:%d:%s:%d', $row['section'], $row['family'], $row['row'], ...$row['near']));
            }
        }
    }

    /**
     * Diagnostics: the closest card of the same brand — model tokens overlap,
     * every number of the price row must be present in the card.
     *
     * @return array{0: int, 1: string, 2: int}|null product id, slug, score %
     */
    private function nearest(array $row, array $products, SyncThermokraftCommand $keyer): ?array
    {
        $tokens = array_filter(explode(' ', $keyer->modelKey($row['name'], $row['brand'])));
        if ($tokens === []) {
            return null;
        }
        $numbers = array_filter($tokens, fn (string $t): bool => preg_match('/\d/', $t) === 1);
        $best = null;
        foreach ($products as $product) {
            $candidate = array_filter(explode(' ', $keyer->modelKey($this->cleanName($product->name), (string) $product->brand?->name)));
            if ($candidate === [] || array_diff($numbers, $candidate) !== []) {
                continue;
            }
            $score = (int) round(100 * count(array_intersect($tokens, $candidate)) / count(array_unique(array_merge($tokens, $candidate))));
            if (! $best || $score > $best[2]) {
                $best = [(int) $product->id, (string) $product->slug, $score];
            }
        }

        return $best;
    }

    public function cleanName(string $name): string
    {
        // «АТМОS» / «АТМОС» written with Cyrillic letters.
        $name = preg_replace('/(?<!\p{L})АТМО[SС](?!\p{L})/u', 'ATMOS', $name) ?? $name;
        $name = preg_replace('/[.!\s]*(скидка\s*-?\s*\d+\s*%|новинка|акция|цен[аы]\s+в\s+бел\.?\s*руб\.?|под заказ)[.!\s]*/iu', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name, " .!-\t");
    }

    public function family(string $text): string
    {
        $text = mb_strtolower($text);
        foreach (self::BRAND_FAMILIES as $family => $spellings) {
            foreach ($spellings as $spelling) {
                if (str_contains($text, $spelling)) {
                    return $family;
                }
            }
        }

        return '';
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(60)->retry(3, 2000, throw: false)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('price.teplota.by не ответил: '.$url);
        }

        return $response->body();
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function money(string $value): ?float
    {
        $value = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $value);

        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function key(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', mb_strtoupper($value)) ?? '';
    }

    private function nameKey(string $value): string
    {
        $value = mb_strtolower(str_replace('ё', 'е', $this->cleanName($value)));

        return trim(preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? '');
    }
}
