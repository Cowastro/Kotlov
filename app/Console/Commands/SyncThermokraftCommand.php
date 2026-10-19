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
        {--limit= : Process only the first N rows}';

    protected $description = 'Термокрафт: UNIVAT and TENKO/СТЭН prices, stock and missing products from the live Google Sheet.';

    private const SHEET_URL = 'https://docs.google.com/spreadsheets/d/17Gk3AhKK3PTQsVwa71NMlncIL4tE1RCWAtL-7qEJ_Q8/edit';

    private const SHEET_ID = '17Gk3AhKK3PTQsVwa71NMlncIL4tE1RCWAtL-7qEJ_Q8';

    private const ELECTRIC_GID = '2059074837';

    private const HEATERS_GID = '1999481119';

    private const AUTOMATION_GID = '494936167';

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
        foreach ([self::ELECTRIC_GID => 'boiler', self::HEATERS_GID => 'heater', self::AUTOMATION_GID => 'automation'] as $gid => $kind) {
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
        $seen = [];
        $rowNumber = 0;

        while (($cells = fgetcsv($handle, 0, ',')) !== false) {
            $rowNumber++;
            $cells = array_map(fn ($value) => $this->clean((string) $value), $cells);
            $name = $cells[0] ?? '';
            $brand = $this->brand($cells[8] ?? '', $name);
            if ($name === '' || ! in_array($brand, ['UNIVAT', 'TENKO', 'СТЭН', 'REGLER PRO'], true)) {
                continue;
            }
            if ($kind === 'boiler' && ! in_array($brand, ['UNIVAT', 'СТЭН'], true)) {
                continue;
            }
            if ($kind === 'heater' && ! in_array($brand, ['TENKO', 'СТЭН'], true)) {
                continue;
            }
            if ($kind === 'automation' && $brand !== 'REGLER PRO') {
                continue;
            }

            $wholesale = $this->money($cells[4] ?? '');
            $retail = $this->money($cells[5] ?? '');
            if ($wholesale === null && $retail === null) {
                continue;
            }
            $validPrice = ! ($wholesale !== null && $retail !== null && $retail + 0.005 < $wholesale);

            $stockText = $cells[6] ?? '';
            $article = $cells[1] ?? '';
            $barcode = preg_replace('/\D+/', '', $cells[2] ?? '') ?: '';
            $identity = $article !== '' ? $article : ($barcode !== '' ? $barcode : $brand.'-'.$this->shortHash($name));
            $dedupe = $this->key($identity);
            if ($dedupe === '' || isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

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
        foreach ($products as $product) {
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
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason;
            $counts[$productId ? 'matched' : 'create_candidates']++;
            $counts[$row['in_stock'] ? 'in_stock' : 'zero_stock']++;
            $brandMetric = match ($row['brand']) {
                'UNIVAT' => 'univat',
                'TENKO' => 'tenko',
                'СТЭН' => 'sten',
                'REGLER PRO' => 'regler_pro',
            };
            $counts[$brandMetric]++;
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
                if ($this->option('sync-retail-prices') && $row['retail'] !== null && abs((float) $product->price - $row['retail']) > 0.005) {
                    $product->forceFill(['price' => $row['retail']])->save();
                    $stats['retail_updated']++;
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
            default => trim($raw),
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
