<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * ООО «Теплошоп» (biodom.by, brand BIOTEP) price list: an .xlsx uploaded to
 * the biodom.by media library as «Prajs-Teploshop-DD.MM.YYYY.xlsx». Sheets:
 * BIOTEP pellet boilers + Wester valves, Wester expansion tanks and pumps,
 * Kospel boilers and water heaters (retail EUR), De Dietrich (retail BYN),
 * BAXI, Vaillant, Protherm (wholesale + recommended retail).
 *
 * Rows are linked like supplier:sync-atmos: an existing link, the
 * manufacturer article (catalogue SKU or another supplier's article inside
 * the same brand), the exact name, the model key inside the same brand.
 * Retail-only rows keep the supplier cost empty (no invented wholesale).
 *
 *   php artisan supplier:sync-teploshop --audit                       # dry run
 *   php artisan supplier:sync-teploshop --apply
 *   php artisan supplier:sync-teploshop --apply --create-biotep       # + missing BIOTEP boiler cards
 */
class SyncTeploshopCommand extends Command
{
    protected $signature = 'supplier:sync-teploshop
        {--file= : Local .xlsx instead of the latest price on biodom.by}
        {--url= : Price .xlsx URL instead of the latest one}
        {--eur-rate= : EUR/BYN rate instead of the NBRB rate}
        {--apply : Save supplier links and prices}
        {--sync-retail-prices : Copy the supplier retail price to cards that have no other operational supplier}
        {--create-biotep : Create cards for BIOTEP boilers missing in the catalogue}
        {--audit : Print one public line per row: sheet, brand, row, product id/slug and match reason}';

    protected $description = 'ООО «Теплошоп» (BIOTEP, Wester, Kospel, De Dietrich, BAXI, Vaillant, Protherm): prices for linked catalogue cards';

    public const SUPPLIER_CODE = 'teploshop';

    private const SUPPLIER_NAME = 'ООО «Теплошоп»';

    private const MEDIA_SEARCH = 'https://biodom.by/wp-json/wp/v2/media?search=prajs-teploshop&per_page=50&_fields=source_url';

    /** Brand family => spellings in the price and in the catalogue. */
    public const BRAND_FAMILIES = [
        'biotep' => ['biotep', 'биотеп'],
        'wester' => ['wester', 'вестер'],
        'kospel' => ['kospel', 'коспел'],
        'dedietrich' => ['de dietrich', 'dedietrich', 'де дитрих'],
        'baxi' => ['baxi', 'бакси'],
        'vaillant' => ['vaillant', 'вайлант', 'вайллант'],
        'protherm' => ['protherm', 'протерм'],
    ];

    private ?float $eurRate = null;

    public function handle(): int
    {
        $lock = Cache::lock('supplier:sync-teploshop', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Теплошопа уже выполняется.');

            return self::FAILURE;
        }

        try {
            [$path, $source] = $this->resolvePrice();
            $rows = $this->parseWorkbook($path);
            $this->line('metric.teploshop.price_date='.(preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $source, $d) ? $d[3].$d[2].$d[1] : 0));
            if (count($rows) < 100) {
                throw new RuntimeException('Прайс Теплошопа вернул слишком мало строк: '.count($rows));
            }
            [$rows, $counts] = $this->classify($rows);
            foreach ($counts as $key => $value) {
                $this->line("metric.teploshop.{$key}={$value}");
            }
            if ($this->option('audit')) {
                $this->audit($rows);
            }
            if (! $this->option('apply')) {
                return self::SUCCESS;
            }

            $stats = DB::transaction(fn (): array => $this->applyRows($rows, $source));
            foreach ($stats as $key => $value) {
                $this->line("metric.teploshop.{$key}={$value}");
            }

            return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /** @return array{0: string, 1: string} local path, source url */
    private function resolvePrice(): array
    {
        if ($file = $this->option('file')) {
            return [(string) $file, basename((string) $file)];
        }
        $url = (string) ($this->option('url') ?: $this->latestPriceUrl());
        $response = Http::timeout(120)->retry(3, 2000, throw: false)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])->get($url);
        if (! $response->successful() || ! str_starts_with($response->body(), 'PK')) {
            throw new RuntimeException('Прайс Теплошопа не скачался: '.$url);
        }
        $path = storage_path('app/supplier-cache/teploshop.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $response->body());

        return [$path, $url];
    }

