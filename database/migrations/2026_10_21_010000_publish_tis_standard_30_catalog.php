<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DATA_FILE = 'data/catalog/tis-standard-30.json';

    private const SOURCE_URL = 'https://teplov.ru/catalog/dymokhodnye_sistemy/sistema_tis_standart_30/';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('suppliers')) {
            return;
        }

        $data = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true);
        if (! is_array($data['series'] ?? null)) {
            return;
        }

        $now = now();
        $supplierId = $this->supplierId($now);
        $brandId = $this->brandId($now);
        $categories = DB::table('categories')
            ->whereIn('slug', [
                'truby-mono', 'troyniki-mono', 'kolena-mono',
                'truby-sendvich', 'troyniki-sendvich', 'kolena-sendvich',
                'shibery-dymohod', 'kondensatootvody', 'krepleniya-dymohod',
                'zonty-deflektory', 'perehody-adaptery-dymohod', 'prochie-dymohod',
            ])
            ->pluck('id', 'slug');

        if (! $supplierId || ! $brandId || $categories->isEmpty()) {
            return;
        }

        $rate = $this->rubRate();
        foreach ($data['series'] as $seriesKey => $series) {
            foreach (($series['items'] ?? []) as $index => $item) {
                $this->upsertProduct(
                    (string) $seriesKey,
                    (string) ($series['title'] ?? ''),
                    (array) $item,
                    $index,
                    (int) $supplierId,
                    (int) $brandId,
                    $categories,
                    $rate,
                    $now,
                );
            }
        }

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Published catalogue cards are intentionally durable.
    }

    private function upsertProduct(
        string $seriesKey,
        string $seriesTitle,
        array $item,
        int $index,
        int $supplierId,
        int $brandId,
        $categories,
        float $rate,
        $now,
    ): void {
        $sourceId = trim((string) ($item['source_id'] ?? ''));
        $sourceUrl = trim((string) ($item['source_url'] ?? ''));
        $sourceName = trim((string) ($item['name'] ?? ''));
        $rowName = trim((string) ($item['row_name'] ?? $sourceName));
        $diameter = trim((string) ($item['diameter'] ?? ''));
        $priceRub = (float) ($item['price_rub'] ?? 0);

        if ($sourceId === '' || $sourceName === '' || $diameter === '' || $priceRub <= 0) {
            return;
        }

        $sku = 'TIS30-'.$sourceId;
        $execution = $seriesKey === 'thermo' ? 'Термо' : 'Моно';
        $seriesName = $execution === 'Термо'
            ? 'ТиС «Стандарт 30» Термо, изоляция 30 мм'
            : 'ТиС «Стандарт 30» Моно';
        $name = 'ТиС Стандарт 30 '.$sourceName;
        $categorySlug = $this->categorySlug($rowName, $execution);
        $categoryId = (int) ($categories[$categorySlug] ?? $categories['prochie-dymohod'] ?? 0);
        if (! $categoryId) {
            return;
        }

        $priceByn = round($priceRub * $rate, 2);
        $properties = collect((array) ($item['properties'] ?? []))
            ->mapWithKeys(function ($property): array {
                if (! is_array($property) || ! filled($property['NAME'] ?? null)) {
                    return [];
                }

                $value = $property['VALUE'] ?? '';
                if (is_array($value)) {
                    $value = implode(', ', array_map(
                        fn ($item): string => is_scalar($item) ? (string) $item : '',
                        $value,
                    ));
                }

                return [(string) $property['NAME'] => is_scalar($value) ? (string) $value : ''];
            })
            ->filter()
            ->all();
        $specs = array_filter(array_merge([
            'Серия' => 'ТиС «Стандарт 30»',
            'Исполнение' => $execution,
            'Тип элемента' => $rowName,
            'Диаметр' => $diameter,
            'Толщина изоляции' => $execution === 'Термо' ? '30 мм' : null,
            'Материал' => 'Нержавеющая сталь',
            'Страна производства' => 'Россия',
            'Срок поставки' => '5–7 рабочих дней',
        ], $properties), fn ($value): bool => $value !== null && $value !== '');
        $description = trim((string) ($item['description'] ?? ''));
        $image = trim((string) ($item['source_image'] ?? ''));
        $slug = 'tis-standart-30-'.Str::slug(Str::after($sourceUrl, '/catalog/detail/'));
        $slug = trim($slug, '-');

        $productId = DB::table('products')->where('content_source_url', $sourceUrl)->value('id');
        $productId = $productId ?: DB::table('supplier_products')
            ->where('source_url', $sourceUrl)
            ->whereNotNull('product_id')
            ->value('product_id');
        $productId = $productId ?: DB::table('products')->where('sku', $sku)->value('id');
        $sharedWithStandard50 = $productId && DB::table('supplier_products')
            ->where('product_id', $productId)
            ->where('supplier_article', 'like', 'TIS50-%')
            ->exists();

        $content = $this->content($name, $seriesName, $diameter, $description, $execution);
        $payload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $name,
            'h1' => $name,
            'price' => $priceByn,
            'price_old' => null,
            'currency' => 'BYN',
            'short_description' => $rowName.'. Диаметр '.$diameter.' мм, нержавеющая сталь AISI 304. Поставка 5–7 рабочих дней.',
            'content' => $content,
            'images' => json_encode($image !== '' ? [$image] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_source_url' => $sourceUrl,
            'unit' => 'шт',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'stock_qty' => null,
            'availability_status' => 'check',
            'requires_price_confirmation' => false,
            'manual_stock_management' => false,
            'is_featured' => false,
            'is_new' => true,
            'is_sale' => false,
            'sort_order' => ($seriesKey === 'thermo' ? 40000 : 30000) + ($index * 10),
            'meta_title' => $name.' купить в %city% — цена | KOTLOV',
            'meta_keywords' => 'ТиС Стандарт 30, '.$rowName.', дымоход '.$diameter.', AISI 304',
            'meta_description' => $rowName.' ТиС «Стандарт 30», диаметр '.$diameter.' мм. Цена в BYN, характеристики, фото и поставка по Беларуси за 5–7 рабочих дней.',
            'updated_at' => $now,
        ];

        if ($productId) {
            // Some official Mono elements are used in both Standard 30 and
            // Standard 50. Keep their existing canonical card and attach the
            // second catalogue channel instead of renaming or duplicating it.
            if ($sharedWithStandard50) {
                $payload = array_intersect_key($payload, array_flip([
                    'category_id', 'brand_id', 'price', 'currency', 'images',
                    'content_source_url', 'is_active', 'is_archived',
                    'requires_price_confirmation', 'updated_at',
                ]));
            }

            $slugOwner = DB::table('products')->where('slug', $slug)->value('id');
            if (! $sharedWithStandard50 && (! $slugOwner || (int) $slugOwner === (int) $productId)) {
                $payload['slug'] = $slug;
            }
            DB::table('products')->where('id', $productId)->update($payload);
        } else {
            $payload['sku'] = $sku;
            $payload['slug'] = $slug;
            $payload['created_at'] = $now;
            $productId = DB::table('products')->insertGetId($payload);
        }

        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $sku],
            [
                'product_id' => $productId,
                'product_sku' => DB::table('products')->where('id', $productId)->value('sku'),
                'supplier_article_normalized' => $sku,
                'supplier_name' => $sourceName,
                'source_url' => $sourceUrl,
                'source_wp_id' => $sourceId,
                'price' => $priceRub,
                'currency' => 'RUB',
                'currency_rate' => $rate,
                'price_byn' => $priceByn,
                'in_stock' => false,
                'stock_quantity' => null,
                'stock_status' => 'preorder',
                'stock_text' => 'Поставка под заказ: 5–7 рабочих дней',
                'warehouse_name' => 'СанБизнесГруп',
                'delivery_days' => 7,
                'match_status' => 'matched',
                'match_confidence' => 'official_source',
                'raw' => json_encode([
                    'catalogue' => 'ТиС Стандарт 30',
                    'series' => $seriesTitle,
                    'element' => $rowName,
                    'diameter' => $diameter,
                    'official_retail_rub' => $priceRub,
                    'rub_to_byn_rate' => $rate,
                    'price_kind' => 'recommended_retail_with_vat',
                    'captured_at' => '2026-10-20',
                    'source' => self::SOURCE_URL,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function supplierId($now): ?int
    {
        $supplier = DB::table('suppliers')
            ->where('code', 'sanbusinessgroup')
            ->orWhere('name', 'ООО «СанБизнесГруп»')
            ->first();

        if ($supplier) {
            DB::table('suppliers')->where('id', $supplier->id)->update(['is_active' => true, 'updated_at' => $now]);

            return (int) $supplier->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => 'sanbusinessgroup',
            'name' => 'ООО «СанБизнесГруп»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Основной поставщик каталога и импортёр продукции.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function brandId($now): int
    {
        $brandId = DB::table('brands')->where('slug', 'teplov-i-suhov')->value('id')
            ?: DB::table('brands')->where('name', 'Теплов и Сухов')->value('id');

        if ($brandId) {
            return (int) $brandId;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => 'Теплов и Сухов',
            'slug' => 'teplov-i-suhov',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function rubRate(): float
    {
        $rate = (float) (DB::table('suppliers')
            ->where('currency', 'RUB')
            ->where(function ($query): void {
                $query->where('code', 'ecokamin')->orWhere('name', 'ООО «СанБизнесГруп»');
            })
            ->whereBetween('currency_rate', [0.02, 0.08])
            ->value('currency_rate') ?? 0);

        return $rate > 0 ? $rate : 0.0361;
    }

    private function categorySlug(string $name, string $execution): string
    {
        $lower = mb_strtolower($name);
        if (str_contains($lower, 'шибер') || str_contains($lower, 'задвиж')) {
            return 'shibery-dymohod';
        }
        if (str_contains($lower, 'конденсат') || str_contains($lower, 'заглушка ревизии')) {
            return 'kondensatootvody';
        }
        if (str_contains($lower, 'площадка') || str_contains($lower, 'хомут') || str_contains($lower, 'креплен')) {
            return 'krepleniya-dymohod';
        }
        if (str_contains($lower, 'зонт') || str_contains($lower, 'дефлектор')) {
            return 'zonty-deflektory';
        }
        if (str_contains($lower, 'адаптер') || str_contains($lower, 'переход') || str_contains($lower, 'конус')) {
            return 'perehody-adaptery-dymohod';
        }
        if (str_contains($lower, 'тройник') || str_contains($lower, 'четверик')) {
            return $execution === 'Моно' ? 'troyniki-mono' : 'troyniki-sendvich';
        }
        if (str_contains($lower, 'отвод') || str_contains($lower, 'колено')) {
            return $execution === 'Моно' ? 'kolena-mono' : 'kolena-sendvich';
        }
        if (str_contains($lower, 'труба')) {
            return $execution === 'Моно' ? 'truby-mono' : 'truby-sendvich';
        }

        return 'prochie-dymohod';
    }

    private function content(string $name, string $series, string $diameter, string $description, string $execution): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeDescription = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $systemNote = $execution === 'Термо'
            ? 'Двустенная конструкция с изоляцией 30 мм предназначена для холодных участков дымохода и наружного монтажа.'
            : 'Одностенный элемент применяется на участках дымохода внутри отапливаемых помещений и при подключении к отопительному прибору.';

        return <<<HTML
<p><strong>{$safeName}</strong> — элемент дымоходной системы {$series}, типоразмер Ø{$diameter} мм. {$systemNote}</p>
<p>{$safeDescription}</p>
<h3>Как заказать</h3>
<p>Выберите нужный диаметр в переключателе карточки. Для комплектации всей трассы сообщите менеджеру модель котла, печи или камина, диаметр патрубка, высоту здания, расположение проходов и желаемый вариант монтажа.</p>
<p><strong>Срок поставки — 5–7 рабочих дней.</strong> Совместимость элементов и окончательную комплектацию менеджер проверит перед подтверждением заказа.</p>
HTML;
    }
};
