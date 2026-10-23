<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncThermokraftCommand extends Command
{
    protected $signature = 'supplier:sync-thermokraft
        {--apply : Save supplier, links, prices and stock}
        {--dry-run : Preview without writes}
        {--create-new : Create missing catalogue cards}
        {--sync-retail-prices : Copy the source RRP to product.price}
        {--sheet-url= : Override the Google Sheet URL}
        {--limit= : Process only the first N rows}
        {--all-sheets : Read every price tab and brand, not only electric boilers, heaters and ReglerPro}
        {--audit : Print one public line per row: tab, sheet row, matched product id/slug and reason}';

    protected $description = 'Термокрафт: UNIVAT and TENKO/СТЭН prices, stock and missing products from the live Google Sheet.';

    private const SHEET_URL = 'https://docs.google.com/spreadsheets/d/17Gk3AhKK3PTQsVwa71NMlncIL4tE1RCWAtL-7qEJ_Q8/edit';

    private const SHEET_ID = '17Gk3AhKK3PTQsVwa71NMlncIL4tE1RCWAtL-7qEJ_Q8';

    private const ELECTRIC_GID = '2059074837';

    private const HEATERS_GID = '1999481119';

    private const AUTOMATION_GID = '494936167';

    /** Every price tab of the live sheet (gid => kind) used with --all-sheets. */
    private const ALL_TABS = [
        '2059074837' => 'boiler',
        '1879571452' => 'solid_fuel',
        '515875811' => 'tank',
        '1999481119' => 'heater',
        '494936167' => 'automation',
        '1001839439' => 'pump',
        '422325648' => 'convector',
        '1002190126' => 'expansion_tank',
        '407379358' => 'general',
    ];

    /** Brands the business does not take from Thermokraft (S-TANK, Auraton come from other channels). */
    private const EXCLUDED_BRANDS = ['S-TANK', 'AURATON'];

    /**
     * Price rows whose catalogue card is named too differently for the automatic
     * match (sheet name => product slug). Cards created by
     * catalog:publish-thermokraft-price-cards are added from its data file.
     * FAKEL 20 is the old sheet name of FAKEL 21 (same RRP; the series has no 20 kW model).
     */
    private const CARD_ALIASES = [
        'Котел твердотопливный FAKEL 10 кВт' => 'kotel-otopitelnyiy-fakel-fakel-10-kvt',
        'Котел твердотопливный FAKEL 15 кВт' => 'kotel-otopitelnyiy-fakel-fakel-15-kvt',
        'Котел твердотопливный FAKEL 20 кВт' => 'kotel-otopitelnyiy-fakel-fakel-21-kvt',
        'Котел твердотопливный FAKEL 21 кВт' => 'kotel-otopitelnyiy-fakel-fakel-21-kvt',
        'Котел Greolit DEEP PLUS 20 кВт без автоматики' => 'greolit-kotel-greolit-deep-plus-20-kvt-bez-avtomatiki',
        'Котел Greolit DEEP PLUS 20 кВт с автоматикой' => 'greolit-kotel-greolit-deep-plus-20-kvt-s-avtomatikoi',
        'Котел Greolit DEEP PLUS 30 кВт без автоматики' => 'greolit-kotel-greolit-deep-plus-30-kvt-bez-avtomatiki',
        'Котел Greolit DEEP PLUS 30 кВт с автоматикой' => 'greolit-kotel-greolit-deep-plus-30-kvt-s-avtomatikoi',
        'Котел Greolit DEEP PLUS 40 кВт без автоматики' => 'greolit-kotel-greolit-deep-plus-40-kvt-bez-avtomatiki',
        'Котел Greolit DEEP PLUS 40 кВт с автоматикой' => 'greolit-kotel-greolit-deep-plus-40-kvt-s-avtomatikoi',
        // The PROFI section of the price is «с автоматикой».
        'Котел Greolit PROFI 50 кВт' => 'greolit-kotel-greolit-profi-50-kvt-s-avtomatikoi',
        'Котел Greolit PROFI 60 кВт' => 'greolit-kotel-greolit-profi-60-kvt-s-avtomatikoi',
        'Котел Greolit PROFI 70 кВт' => 'greolit-kotel-greolit-profi-70-kvt-s-avtomatikoi',
        // The price names the importer (SKIPFIRE LIMITED), the catalogue the brand AV Engineering.
        'AVE-Titan-RS25-40-130, A&P' => 'nasos-tsirkulyatsionnyiy-av-engineering-ave-titan-rs25-40-130',
        'AVE-Titan-RS25-40-180, A&P' => 'nasos-tsirkulyatsionnyiy-av-engineering-ave-titan-rs25-40-180',
        'AVE-Titan-RS25-60-130, A&P' => 'nasos-tsirkulyatsionnyiy-av-engineering-ave-titan-rs25-60-130',
        'AVE-Titan-RS25-60-180, A&P' => 'nasos-tsirkulyatsionnyiy-av-engineering-ave-titan-rs25-60-180',
        'Переходник КВ-ПГ 1 (Каракан)' => 'sten-perexodnik-kv-pg-1-dlia-prisoedineniia-kotlov-s-kruglymi-vertikalnymi-dymoxodami-k-kirpicnym-dymovym-trubam',
    ];

    /** @var array<string,bool> */
    private array $seenArticles = [];

    private const REGLER_SOURCE_URL = 'https://regler.pro/reglerproelite';

    private const SUPPLIER_CODE = 'thermokraft';

    private const SUPPLIER_NAME = 'ООО «ТЕРМОКРАФТ»';

    public function handle(): int
    {
        $lock = Cache::lock('supplier:sync-thermokraft', 900);
        if (! $lock->get()) {
            $this->error('Синхронизация Термокрафта уже выполняется.');

            return self::FAILURE;
        }

        try {
            $rows = $this->loadRows();
            if (($limit = (int) $this->option('limit')) > 0) {
                $rows = array_slice($rows, 0, $limit);
            }

            [$classified, $counts] = $this->classify($rows);
            $this->metrics($counts);
            if ($this->option('audit')) {
                $this->audit($classified);
            }

            if (! $this->option('apply') || $this->option('dry-run')) {
                return self::SUCCESS;
            }

            $stats = DB::transaction(fn (): array => $this->applyRows($classified));
            foreach ($stats as $key => $value) {
                $this->line("metric.thermokraft.{$key}={$value}");
            }

            return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function loadRows(): array
    {
        $sheetId = self::SHEET_ID;
        $url = trim((string) $this->option('sheet-url'));
        if ($url !== '') {
            if (! preg_match('#/spreadsheets/d/([A-Za-z0-9_-]+)#', $url, $match)) {
                throw new RuntimeException('Некорректная ссылка Google Sheets.');
            }
            $sheetId = $match[1];
        }

        $rows = [];
        $tabs = $this->option('all-sheets')
            ? self::ALL_TABS
            : [self::ELECTRIC_GID => 'boiler', self::HEATERS_GID => 'heater', self::AUTOMATION_GID => 'automation'];
        foreach ($tabs as $gid => $kind) {
            $csv = $this->fetch("https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}");
            foreach ($this->parseCsv($csv, $gid, $kind) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function parseCsv(string $csv, string $gid, string $kind): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $rows = [];
        $rowNumber = 0;
        // parseCsv is also called without console input (unit tests).
        $allSheets = $this->input?->hasOption('all-sheets') && (bool) $this->option('all-sheets');

        while (($cells = fgetcsv($handle, 0, ',')) !== false) {
            $rowNumber++;
            $cells = array_map(fn ($value) => $this->clean((string) $value), $cells);
            $name = $cells[0] ?? '';
            $brand = $this->brand($cells[8] ?? '', $name);
            if (in_array(mb_strtoupper($brand), self::EXCLUDED_BRANDS, true)) {
                continue;
            }
            if ($name === '' || $brand === '' || (! $allSheets && ! in_array($brand, ['UNIVAT', 'TENKO', 'СТЭН', 'REGLER PRO'], true))) {
                continue;
            }
            $allowed = match (true) {
                $allSheets => true,
                $kind === 'boiler' => in_array($brand, ['UNIVAT', 'СТЭН'], true),
                $kind === 'heater' => in_array($brand, ['TENKO', 'СТЭН'], true),
                $kind === 'automation' => $brand === 'REGLER PRO',
                default => true,
            };
            if (! $allowed) {
                continue;
            }

            // Greolit lists «без автоматики / с автоматикой» in one row as «X/Y» prices: one row per variant.
            $variants = [[$name, $cells[4] ?? '', $cells[5] ?? '']];
            $split = '#^\s*([\d\s.,]+?)\s*/\s*([\d\s.,]+?)\s*$#u';
            if (preg_match($split, $cells[4] ?? '', $w) === 1 && preg_match($split, $cells[5] ?? '', $r) === 1) {
                $variants = [[$name.' без автоматики', $w[1], $r[1]], [$name.' с автоматикой', $w[2], $r[2]]];
            }
            foreach ($variants as [$name, $wholesaleCell, $retailCell]) {
                $wholesale = $this->money($wholesaleCell);
                $retail = $this->money($retailCell);
                if ($wholesale === null && $retail === null) {
                    continue;
                }
                $validPrice = ! ($wholesale !== null && $retail !== null && $retail + 0.005 < $wholesale);

                $stockText = $cells[6] ?? '';
                $article = $cells[1] ?? '';
                $barcode = preg_replace('/\D+/', '', $cells[2] ?? '') ?: '';
                $identity = $article !== '' ? $article : ($barcode !== '' ? $barcode : $brand.'-'.$this->shortHash($name));
                $dedupe = $this->key($identity);
                // The «Общее» tab repeats rows of the other tabs, sometimes without an article.
                $nameDedupe = 'name:'.$brand.':'.$this->nameKey($name);
                if ($dedupe === '' || isset($this->seenArticles[$dedupe]) || isset($this->seenArticles[$nameDedupe])) {
                    continue;
                }
                $this->seenArticles[$dedupe] = true;
                $this->seenArticles[$nameDedupe] = true;

                $quantity = $this->stockQuantity($stockText);
                $rows[] = [
                    'gid' => $gid,
                    'sheet_row' => $rowNumber,
                    'kind' => $kind,
                    'name' => $name,
                    'brand' => $brand,
                    'article' => $identity,
                    'article_key' => $dedupe,
                    'barcode' => $barcode,
                    'wholesale' => $wholesale,
                    'retail' => $retail,
                    'valid_price' => $validPrice,
                    'stock_text' => $stockText,
                    'stock_quantity' => $quantity,
                    'in_stock' => $quantity !== null && $quantity > 0,
                    'warranty' => $cells[7] ?? '',
                    'country' => $cells[9] ?? '',
                    'description' => $this->clean(($cells[10] ?? '').' '.($cells[12] ?? '')),
                ];
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<string,int>} */
    private function classify(array $rows): array
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $links = $supplier
            ? $supplier->supplierProducts()->whereNotNull('product_id')->get()->keyBy('supplier_article_normalized')
            : collect();
        $products = Product::query()->where('is_archived', false)->with('brand:id,name')->get(['id', 'sku', 'name', 'slug', 'brand_id']);
        $bySku = [];
        $byName = [];
        $byModel = [];
        $linkedElsewhere = $links->pluck('supplier_article_normalized', 'product_id');
        $aliases = [];
        foreach ($this->cardAliases() as $sheetName => $slug) {
            $aliases[$this->nameKey($sheetName)] = $slug;
        }
        foreach ($products as $product) {
            $modelKey = $this->modelKey($product->name, (string) $product->brand?->name);
            if ($modelKey !== '') {
                $byModel[$this->brandKey(($product->brand?->name ?? '').' '.$product->name)][$modelKey][] = $product;
            }
            $bySku[$this->key((string) $product->sku)][] = $product;
            $byName[$this->nameKey(($product->brand?->name ?? '').' '.$product->name)][] = $product;
            $byName[$this->nameKey($product->name)][] = $product;
        }

        $counts = ['rows' => count($rows), 'matched' => 0, 'create_candidates' => 0, 'in_stock' => 0, 'zero_stock' => 0, 'univat' => 0, 'tenko' => 0, 'sten' => 0, 'regler_pro' => 0, 'invalid' => 0];
        foreach ($rows as &$row) {
            if (! $row['valid_price']) {
                $row['product_id'] = null;
                $row['match_reason'] = 'invalid_price';
                $counts['invalid']++;

                continue;
            }

            $productId = (int) ($links->get($row['article_key'])?->product_id ?? 0);
            $reason = $productId ? 'supplier_article' : null;
            if ($productId === 0 && ($aliasSlug = $aliases[$this->nameKey($row['name'])] ?? null)) {
                $aliased = $products->firstWhere('slug', $aliasSlug);
                if ($aliased) {
                    $productId = (int) $aliased->id;
                    $reason = 'card_alias';
                }
            }
            if ($row['brand'] === 'REGLER PRO' && $this->isStandardReglerController($row['name'])) {
                $canonical = $products->firstWhere('slug', 'kontroller-kotla-regler-wi-fi-nasos-co-1-ventiliator');
                if ($canonical) {
                    $productId = (int) $canonical->id;
                    $reason = 'verified_regler_alias';
                }
            }
            foreach (array_filter([$row['article_key'], $this->key($row['barcode'])]) as $key) {
                if ($productId === 0 && count($bySku[$key] ?? []) === 1) {
                    $productId = (int) $bySku[$key][0]->id;
                    $reason = 'catalogue_article';
                }
            }
            foreach ([$this->nameKey($row['brand'].' '.$row['name']), $this->nameKey($row['name'])] as $key) {
                $candidates = collect($byName[$key] ?? [])->unique('id')->values();
                if ($productId === 0 && $key !== '' && $candidates->count() === 1) {
                    $productId = (int) $candidates->first()->id;
                    $reason = 'exact_name';
                }
            }
            if ($productId === 0 && ($modelKey = $this->modelKey($row['name'], $row['brand'])) !== '') {
                // Same brand, same series/power/voltage/model code: link only a unique, unclaimed card.
                $candidates = collect($byModel[$this->brandKey($row['brand'].' '.$row['name'])][$modelKey] ?? [])
                    ->unique('id')
                    ->reject(fn (Product $product): bool => isset($linkedElsewhere[$product->id])
                        && $linkedElsewhere[$product->id] !== $row['article_key'])
                    ->values();
                if ($candidates->count() === 1) {
                    $productId = (int) $candidates->first()->id;
                    $reason = 'model_key';
                } elseif ($candidates->count() > 1) {
                    $reason = 'ambiguous_model';
                    $row['candidates'] = $candidates->pluck('id')->all();
                    $counts['ambiguous'] = ($counts['ambiguous'] ?? 0) + 1;
                }
            }
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason;
            $counts[$productId ? 'matched' : 'create_candidates']++;
            $counts[$row['in_stock'] ? 'in_stock' : 'zero_stock']++;
            $brandMetric = match ($row['brand']) {
                'UNIVAT' => 'univat',
                'TENKO' => 'tenko',
                'СТЭН' => 'sten',
                'REGLER PRO' => 'regler_pro',
                default => 'other_brands',
            };
            $counts[$brandMetric] = ($counts[$brandMetric] ?? 0) + 1;
        }
        unset($row);

        return [$rows, $counts];
    }

    /** @return array<string,int> */
    private function applyRows(array $rows): array
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => '+375 (29) 636-93-93; +375 (33) 336-93-93; info@tenko.by',
            'communication_channels' => ['phone' => ['+375296369393', '+375333369393'], 'email' => ['info@tenko.by']],
            'notes' => 'УНП 192511125. Склад: Колодищанский с/с, 79, строение 61. Источник: '.self::SHEET_URL,
            'is_active' => true,
        ]);
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'thermokraft_live_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Термокрафт: UNIVAT, TENKO/СТЭН и автоматика ReglerPro',
            'description' => 'Прямые опт, РРЦ и остатки из Google Sheets; карточки ReglerPro дополнены данными производителя.',
            'command' => 'supplier:sync-thermokraft',
            'source_url' => self::SHEET_URL,
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'linked' => 0, 'created' => 0, 'retail_updated' => 0, 'archived_duplicates' => 0, 'skipped' => 0, 'errors' => 0];
        $touched = [];
        $existingLinks = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->get()
            ->keyBy('supplier_article_normalized');

        foreach ($rows as $row) {
            try {
                if (! $row['valid_price']) {
                    $stats['skipped']++;

                    continue;
                }

                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                if (! $product && $this->option('create-new')) {
                    $product = $this->createProduct($row);
                    $stats['created']++;
                }
                if (! $product) {
                    $stats['skipped']++;

                    continue;
                }

                $existingLink = $existingLinks->get($row['article_key']);
                $previousProductId = (int) ($existingLink?->product_id ?? 0);
                $sheetSourceUrl = self::SHEET_URL.'#gid='.$row['gid'];
                $sourceUrl = $row['brand'] === 'REGLER PRO'
                    ? self::REGLER_SOURCE_URL
                    : (preg_match('#^https?://(?:www\.)?(?:tenko\.(?:ua|by)|sten\.ru)/#i', (string) $existingLink?->source_url)
                        ? $existingLink->source_url
                        : $sheetSourceUrl);

                if ($row['brand'] === 'REGLER PRO') {
                    $this->enrichReglerProduct($product, $row);
                }

                $link = SupplierProduct::query()->updateOrCreate([
                    'supplier_id' => $supplier->id,
                    'supplier_article_normalized' => $row['article_key'],
                ], [
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_article' => $row['article'],
                    'supplier_article_compact' => preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', $row['article_key']),
                    'supplier_name' => $row['name'],
                    'source_url' => $sourceUrl,
                    'price' => $row['wholesale'],
                    'price_byn' => $row['wholesale'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $row['in_stock'],
                    'stock_quantity' => $row['stock_quantity'],
                    'stock_status' => $row['in_stock'] ? 'in_stock' : 'out_of_stock',
                    'stock_text' => $row['stock_text'] ?: ($row['in_stock'] ? 'В наличии' : 'Нет в наличии'),
                    'warehouse_name' => 'Склад Термокрафт',
                    'delivery_days' => $row['in_stock'] ? 0 : null,
                    'last_stock_synced_at' => now(),
                    'match_status' => 'matched',
                    'match_confidence' => $row['match_reason'] ?: 'created_from_live_price',
                    'raw' => $row,
                    'last_synced_at' => now(),
                ]);
                $existingLinks->put($row['article_key'], $link);
                if ($previousProductId > 0 && $previousProductId !== (int) $product->id) {
                    $duplicate = Product::query()->find($previousProductId);
                    $stillLinked = SupplierProduct::query()->where('product_id', $previousProductId)->exists();
                    if ($duplicate && ! $stillLinked && str_starts_with((string) $duplicate->sku, 'THERMOKRAFT-')) {
                        $duplicate->forceFill(['is_active' => false, 'is_archived' => true])->save();
                        $stats['archived_duplicates']++;
                    }
                }
                if ($this->option('sync-retail-prices') && $row['retail'] !== null) {
                    if (abs((float) $product->price - $row['retail']) > 0.005) {
                        $product->forceFill(['price' => $row['retail']])->save();
                        $stats['retail_updated']++;
                    }
                    // The supplier RRP confirms the price: release a legacy «price on request» hold.
                    if ($product->requires_price_confirmation) {
                        $product->forceFill(['requires_price_confirmation' => false])->save();
                        $stats['price_holds_released'] = ($stats['price_holds_released'] ?? 0) + 1;
                    }
                }
                if ($product->brand_id) {
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                }
                $stats['offers']++;
                $stats['linked']++;
                $touched[] = $product->id;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($row['name'].': '.$e->getMessage());
            }
        }

        // Brands excluded from this supplier must not keep stale Thermokraft offers.
        $stats['excluded_removed'] = 0;
        foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->get() as $link) {
            $brand = mb_strtoupper($this->brand((string) data_get($link->raw, 'brand', ''), (string) $link->supplier_name));
            if (in_array($brand, self::EXCLUDED_BRANDS, true)) {
                $touched[] = (int) $link->product_id;
                $link->delete();
                $stats['excluded_removed']++;
            }
        }

        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();

        return $stats;
    }

    private function createProduct(array $row): Product
    {
        $brand = Brand::query()->firstOrCreate(['name' => $row['brand']], ['slug' => Str::slug($row['brand']), 'h1' => $row['brand'], 'is_active' => true]);
        $categorySlug = match ($row['kind']) {
            'boiler' => 'elektricheskie',
            'automation' => 'avtomatika-i-termoregulyatory',
            default => 'elektricheskie-teny-dlya-otopleniya',
        };
        $categoryId = (int) DB::table('categories')->where('slug', $categorySlug)->value('id');
        if ($categoryId <= 0) {
            $categoryId = (int) DB::table('categories')->where('slug', 'komplektuyushhie-dlya-otopleniya')->value('id');
        }
        if ($categoryId <= 0) {
            throw new RuntimeException('Не найдена категория для '.$row['name']);
        }
        $name = trim($row['name']);
        $description = $row['description'] ?: "{$name} от {$row['brand']}. Актуальные цена и наличие синхронизируются с прайсом поставщика.";

        return Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brand->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'h1' => $name,
            'sku' => $this->uniqueSku($row['article']),
            'price' => $row['retail'] ?? 0,
            'currency' => 'BYN',
            'content' => $description,
            'short_description' => Str::limit(strip_tags($description), 300),
            'images' => [],
            'specs' => array_values(array_filter([
                ['name' => 'Артикул производителя', 'value' => $row['article']],
                ['name' => 'Штрихкод', 'value' => $row['barcode']],
                ['name' => 'Страна', 'value' => $row['country']],
            ], fn ($item) => $item['value'] !== '')),
            'service_info' => [['name' => 'Гарантия', 'value' => $row['warranty']]],
            'unit' => 'шт',
            'warranty' => $row['warranty'] ?: null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $row['in_stock'],
            'stock_qty' => $row['stock_quantity'],
            'availability_status' => $row['in_stock'] ? 'in_stock' : 'out_of_stock',
            'is_new' => true,
            'meta_title' => $name.' — купить в Минске и Беларуси | KOTLOV',
            'meta_description' => Str::limit($name.'. Цена, наличие, характеристики и доставка по Беларуси.', 155, ''),
        ]);
    }

    /** @return array<string,string> sheet name => product slug */
    private function cardAliases(): array
    {
        $aliases = self::CARD_ALIASES;
        $file = resource_path(PublishThermokraftPriceCardsCommand::DATA_FILE);
        if (is_file($file)) {
            foreach (json_decode((string) file_get_contents($file), true) ?: [] as $item) {
                $aliases[$item['sheet_name']] = $item['slug'];
            }
        }

        return $aliases;
    }

    /** Public diagnostics only: tab gid, sheet row, product id and storefront slug. */
    private function audit(array $rows): void
    {
        $slugs = Product::query()->whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $this->line(sprintf(
                'audit.thermokraft.row=%s:%d:%d:%s:%s',
                $row['gid'],
                $row['sheet_row'],
                $productId,
                $productId ? (string) ($slugs[$productId] ?? '') : '',
                (string) ($row['match_reason'] ?? 'none'),
            ));
            // Duplicate cards behind an ambiguous row: what is needed to pick the one to keep.
            foreach (Product::query()->whereIn('id', $row['candidates'] ?? [])->withCount('supplierProducts')->get() as $candidate) {
                $this->line(sprintf(
                    'audit.thermokraft.candidate=%s:%d:%d:%s:%d:%d:%d:%d:%d',
                    $row['gid'],
                    $row['sheet_row'],
                    $candidate->id,
                    $candidate->slug,
                    (int) $candidate->is_active,
                    (int) round((float) $candidate->price),
                    count($candidate->images ?: []),
                    count($candidate->specs ?: []),
                    $candidate->supplier_products_count,
                ));
            }
        }
    }

    private function metrics(array $counts): void
    {
        foreach ($counts as $key => $value) {
            $this->line("metric.thermokraft.{$key}={$value}");
        }
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 1, 'header' => "User-Agent: Kotlov/1.0\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false || trim($content) === '' || str_starts_with(ltrim($content), '<')) {
            throw new RuntimeException('Google Sheet не отдал CSV для синхронизации.');
        }

        return $content;
    }

    private function brand(string $raw, string $name): string
    {
        $text = mb_strtoupper($raw.' '.$name);

        return match (true) {
            str_contains($text, 'UNIVAT') => 'UNIVAT',
            str_contains($text, 'TENKO') => 'TENKO',
            str_contains($text, 'СТЭН') || str_contains($text, 'STEN') => 'СТЭН',
            str_contains($text, 'REGLER') => 'REGLER PRO',
            str_contains($text, 'С-ТЭНК') || str_contains($text, 'S-TANK') => 'S-TANK',
            str_contains(mb_strtoupper($raw), 'ИПРО') => 'ИПРо',
            default => preg_replace('/^[\s"\'«»]+|[\s"\'«»]+$/u', '', $raw) ?? trim($raw),
        };
    }

    private function stockQuantity(string $value): ?int
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if (preg_match('/^>(\d+)$/', $value, $m)) {
            return (int) $m[1] + 1;
        }

        return preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    private function money(string $value): ?float
    {
        $value = preg_replace('/[^\d,.\-]/u', '', str_replace(["\xc2\xa0", ' '], '', $value)) ?? '';
        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }
        $value = str_contains($value, ',') && str_contains($value, '.') ? str_replace(',', '', $value) : str_replace(',', '.', $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(str_replace("\xc2\xa0", ' ', $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $value);
    }

    private function key(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', mb_strtoupper($value)) ?? '';
    }

    private function nameKey(string $value): string
    {
        $value = mb_strtolower(str_replace('ё', 'е', $value));
        $value = preg_replace('/\b(univat|tenko|стэн|sten|regler\s*pro)\b/u', ' ', $value) ?? $value;

        return trim(preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? '');
    }

    /** Brand family of a sheet row or a catalogue card. */
    private function brandKey(string $text): string
    {
        $text = mb_strtolower(str_replace('ё', 'е', $text));

        return match (true) {
            str_contains($text, 'univat') => 'univat',
            str_contains($text, 'tenko') || str_contains($text, 'тенко') => 'tenko',
            str_contains($text, 'стэн') || preg_match('/\bsten\b/u', $text) === 1 || str_contains($text, 'каракан') => 'sten',
            str_contains($text, 'с-тэнк') || str_contains($text, 's-tank') || str_contains($text, 's tank') => 's-tank',
            str_contains($text, 'regler') => 'regler',
            default => preg_replace('/[^a-zа-я0-9]+/u', '', explode(' ', trim($text))[0] ?? '') ?? '',
        };
    }

    /**
     * Series, power, voltage and model code without marketing words, so that
     * «Котел Tenko Економ 4,5_220» and «Электрический котел Tenko ЭКОНОМ (КЕ)
     * 4,5 кВт 220В» get the same key. Returns '' when no number is present.
     */
    public function modelKey(string $name, string $brand = ''): string
    {
        $text = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name));
        // Latin look-alikes inside Cyrillic words («Cтандарт»).
        $text = preg_replace_callback('/\S+/u', function (array $m): string {
            return preg_match('/\p{Cyrillic}/u', $m[0]) === 1
                ? strtr($m[0], ['a' => 'а', 'c' => 'с', 'e' => 'е', 'o' => 'о', 'p' => 'р', 'x' => 'х', 'k' => 'к', 'm' => 'м', 't' => 'т', 'h' => 'н', 'b' => 'в', 'y' => 'у'])
                : $m[0];
        }, $text) ?? $text;
        $text = str_replace(['економ', 'плюс'], ['эконом', 'plus'], $text);
        $text = preg_replace('/(\d),(\d)/u', '$1.$2', $text) ?? $text;
        $text = preg_replace('/(\d)(квт|в)(?!\p{L})/u', '$1 $2', $text) ?? $text;
        $text = preg_replace('/[«»"“”()\[\],;:+_\/\-–]+/u', ' ', $text) ?? $text;
        $stop = ['котел', 'электрический', 'электрокотел', 'твердотопливный', 'квт', 'в', 'с', 'со', 'и', 'для', 'насосом',
            'grundfos', 'расширительным', 'баком', 'ке', 'кем', 'нке', 'univat', 'tenko', 'стэн', 'sten', 'regler',
            'двухконтурный', 'терракот', 'с-тэнк', 's', 'tank', 'тэнк', 'бак', 'косвенного', 'косвенный', 'нагрева', 'буферная',
            'емкость', 'водонагреватель', 'литров', 'л', 'регулятор', 'тяги', 'термостат', 'комнатный'];
        // The catalogue repeats the brand in the name («Котел Теплоприбор КС-Т-12,5»), the price does not.
        $stop = array_merge($stop, preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(str_replace('ё', 'е', $brand))) ?: []);
        $tokens = [];
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $token) {
            if ($token === '' || in_array($token, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d+(?:\.\d+)?$/', $token) === 1) {
                $token = rtrim(rtrim($token, '0'), '.') === '' ? $token : (str_contains($token, '.') ? rtrim(rtrim($token, '0'), '.') : $token);
            }
            // One alphabet for look-alike letters: «ЕТ-1000» (Cyrillic) = «ET-1000» (Latin).
            $token = strtr($token, ['а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x']);
            $tokens[$token] = true;
        }
        if (! preg_grep('/\d/', array_keys($tokens))) {
            return '';
        }
        $keys = array_keys($tokens);
        sort($keys, SORT_STRING);

        return implode(' ', $keys);
    }

    private function shortHash(string $value): string
    {
        return mb_strtoupper(substr(sha1($this->nameKey($value)), 0, 14));
    }

    private function uniqueSku(string $article): string
    {
        $base = 'THERMOKRAFT-'.substr($this->key($article), 0, 36);
        $sku = $base;
        $i = 2;
        while (Product::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'thermokraft-product';
        $slug = $base;
        $i = 2;
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function enrichReglerProduct(Product $product, array $row): void
    {
        $isKit = str_contains(mb_strtolower($row['name']), 'комплект');
        $images = $isKit
            ? ['img/products/regler-pro/kit-controller-vsk-120.jpg', 'img/products/regler-pro/kit-fan-vsk-120.jpg']
            : ['img/products/regler-pro/controller-wifi.jpg', 'img/products/regler-pro/controller-wifi-close.jpg', 'img/products/regler-pro/mobile-control.png'];

        $content = $isKit
            ? '<p>Комплект автоматики ReglerPro Wi‑Fi предназначен для управления твердотопливным котлом с принудительной подачей воздуха. В набор входят контроллер и вентилятор VSK 120.</p><p>Контроллер поддерживает заданную температуру теплоносителя и помещения, плавно регулирует обороты вентилятора, управляет циркуляционным насосом и помогает защищать котёл от перегрева. Встроенный Wi‑Fi позволяет контролировать работу отопления со смартфона и получать уведомления о состоянии системы.</p><p>Комплект подходит для модернизации полуавтоматических твердотопливных котлов. Перед заказом рекомендуем сверить посадочные размеры вентилятора и электрическую схему котла.</p>'
            : '<p>ReglerPro Wi‑Fi — контроллер для полуавтоматических твердотопливных котлов. Он поддерживает заданную температуру теплоносителя и помещения, плавно управляет вентилятором наддува и включает циркуляционный насос.</p><p>Контроллер оснащён Wi‑Fi: со смартфона можно менять температуру, наблюдать за работой котла и получать уведомления об аварийных ситуациях. В комплект входит датчик климат-контроля.</p><p>Плавное снижение оборотов вентилятора при приближении к заданной температуре уменьшает температурные скачки и помогает полнее использовать топливо. Гарантия производителя — 12 месяцев.</p>';

        $specs = [
            ['name' => 'Назначение', 'value' => 'Управление твердотопливным котлом'],
            ['name' => 'Напряжение питания', 'value' => '230 В ±5%'],
            ['name' => 'Потребляемая мощность', 'value' => 'не более 5 Вт'],
            ['name' => 'Степень защиты', 'value' => 'IP40'],
            ['name' => 'Температура эксплуатации', 'value' => 'от −10 до +40 °C'],
            ['name' => 'Максимальная мощность выхода насоса ЦО', 'value' => '250 Вт'],
            ['name' => 'Максимальная мощность выхода вентилятора', 'value' => '220 Вт'],
            ['name' => 'Управление со смартфона', 'value' => 'Wi‑Fi'],
            ['name' => 'Страна бренда', 'value' => 'Россия'],
        ];
        if ($isKit) {
            array_unshift($specs, ['name' => 'Комплектация', 'value' => 'Контроллер ReglerPro Wi‑Fi, вентилятор VSK 120']);
        } else {
            array_unshift(
                $specs,
                ['name' => 'Габариты контроллера', 'value' => '175 × 100 × 75 мм'],
                ['name' => 'Масса контроллера', 'value' => 'не более 700 г'],
            );
        }

        $updates = [
            'short_description' => $isKit
                ? 'Комплект управления твердотопливным котлом: Wi‑Fi-контроллер ReglerPro и вентилятор VSK 120.'
                : 'Wi‑Fi-контроллер твердотопливного котла с управлением вентилятором, насосом ЦО и климат-контролем.',
            'specs' => $this->mergeSpecs((array) ($product->specs ?? []), $specs),
            'meta_title' => $row['name'].' — купить в Минске | KOTLOV',
            'meta_description' => $isKit
                ? 'Комплект автоматики ReglerPro Wi‑Fi с вентилятором VSK 120. Цена, наличие, характеристики и доставка по Беларуси.'
                : 'Контроллер ReglerPro Wi‑Fi для твердотопливного котла. Характеристики, цена, наличие и доставка по Беларуси.',
        ];

        $currentImages = is_array($product->images) ? array_values(array_filter($product->images)) : [];
        if ($currentImages === []) {
            $updates['images'] = $images;
        }
        $plainContent = trim(strip_tags((string) $product->content));
        if (mb_strlen($plainContent) < 200 || str_contains($plainContent, 'Актуальные цена и наличие')) {
            $updates['content'] = $content;
        }

        $product->forceFill($updates)->save();
    }

    private function isStandardReglerController(string $name): bool
    {
        $normalized = mb_strtolower($name);

        return str_contains($normalized, 'контроллер')
            && str_contains($normalized, 'regler')
            && ! str_contains($normalized, 'комплект')
            && ! str_contains($normalized, 'granda')
            && ! str_contains($normalized, 'mini');
    }

    /**
     * @param  array<int,array{name?:string,value?:string}>  $current
     * @param  array<int,array{name:string,value:string}>  $verified
     * @return array<int,array{name:string,value:string}>
     */
    private function mergeSpecs(array $current, array $verified): array
    {
        $result = [];
        foreach (array_merge($current, $verified) as $spec) {
            $name = trim((string) ($spec['name'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $result[mb_strtolower($name)] = ['name' => $name, 'value' => $value];
        }

        return array_values($result);
    }
}