    /** The newest «Prajs-Teploshop-DD.MM.YYYY.xlsx» in the biodom.by media library. */
    private function latestPriceUrl(): string
    {
        $media = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::MEDIA_SEARCH)->json() ?? [];
        $best = null;
        foreach ((array) $media as $item) {
            $url = (string) ($item['source_url'] ?? '');
            if (preg_match('/Prajs-Teploshop-(\d{2})\.(\d{2})\.(\d{4})[^\/]*\.xlsx$/i', $url, $m)) {
                $date = $m[3].$m[2].$m[1];
                if (! $best || $date > $best[0]) {
                    $best = [$date, $url];
                }
            }
        }
        if (! $best) {
            throw new RuntimeException('На biodom.by не найден прайс Теплошопа.');
        }

        return $best[1];
    }

    /** @return array<int, array<string, mixed>> */
    public function parseWorkbook(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $rows = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            foreach ($this->parseSheet($sheet) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseSheet(Worksheet $sheet): array
    {
        $title = mb_strtolower(trim($sheet->getTitle()));
        $out = [];
        $wholesaleCurrency = 'BYN';
        $kospelSeries = '';
        foreach ($sheet->getRowIterator() as $sheetRow) {
            $r = $sheetRow->getRowIndex();
            $c = [];
            foreach ($sheetRow->getCellIterator() as $cell) {
                $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                $c[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn()) - 1] = is_string($value) ? trim($value) : $value;
            }
            $text = fn (int $i): string => trim(preg_replace('/\s+/u', ' ', (string) ($c[$i] ?? '')) ?? '');
            $row = null;
            switch ($title) {
                case 'biotep':
                    if (preg_match('/^BIOTEP[\s-]*\d+/i', $text(1))) {
                        $row = ['brand' => 'BIOTEP', 'article' => $text(1), 'name' => $text(1), 'description' => trim($text(2).' '.$text(5)),
                            'wholesale' => $this->money($c[8] ?? null), 'retail' => $this->money($c[9] ?? null)];
                    } elseif (($name = $text(2) ?: $text(3)) !== '' && stripos($name, 'wester') !== false && $this->money($c[9] ?? null)) {
                        $row = ['brand' => 'Wester', 'article' => '', 'name' => $name, 'wholesale' => null, 'retail' => $this->money($c[9] ?? null)];
                    }
                    break;
                case 'расшир.баки':
                    if (stripos($text(4), 'wester') !== false || str_starts_with(mb_strtolower($text(4)), 'комплект')) {
                        $row = ['brand' => 'Wester', 'article' => $text(3), 'name' => $text(4),
                            'wholesale' => $this->money($c[10] ?? null), 'retail' => $this->money($c[11] ?? null)];
                    }
                    break;
                case 'насосы':
                    if (stripos($text(4), 'wester') !== false) {
                        $row = ['brand' => 'Wester', 'article' => $text(3), 'name' => $text(4),
                            'wholesale' => $this->money($c[11] ?? null), 'retail' => $this->money($c[12] ?? null)];
                    }
                    break;
                case 'kospel котлы':
                case 'kospel гвс':
                    if ($text(0) !== '' && $text(1) === '') {
                        $kospelSeries = $text(0);
                    }
                    $eur = $this->money($c[5] ?? null);
                    if ($text(1) === '' || $text(1) === 'Модель' || ! $eur) {
                        break;
                    }
                    $accessory = $text(3) !== '' && ! str_starts_with($text(3), '—');
                    foreach ($accessory ? [$text(1)] : $this->expandVariants($text(1)) as $model) {
                        $out[] = $this->row($sheet, $r, ['brand' => 'Kospel', 'article' => $model,
                            'name' => $accessory ? 'Kospel '.$text(3).' '.$model : 'Kospel '.$model,
                            'wholesale' => null, 'retail' => $this->byn($eur, 'EUR'), 'retail_eur' => $eur,
                            'series' => $kospelSeries, 'description' => $accessory ? '' : $text(3)]);
                    }
                    break;
                case 'de dietrich':
                    if ($text(1) !== '' && $text(1) !== 'Наименование' && ($retail = $this->money($c[7] ?? null))) {
                        $row = ['brand' => 'De Dietrich', 'article' => preg_match('/\d{5,}/', $text(0)) ? $text(0) : '', 'name' => $text(1),
                            'wholesale' => null, 'retail' => $retail];
                    }
                    break;
                case 'baxi':
                    if (preg_match('/опт\s*(eur|byn)/iu', $text(3), $m)) {
                        $wholesaleCurrency = mb_strtoupper($m[1]);
                    }
                    if ($text(1) !== '' && $text(1) !== 'Наименование' && ($retail = $this->money($c[4] ?? null))) {
                        $wholesale = $this->money($c[3] ?? null);
                        $row = ['brand' => 'BAXI', 'article' => $text(0), 'name' => 'BAXI '.$text(1), 'description' => $text(2),
                            'wholesale' => $wholesale ? $this->byn($wholesale, $wholesaleCurrency) : null, 'retail' => $retail];
                    }
                    break;
                case 'vaillant':
                case 'protherm':
                    if (preg_match('/^\d{8,}$/', $text(0)) && ($retail = $this->money($c[4] ?? null))) {
                        $wholesale = $this->money($c[3] ?? null);
                        $row = ['brand' => $title === 'vaillant' ? 'Vaillant' : 'Protherm', 'article' => $text(0),
                            'name' => ($title === 'vaillant' ? 'Vaillant ' : 'Protherm ').$text(1), 'description' => $text(2),
                            // The sheet has zeros, #VALUE! and wholesale above retail: only a real discount is a cost.
                            'wholesale' => $wholesale && $wholesale < $retail ? $wholesale : null, 'retail' => $retail];
                    }
                    break;
            }
            if ($row) {
                $out[] = $this->row($sheet, $r, $row);
            }
        }

        return $out;
    }

    /** «EKCO.L3-04/06/08.PL» → EKCO.L3-04.PL, EKCO.L3-06.PL, EKCO.L3-08.PL (one price for every power). */
    public function expandVariants(string $model): array
    {
        if (! preg_match('/(\d+(?:\s*\/\s*\d+)+)/', $model, $m)) {
            return [$model];
        }

        return array_map(fn (string $n): string => str_replace($m[1], trim($n), $model), explode('/', $m[1]));
    }

    private function row(Worksheet $sheet, int $r, array $row): array
    {
        $row += ['description' => '', 'series' => ''];
        $row['name'] = trim(preg_replace('/\s+/u', ' ', $row['name']) ?? '');
        $row['sheet'] = $sheet->getTitle();
        $row['row'] = $r;
        $row['family'] = $this->family($row['brand']);
        $row['article_key'] = $this->key($row['article']) ?: 'R'.substr(md5($row['sheet'].'|'.$row['name']), 0, 12);

        return $row;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<string, int>} */
    public function classify(array $rows): array
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article_normalized')
            : collect();
        $products = Product::query()->where('is_archived', false)->with('brand:id,name')->get(['id', 'sku', 'name', 'slug', 'brand_id']);
        $keyer = new SyncThermokraftCommand;
        $productFamily = $bySku = $byName = $byModel = $familyProducts = $modelTokens = [];
        foreach ($products as $product) {
            $family = $this->family(($product->brand?->name ?? '').' '.$product->name);
            $productFamily[(int) $product->id] = $family;
            if ($family !== '') {
                $familyProducts[$family][] = $product;
                $key = $keyer->modelKey($this->normalize($product->name), (string) $product->brand?->name);
                $modelTokens[(int) $product->id] = array_filter(explode(' ', $key));
                if ($key !== '') {
                    $byModel[$family][$key][] = (int) $product->id;
                }
            }
            if (($sku = $this->key((string) $product->sku)) !== '') {
                $bySku[$sku][] = (int) $product->id;
            }
            $byName[$this->nameKey(($product->brand?->name ?? '').' '.$product->name)][] = (int) $product->id;
            $byName[$this->nameKey($product->name)][] = (int) $product->id;
        }
        $byArticle = [];
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article_normalized', 'product_id']) as $link) {
            if (isset($productFamily[$link->product_id]) && ($article = $this->key((string) $link->supplier_article_normalized)) !== '') {
                $byArticle[$article][] = (int) $link->product_id;
            }
        }
        $claimed = $links->flip();
        $unique = fn (array $ids): ?int => count(array_unique($ids)) === 1 ? (int) $ids[0] : null;

        $counts = ['rows' => count($rows), 'matched' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'with_wholesale' => 0, 'retail_only' => 0];
        foreach ($rows as &$row) {
            $counts[$row['wholesale'] ? 'with_wholesale' : 'retail_only']++;
            $productId = (int) ($links[$row['article_key']] ?? 0);
            $reason = $productId ? 'supplier_article' : null;
            $articleKey = $this->key($row['article']);
            if (! $productId && strlen($articleKey) >= 5 && preg_match('/\d/', $articleKey) === 1) {
                if (($id = $unique($bySku[$articleKey] ?? [])) && ($productFamily[$id] ?? '') === $row['family']) {
                    [$productId, $reason] = [$id, 'catalogue_sku'];
                } elseif (($id = $unique($byArticle[$articleKey] ?? [])) && ($productFamily[$id] ?? '') === $row['family']) {
                    [$productId, $reason] = [$id, 'supplier_article_other'];
                }
            }
            foreach ([$this->nameKey($row['name'])] as $nameKey) {
                if (! $productId && $nameKey !== '' && ($id = $unique($byName[$nameKey] ?? [])) && ($productFamily[$id] ?? '') === $row['family']) {
                    [$productId, $reason] = [$id, 'exact_name'];
                }
            }
            $key = $keyer->modelKey($this->normalize($row['name']), $row['brand']);
            if (! $productId && $key !== '') {
                $candidates = array_values(array_unique(array_filter($byModel[$row['family']][$key] ?? [],
                    fn (int $id): bool => ! isset($claimed[$id]) || $claimed[$id] === $row['article_key'])));
                if (count($candidates) === 1) {
                    [$productId, $reason] = [$candidates[0], 'model_key'];
                } elseif (count($candidates) > 1) {
                    $reason = 'ambiguous_model';
                    $counts['ambiguous']++;
                }
            }
            // The card may add descriptive Cyrillic words («котел», «газовый»), never another model code.
            if (! $productId && $reason !== 'ambiguous_model' && $key !== '') {
                $tokens = array_filter(explode(' ', $key));
                $candidates = [];
                foreach ($familyProducts[$row['family']] ?? [] as $product) {
                    $card = $modelTokens[(int) $product->id] ?? [];
                    $extra = array_diff($card, $tokens);
                    if ($card !== [] && array_diff($tokens, $card) === []
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
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason ?? 'none';
            $counts[$productId ? 'matched' : 'unmatched']++;
            $counts['matched_'.$row['family']] = ($counts['matched_'.$row['family']] ?? 0) + ($productId ? 1 : 0);
            $counts['rows_'.$row['family']] = ($counts['rows_'.$row['family']] ?? 0) + 1;
        }
        unset($row);
        foreach (array_keys(self::BRAND_FAMILIES) as $family) {
            $counts['catalogue_'.$family] = count($familyProducts[$family] ?? []);
        }

        return [$rows, $counts];
    }

    /** @return array<string, int> */
    private function applyRows(array $rows, string $source): array
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => 'Евгений, менеджер: +375 29 642-92-92',
            'communication_channels' => [
                ['type' => 'phone', 'value' => '+375 29 642-92-92', 'label' => 'Евгений, менеджер'],
                ['type' => 'phone', 'value' => '+375 29 136-54-75', 'label' => 'Евгений (из прайса)'],
                ['type' => 'phone', 'value' => '+375 29 333-72-31', 'label' => 'Анатолий (из прайса)'],
                ['type' => 'site', 'value' => 'https://biodom.by'],
            ],
            'notes' => 'ООО «ТЕПЛОШОП», УНП 193506998, г. Минск, ул. Даумана 13, каб. 16. Представитель BIOTEP в РБ. Прайс: '.$source,
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'teploshop_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Теплошоп: BIOTEP, Wester, Kospel, De Dietrich, BAXI, Vaillant, Protherm',
            'description' => 'Опт и рекомендованная розница из прайса Теплошопа на biodom.by.',
            'command' => 'supplier:sync-teploshop',
            'source_url' => $source,
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'created' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'skipped' => 0, 'errors' => 0];
        $touched = [];
        foreach ($rows as $row) {
            try {
                if (! $row['product_id'] && $row['family'] === 'biotep' && $this->option('create-biotep') && $row['retail']) {
                    $row['product_id'] = $this->createBiotepCard($row)->id;
                    $row['match_reason'] = 'created_from_price';
                    $stats['created']++;
                }
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                if (! $product || (! $row['wholesale'] && ! $row['retail'])) {
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
                    'supplier_name' => $row['name'],
                    'source_url' => $source,
                    // Retail-only rows (Kospel, De Dietrich, Wester valves): the cost is unknown, not the retail.
                    'price' => $row['wholesale'],
                    'price_byn' => $row['wholesale'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => 'Под заказ',
                    'warehouse_name' => 'Теплошоп, Минск',
                    'last_stock_synced_at' => now(),
                    'match_status' => 'matched',
                    'match_confidence' => $row['match_reason'],
                    'raw' => $row,
                    'last_synced_at' => now(),
                ]);
                if ($this->option('sync-retail-prices') && $row['retail']) {
                    $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                    if ($otherSupplier) {
                        $stats['retail_kept_other_supplier']++;
                    } elseif (abs((float) $product->price - $row['retail']) > 0.005) {
                        $product->forceFill(['price' => $row['retail'], 'requires_price_confirmation' => false])->save();
                        $stats['retail_updated']++;
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

    private function createBiotepCard(array $row): Product
    {
        $brand = Brand::query()->whereRaw('LOWER(name) = ?', ['biotep'])->first()
            ?? Brand::query()->create(['name' => 'BIOTEP', 'slug' => 'biotep', 'h1' => 'BIOTEP', 'country' => 'Словения', 'is_active' => true]);
        $category = Category::query()->where('slug', 'kotly-na-pelletah')->firstOrFail();
        preg_match('/(\d+)/', $row['name'], $m);
        $name = 'Пеллетный котел BIOTEP '.$m[1];
        $specs = [['key' => 'Тип топлива', 'value' => 'Пеллеты']];
        if (preg_match('/Мощность:\s*([\d\s,.-]+)\s*кВт/u', $row['description'], $p)) {
            $specs[] = ['key' => 'Мощность, кВт', 'value' => trim($p[1])];
        }
        if (preg_match('/дымоход\s*-\s*Ø?\s*(\d+)\s*мм/u', $row['description'], $p)) {
            $specs[] = ['key' => 'Диаметр дымохода, мм', 'value' => $p[1]];
        }
        $specs[] = ['key' => 'Страна бренда', 'value' => 'Словения'];
        $short = Str::limit($name.'. '.preg_replace('/\s+/u', ' ', $row['description']), 250, '…');
        $slug = $base = Str::slug($name);
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'h1' => $name,
            'slug' => $slug,
            'sku' => 'BIOTEP-'.$m[1],
            'price' => $row['retail'],
            'currency' => 'BYN',
            'unit' => 'шт',
            'short_description' => $short,
            'specs' => $specs,
            'service_info' => [
                ['key' => 'Гарантия', 'value' => 'Гарантия производителя'],
                ['key' => 'Поставка', 'value' => 'Под заказ'],
            ],
            'content_source_url' => 'https://biodom.by/',
            'meta_title' => Str::limit($name, 60, '').' — купить в %city%',
            'meta_description' => Str::limit($short.' Цена, наличие и доставка по Беларуси.', 175, ''),
            'is_active' => true,
            'is_archived' => false,
            'is_new' => true,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
        ]);
    }

    private function audit(array $rows): void
    {
        $slugs = Product::query()->whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            $this->line(sprintf('audit.teploshop.row=%s:%s:%d:%d:%s:%s', Str::slug($row['sheet']) ?: 'sheet', $row['family'] ?: 'other', $row['row'], $id,
                $id ? (string) ($slugs[$id] ?? '') : Str::slug($row['name']), $row['match_reason']));
        }
    }

    /** Model spelling shared by the price and the catalogue: «12КТО» = «12 KTO», «L2M-04» = «L2M-4». */
    public function normalize(string $name): string
    {
        $name = preg_replace('/\((?:дымоход в подарок|с гайками|заказная позиция)\)/iu', ' ', $name) ?? $name;
        $name = preg_replace('/(?<=[\p{L}\d])\.(?=\p{L})/u', ' ', $name) ?? $name;
        $name = preg_replace('/(?<=\d)(?=\p{L})|(?<=\p{L})(?=\d)/u', ' ', $name) ?? $name;

        return preg_replace('/(?<!\d)0+(?=\d)/', '', $name) ?? $name;
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

    private function money(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $value);
        }

        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function byn(float $amount, string $currency): float
    {
        if ($currency !== 'EUR') {
            return round($amount, 2);
        }
        if ($this->eurRate === null) {
            $option = (float) str_replace(',', '.', (string) $this->option('eur-rate'));
            $this->eurRate = $option > 0 ? $option : (float) Http::timeout(15)->get('https://api.nbrb.by/exrates/rates/EUR?parammode=2')->json('Cur_OfficialRate');
            if ($this->eurRate <= 0) {
                throw new RuntimeException('Не удалось получить курс EUR. Передайте --eur-rate=X.XXXX.');
            }
        }

        return round($amount * $this->eurRate, 2);
    }

    private function key(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', mb_strtoupper($value)) ?? '';
    }

    private function nameKey(string $value): string
    {
        return trim(preg_replace('/[^a-zа-я0-9]+/u', ' ', mb_strtolower(str_replace('ё', 'е', $this->normalize($value)))) ?? '');
    }
}
