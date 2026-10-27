<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        $categoryId = DB::table('categories')
            ->whereIn('slug', ['krepleniya-dymohod', 'dymohody'])
            ->orderByRaw("CASE WHEN slug = 'krepleniya-dymohod' THEN 0 ELSE 1 END")
            ->value('id');

        if (! $categoryId) {
            $now = now();
            $rootId = DB::table('categories')->where('slug', 'dymohody')->value('id');
            if (! $rootId) {
                $rootId = DB::table('categories')->insertGetId([
                    'parent_id' => 0,
                    'name' => 'Дымоходы',
                    'slug' => 'dymohody',
                    'h1' => 'Дымоходы',
                    'type' => 'main',
                    'sort_order' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $categoryId = DB::table('categories')->insertGetId([
                'parent_id' => $rootId,
                'name' => 'Крепления и монтаж дымохода',
                'slug' => 'krepleniya-dymohod',
                'h1' => 'Крепления и монтаж дымохода',
                'type' => 'child',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $items = [
            [
                'name' => 'Базальтовая вата для набивки ППУ (3 кг)',
                'match' => 'Базальтовая вата для набивки ППУ',
                'slug' => 'bazaltovaya-vata-dlya-nabivki-ppu-3-kg',
                'sku' => 'SGB-BASALT-WOOL-3KG',
                'supplier_article' => 'Базальтовая вата',
                'price' => 25.00,
                'source_url' => 'https://sbg.by/p121547453-bazaltovaya-vata-dlya.html',
                'short_description' => 'Негорючая базальтовая вата в упаковке 3 кг для заполнения проходных узлов дымохода.',
                'content' => '<p><strong>Базальтовая вата, 3 кг</strong> применяется для заполнения прохода перекрытия (ППУ) при монтаже дымохода. Материал негорючий и устойчив к температурным деформациям.</p><p>Количество и плотность заполнения, пожарные отступы и совместимость с конкретным проходным узлом должен проверить специалист по монтажу.</p>',
                'specs' => [
                    ['key' => 'Тип', 'value' => 'Базальтовая вата для ППУ'],
                    ['key' => 'Масса упаковки', 'value' => '3 кг'],
                    ['key' => 'Горючесть', 'value' => 'Негорючий материал'],
                ],
            ],
            [
                'name' => 'Базальтовый картон 1,0 × 0,5 м, толщина 5 мм',
                'match' => 'Базальтовый картон 1,0х0,5м толщина 5 мм',
                'slug' => 'bazaltovyy-karton-1000x500x5-mm',
                'sku' => 'SGB-BASALT-CARDBOARD-5MM',
                'supplier_article' => 'базальтовый картон',
                'price' => 10.00,
                'source_url' => 'https://sbg.by/p62513408-bazaltovyj-karton-10h05m.html',
                'short_description' => 'Лист базальтового картона 1,0 × 0,5 м толщиной 5 мм для жаростойкой изоляции прохода дымохода.',
                'content' => '<p><strong>Базальтовый картон 1,0 × 0,5 м, 5 мм</strong> используется как жаростойкая прослойка при устройстве проходов труб через перекрытия и кровлю, а также для изоляции печей, котлов и трубопроводов.</p><p>Схему укладки, количество листов и пожарные отступы необходимо согласовать со специалистом по монтажу.</p>',
                'specs' => [
                    ['key' => 'Тип', 'value' => 'Базальтовый картон'],
                    ['key' => 'Размер листа', 'value' => '1,0 × 0,5 м'],
                    ['key' => 'Толщина', 'value' => '5 мм'],
                    ['key' => 'Страна производства', 'value' => 'Россия'],
                ],
            ],
        ];

        foreach ($items as $item) {
            $this->ensureProduct((int) $categoryId, $item);
        }
    }

    /** @param array<string, mixed> $item */
    private function ensureProduct(int $categoryId, array $item): void
    {
        DB::transaction(function () use ($categoryId, $item): void {
            $integration = Schema::hasTable('integration_products')
                ? DB::table('integration_products')
                    ->where('name', 'like', '%'.$item['match'].'%')
                    ->orderByRaw('CASE WHEN product_id IS NOT NULL THEN 0 ELSE 1 END')
                    ->first()
                : null;

            $productId = $integration?->product_id;
            if (! $productId) {
                $productId = DB::table('products')
                    ->where(fn ($query) => $query
                        ->where('slug', $item['slug'])
                        ->orWhere('sku', $item['sku'])
                        ->orWhere('name', 'like', '%'.$item['match'].'%'))
                    ->value('id');
            }

            $now = now();
            if (! $productId) {
                $productId = DB::table('products')->insertGetId([
                    'category_id' => $categoryId,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'h1' => $item['name'],
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'currency' => 'BYN',
                    'short_description' => $item['short_description'],
                    'content' => $item['content'],
                    'content_source_url' => $item['source_url'],
                    'specs' => json_encode($item['specs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'unit' => 'шт',
                    'is_active' => true,
                    'is_archived' => false,
                    'in_stock' => false,
                    'availability_status' => 'check',
                    'requires_price_confirmation' => false,
                    'manual_stock_management' => false,
                    'sort_order' => 500,
                    'meta_title' => $item['name'].' — купить в Беларуси',
                    'meta_description' => $item['short_description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($integration && ! $integration->product_id) {
                DB::table('integration_products')->where('id', $integration->id)->update([
                    'product_id' => $productId,
                    'target_category_id' => $categoryId,
                    'match_status' => 'matched',
                    'match_method' => 'exact_accessory_name',
                    'match_confidence' => 1,
                    'matched_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! Schema::hasTable('supplier_products')) {
                return;
            }

            $supplierId = Schema::hasTable('suppliers')
                ? DB::table('suppliers')
                    ->where(fn ($query) => $query
                        ->where('code', 'sanbusinessgroup')
                        ->orWhere('name', 'like', '%СанБизнесГруп%'))
                    ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
                    ->value('id')
                : null;

            if (! $supplierId) {
                return;
            }

            DB::table('supplier_products')->updateOrInsert(
                ['supplier_id' => $supplierId, 'supplier_article' => $item['supplier_article']],
                [
                    'product_id' => $productId,
                    'product_sku' => $item['sku'],
                    'supplier_article_normalized' => mb_strtolower($item['supplier_article']),
                    'supplier_article_compact' => preg_replace('/[^a-zа-я0-9]+/iu', '', mb_strtolower($item['supplier_article'])),
                    'supplier_name' => $item['name'],
                    'source_url' => $item['source_url'],
                    'price' => null,
                    'currency' => 'BYN',
                    'price_byn' => null,
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'unknown',
                    'stock_text' => 'Наличие и закупочную цену уточнить у ООО «СанБизнесГруп»',
                    'warehouse_name' => 'ООО «СанБизнесГруп»',
                    'match_status' => 'matched',
                    'match_confidence' => 'manual_price_list',
                    'raw' => json_encode(['source' => 'sbg.by', 'retail_reference_price_byn' => $item['price']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        });
    }

    public function down(): void
    {
        // Catalog reconciliation is intentionally non-destructive.
    }
};
