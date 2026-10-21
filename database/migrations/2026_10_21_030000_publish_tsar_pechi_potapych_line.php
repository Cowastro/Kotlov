<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_SLUG = 'pech-otopitelnaya-tsar-pechi-potapyich';

    private const VIDEO_URL = 'https://vkvideo.ru/video_ext.php?oid=-94642717&id=456239064&hash=0f27a0dfd37dff7e';

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $base = DB::table('products')
            ->where('slug', self::BASE_SLUG)
            ->orWhere('id', 12111)
            ->first();

        if (! $base) {
            return;
        }

        $now = now();
        $rubToByn = (float) $base->price > 0
            ? (float) $base->price / 6900
            : 297 / 6900;
        $supplierId = $this->sanBusinessGroupSupplierId();
        $brandId = (int) ($base->brand_id ?? 0);

        if ($supplierId && $brandId && Schema::hasTable('brand_supplier')) {
            DB::table('brand_supplier')->updateOrInsert(
                ['brand_id' => $brandId, 'supplier_id' => $supplierId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach ($this->products() as $item) {
            $productId = $this->upsertProduct($base, $item, $rubToByn, $now);

            if ($supplierId && $productId) {
                $this->linkSupplier($supplierId, $productId, $item, $now);
            }
        }

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // Published catalogue data and supplier links are intentionally durable.
    }

    private function upsertProduct(object $base, array $item, float $rubToByn, $now): int
    {
        $existingId = DB::table('products')
            ->where('slug', $item['slug'])
            ->orWhere('name', $item['name'])
            ->value('id');

        $payload = [
            'name' => $item['name'],
            'slug' => $item['slug'],
            'h1' => $item['name'],
            'price' => round($item['retail_rub'] * $rubToByn, 2),
            'price_old' => null,
            'currency' => 'BYN',
            'short_description' => $item['short'],
            'content' => $this->content($item),
            'images' => json_encode([$item['image']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'specs' => json_encode($item['specs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'video_url' => $item['with_burner'] ? self::VIDEO_URL : null,
            'weight' => $item['weight'],
            'unit' => 'шт',
            'warranty' => '5 лет',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'stock_qty' => null,
            'is_new' => $item['slug'] !== self::BASE_SLUG,
            'meta_title' => $item['meta_title'],
            'meta_keywords' => $item['meta_keywords'],
            'meta_description' => $item['meta_description'],
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('products', 'content_source_url')) {
            $payload['content_source_url'] = $item['source_url'];
        }
        if (Schema::hasColumn('products', 'availability_status')) {
            $payload['availability_status'] = 'check';
        }
        if (Schema::hasColumn('products', 'requires_price_confirmation')) {
            $payload['requires_price_confirmation'] = false;
        }

        if ($existingId) {
            DB::table('products')->where('id', $existingId)->update($payload);

            return (int) $existingId;
        }

        $clone = (array) $base;
        unset($clone['id']);
        $clone = array_merge($clone, $payload, [
            'sku' => $this->nextSku(),
            'created_at' => $now,
        ]);

        return (int) DB::table('products')->insertGetId($clone);
    }

    private function linkSupplier(int $supplierId, int $productId, array $item, $now): void
    {
        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        $productSku = DB::table('products')->where('id', $productId)->value('sku');
        $article = 'TSAR-POTAPYCH-'.$item['source_wp_id'];
        $payload = [
            'product_id' => $productId,
            'product_sku' => $productSku,
            'supplier_name' => $item['name'],
            'source_url' => $item['source_url'],
            'source_wp_id' => (string) $item['source_wp_id'],
            'price' => null,
            'currency' => 'RUB',
            'in_stock' => false,
            'match_status' => 'matched',
            'match_confidence' => 'manual',
            'raw' => json_encode([
                'brand' => 'Царь-Печи',
                'supplier' => 'ООО «СанБизнесГруп»',
                'manufacturer_source' => 'BANPECHI',
                'retail_price_rub' => $item['retail_rub'],
                'purchase_price_status' => 'по запросу',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('supplier_products', 'supplier_article_normalized')) {
            $payload['supplier_article_normalized'] = $article;
        }
        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            $payload['supplier_article_compact'] = str_replace('-', '', $article);
        }
        if (Schema::hasColumn('supplier_products', 'currency_rate')) {
            $payload['currency_rate'] = (float) DB::table('suppliers')->where('id', $supplierId)->value('currency_rate') ?: 1;
        }
        if (Schema::hasColumn('supplier_products', 'price_byn')) {
            $payload['price_byn'] = null;
        }
        if (Schema::hasColumn('supplier_products', 'stock_quantity')) {
            $payload['stock_quantity'] = null;
        }
        if (Schema::hasColumn('supplier_products', 'stock_status')) {
            $payload['stock_status'] = 'preorder';
        }
        if (Schema::hasColumn('supplier_products', 'stock_text')) {
            $payload['stock_text'] = 'Наличие и закупочная цена по запросу у ООО «СанБизнесГруп»';
        }
        if (Schema::hasColumn('supplier_products', 'warehouse_name')) {
            $payload['warehouse_name'] = 'ООО «СанБизнесГруп»';
        }

        $exists = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('supplier_article', $article)
            ->exists();
        if (! $exists) {
            $payload['created_at'] = $now;
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $article],
            $payload,
        );
    }

    private function sanBusinessGroupSupplierId(): ?int
    {
        $id = DB::table('suppliers')
            ->where('name', 'ООО «СанБизнесГруп»')
            ->orWhere('name', 'like', '%СанБизнесГруп%')
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        if (Schema::hasTable('integration_sources') && Schema::hasColumn('integration_sources', 'supplier_id')) {
            $id = DB::table('integration_sources')
                ->where('name', '1С СанБизнесГруп')
                ->value('supplier_id');
        }

        return $id ? (int) $id : null;
    }

    private function nextSku(): string
    {
        $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return 'KOTLOV-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function content(array $item): string
    {
        $volume = $item['specs']['Объём помещения'];
        $dimensions = $item['specs']['Габариты (Ш×Г×В)'];
        $fuel = $item['specs']['Вид топлива'];
        $feature = $item['with_hob']
            ? 'В верхней части предусмотрена конфорка: на печи можно подогреть воду или приготовить простую пищу.'
            : 'Компактная конструкция рассчитана прежде всего на быстрый обогрев небольшого помещения.';
        $burner = $item['with_burner']
            ? 'В комплект входит пеллетная горелка. Печь может работать на дровах и пеллетах; длительность горения одной загрузки зависит от объёма бункера и может достигать 10 часов.'
            : 'Пеллетную горелку «Потапыч» можно приобрести отдельно и установить вместо штатного режима топки дровами.';

        return <<<HTML
<p><strong>{$item['name']}</strong> — компактная бюджетная отопительная печь бренда «Царь-Печи» из стали толщиной 3 мм. Подходит для дачи, гаража, теплицы, бытовки и других помещений объёмом {$volume}.</p>
<p>{$feature}</p>
<p>{$burner}</p>
<h3>Основные характеристики</h3>
<ul>
    <li>габариты — {$dimensions};</li>
    <li>масса — {$item['weight']} кг;</li>
    <li>диаметр дымохода — 115 мм;</li>
    <li>топливо — {$fuel};</li>
    <li>гарантия производителя — 5 лет.</li>
</ul>
<p>Поставщик — ООО «СанБизнесГруп». Наличие и закупочную цену менеджер проверяет перед подтверждением заказа.</p>
HTML;
    }

    private function products(): array
    {
        return [
            [
                'name' => 'Печь отопительная Царь-Печи Потапыч 3 мм',
                'slug' => self::BASE_SLUG,
                'retail_rub' => 6900,
                'weight' => 18,
                'with_hob' => false,
                'with_burner' => false,
                'source_wp_id' => 'potapych-3mm',
                'source_url' => 'https://banpechi.ru/product/otopitelnue/potapyich-3-mm/',
                'image' => 'img/products/tsar-pechi/potapych-3mm-v2.webp',
                'short' => 'Бюджетная отопительная печь «Потапыч» из стали 3 мм для помещений объёмом 45–60 м³. Дымоход 115 мм, масса 18 кг.',
                'specs' => $this->specs('45–60 м³', '320 × 400 × 350 мм', '18 кг', 'до 40 см', false, false),
                'meta_title' => 'Печь Царь-Печи Потапыч 3 мм купить в Минске | KOTLOV.BY',
                'meta_keywords' => 'печь Потапыч, Царь-Печи Потапыч 3 мм, печь для гаража, печь для теплицы',
                'meta_description' => 'Купить печь Царь-Печи Потапыч 3 мм в Беларуси. Обогрев 45–60 м³, дымоход 115 мм, масса 18 кг. Поставщик ООО «СанБизнесГруп».',
            ],
            [
                'name' => 'Печь отопительная Царь-Печи Потапыч с конфоркой 3 мм',
                'slug' => 'pech-otopitelnaya-tsar-pechi-potapyich-s-konforkoy-3-mm',
                'retail_rub' => 8500,
                'weight' => 20,
                'with_hob' => true,
                'with_burner' => false,
                'source_wp_id' => 'potapych-hob-3mm',
                'source_url' => 'https://banpechi.ru/product/otopitelnue/potapyich-s-konforkoy-3-mm/',
                'image' => 'img/products/tsar-pechi/potapych-hob-3mm-v2.webp',
                'short' => 'Новая отопительно-варочная печь «Потапыч» из стали 3 мм с конфоркой. Для помещений 50–70 м³, дымоход 115 мм.',
                'specs' => $this->specs('50–70 м³', '320 × 460 × 350 мм', '20 кг', 'до 45 см', true, false),
                'meta_title' => 'Потапыч с конфоркой 3 мм купить в Минске | KOTLOV.BY',
                'meta_keywords' => 'Потапыч с конфоркой, отопительно-варочная печь, Царь-Печи, печь 3 мм',
                'meta_description' => 'Печь Царь-Печи Потапыч с конфоркой 3 мм: обогрев 50–70 м³, масса 20 кг, дымоход 115 мм. Цена и заказ в Беларуси.',
            ],
            [
                'name' => 'Комплект Царь-Печи Потапыч с пеллетной горелкой',
                'slug' => 'pech-otopitelnaya-tsar-pechi-potapyich-s-pelletnoy-gorelkoy',
                'retail_rub' => 10900,
                'weight' => 23,
                'with_hob' => false,
                'with_burner' => true,
                'source_wp_id' => '4418',
                'source_url' => 'https://banpechi.ru/product/otopitelnue/potapyich-s-pelletnoy-gorelkoy/',
                'image' => 'img/products/tsar-pechi/potapych-pellet-v2.webp',
                'short' => 'Готовый комплект «Потапыч»: печь из стали 3 мм и пеллетная горелка. Работа на дровах или пеллетах, обогрев 45–60 м³.',
                'specs' => $this->specs('45–60 м³', '320 × 400 × 350 мм', '23 кг', 'до 40 см', false, true),
                'meta_title' => 'Потапыч с пеллетной горелкой купить в Минске | KOTLOV.BY',
                'meta_keywords' => 'Потапыч с пеллетной горелкой, комплект печь горелка, Царь-Печи',
                'meta_description' => 'Комплект Царь-Печи Потапыч с пеллетной горелкой: дрова и пеллеты, обогрев 45–60 м³, горение до 10 часов. Заказ в Беларуси.',
            ],
            [
                'name' => 'Комплект Царь-Печи Потапыч с конфоркой и пеллетной горелкой',
                'slug' => 'pech-otopitelnaya-tsar-pechi-potapyich-s-konforkoy-i-pelletnoy-gorelkoy',
                'retail_rub' => 12500,
                'weight' => 25,
                'with_hob' => true,
                'with_burner' => true,
                'source_wp_id' => 'potapych-hob-pellet',
                'source_url' => 'https://banpechi.ru/product/otopitelnue/potapyich-s-konforkoy-i-pelletnoy-gorelkoy/',
                'image' => 'img/products/tsar-pechi/potapych-hob-pellet-v2.webp',
                'short' => 'Комплект «Потапыч» с конфоркой и пеллетной горелкой: отопление и приготовление пищи, работа на дровах или пеллетах, 50–70 м³.',
                'specs' => $this->specs('50–70 м³', '320 × 460 × 350 мм', '25 кг', 'до 45 см', true, true),
                'meta_title' => 'Потапыч с конфоркой и пеллетной горелкой | KOTLOV.BY',
                'meta_keywords' => 'Потапыч с конфоркой и пеллетной горелкой, Царь-Печи, комплект печи',
                'meta_description' => 'Комплект Царь-Печи Потапыч с конфоркой и пеллетной горелкой: 50–70 м³, дрова или пеллеты, горение до 10 часов.',
            ],
        ];
    }

    private function specs(string $volume, string $dimensions, string $weight, string $logLength, bool $withHob, bool $withBurner): array
    {
        $specs = [
            'Бренд' => 'Царь-Печи',
            'Поставщик' => 'ООО «СанБизнесГруп»',
            'Тип' => $withHob ? 'Отопительно-варочная печь' : 'Отопительная печь',
            'Объём помещения' => $volume,
            'Габариты (Ш×Г×В)' => $dimensions,
            'Масса' => $weight,
            'Материал корпуса' => 'Сталь',
            'Толщина стали' => '3 мм',
            'Диаметр дымохода' => '115 мм',
            'Максимальная длина полена' => $logLength,
            'Варочная конфорка' => $withHob ? 'Да' : 'Нет',
            'Пеллетная горелка в комплекте' => $withBurner ? 'Да' : 'Нет',
            'Вид топлива' => $withBurner ? 'Дрова / пеллеты' : 'Дрова',
            'Гарантия' => '5 лет',
        ];

        if ($withBurner) {
            $specs['Горение на одной загрузке пеллет'] = 'до 10 часов, зависит от объёма бункера';
        }

        return $specs;
    }
};
