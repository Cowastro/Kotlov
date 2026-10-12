<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'bania';

    private const SOURCE_URL = 'https://drive.google.com/drive/folders/108zmF6VlM-AWiRgXaMK9BFFqgz2iAdNG';

    /**
     * Verified against the Bania wholesale stock and recommended retail
     * workbooks published on 2026-10-12. Each supplier article may be linked
     * to only one canonical product.
     *
     * @var array<string, array{article: string, name: string, cost: float, retail: float, stock: string}>
     */
    private const CURRENT_PRODUCTS = [
        // Accessories and water tanks.
        'KOTLOV-004485' => ['article' => 'ДМ1.16.900.00', 'name' => 'Модуль трубы d115-600 Чугун ЧХ (Теплодар)', 'cost' => 256.01, 'retail' => 295.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004467' => ['article' => 'БК.06.000.00', 'name' => 'Бак самоварного типа 55л (Теплодар)', 'cost' => 316.10, 'retail' => 365.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004466' => ['article' => 'БК.20.000.00', 'name' => 'Бак самоварного типа 45л (Теплодар)', 'cost' => 278.79, 'retail' => 321.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004407' => ['article' => 'АС.95.000.00', 'name' => 'Дверь чугунная банных печей (Теплодар)', 'cost' => 119.36, 'retail' => 137.00, 'stock' => 'Товар заканчивается'],
        'PS-000.803' => ['article' => 'БК.07.000.00', 'name' => 'Бак ПАРУС 60 РГ', 'cost' => 294.15, 'retail' => 339.00, 'stock' => 'Товар заканчивается'],
        'PS-000.805' => ['article' => 'БК.07.000.00-01', 'name' => 'Бак ПАРУС 80 РГ', 'cost' => 328.73, 'retail' => 379.00, 'stock' => 'Товар заканчивается'],
        'PS-001.663' => ['article' => 'РУ.01.000.00', 'name' => 'Регистр универсальный d115', 'cost' => 170.68, 'retail' => 197.00, 'stock' => 'В наличии'],
        'PS-003.815' => ['article' => 'АС25.000.00', 'name' => 'Теплосъемник ТОП-140', 'cost' => 33.48, 'retail' => 38.50, 'stock' => 'Товар заканчивается'],
        'PS-003.816' => ['article' => 'АС25.000.00-01', 'name' => 'Теплосъемник ТОП-200', 'cost' => 36.18, 'retail' => 41.50, 'stock' => 'Товар заканчивается'],
        'PS-003.817' => ['article' => 'АС.24.000.00', 'name' => 'Теплосъемник ТОП-300', 'cost' => 43.20, 'retail' => 49.50, 'stock' => 'Товар заканчивается'],
        'PS-011.495' => ['article' => 'ПО.36.000.00', 'name' => 'Печь МАТРИЦА-200 (1.1)', 'cost' => 1050.00, 'retail' => 1210.00, 'stock' => 'Товар заканчивается'],

        // Solid-fuel boilers.
        'PS-010.919' => ['article' => 'ОК.72.000.00', 'name' => 'Котел КУППЕР ОК-15 (2.0)', 'cost' => 1505.56, 'retail' => 1735.00, 'stock' => 'В наличии'],
        'KOTLOV-000332' => ['article' => 'ОК.74.000.00', 'name' => 'Котел КУППЕР ОК-20 (2.0)', 'cost' => 1772.71, 'retail' => 2045.00, 'stock' => 'В наличии'],
        'PS-010.918' => ['article' => 'ОК.66.000.00', 'name' => 'Котел КУППЕР ОК-9 (2.0)', 'cost' => 1368.79, 'retail' => 1580.00, 'stock' => 'В наличии'],
        'KOTLOV-000333' => ['article' => 'ОК.68.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-10 В', 'cost' => 1241.93, 'retail' => 1430.00, 'stock' => 'Товар заканчивается'],
        'PS-007.378' => ['article' => 'ОК.87.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-14 (1.1)', 'cost' => 1179.37, 'retail' => 1360.00, 'stock' => 'В наличии'],
        'KOTLOV-000338' => ['article' => 'ОК.59.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-16 В', 'cost' => 1521.27, 'retail' => 1755.00, 'stock' => 'В наличии'],
        'PS-007.679' => ['article' => 'ОК.90.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-20 (1.1)', 'cost' => 1365.95, 'retail' => 1575.00, 'stock' => 'В наличии'],
        'KOTLOV-000337' => ['article' => 'ОК.71.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-22 В', 'cost' => 1739.14, 'retail' => 2005.00, 'stock' => 'Товар заканчивается'],
        'PS-007.225' => ['article' => 'ОК.86.000.00', 'name' => 'Котел КУППЕР ПРАКТИК-8 (1.1)', 'cost' => 1007.88, 'retail' => 1160.00, 'stock' => 'В наличии'],
        'PS-004.259' => ['article' => 'ОК.94.000.00', 'name' => 'Котел КУППЕР УЮТ-10', 'cost' => 1405.96, 'retail' => 1620.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-000348' => ['article' => 'ОК.95.000.00', 'name' => 'Котел КУППЕР УЮТ-20', 'cost' => 1585.81, 'retail' => 1830.00, 'stock' => 'Товар заканчивается'],
        'PS-010.266' => ['article' => 'ОК.21.000.00', 'name' => 'Котел КУППЕР ПРО-16 (2.0)', 'cost' => 1923.31, 'retail' => 2220.00, 'stock' => 'В наличии'],
        'PS-010.267' => ['article' => 'ОК.60.000.00', 'name' => 'Котел КУППЕР ПРО-22 (2.0)', 'cost' => 2179.50, 'retail' => 2515.00, 'stock' => 'В наличии'],
        'PS-010.268' => ['article' => 'ОК.61.000.00', 'name' => 'Котел КУППЕР ПРО-28 (2.0)', 'cost' => 2457.56, 'retail' => 2835.00, 'stock' => 'Товар заканчивается'],
        'PS-010.269' => ['article' => 'ОК.62.000.00', 'name' => 'Котел КУППЕР ПРО-36 (2.0)', 'cost' => 2876.74, 'retail' => 3320.00, 'stock' => 'Товар заканчивается'],
        'PS-010.270' => ['article' => 'ОК.63.000.00', 'name' => 'Котел КУППЕР ПРО-42 (2.0)', 'cost' => 3464.72, 'retail' => 4000.00, 'stock' => 'Товар заканчивается'],
        'PS-011.030' => ['article' => 'ОК.65.000.00', 'name' => 'Котел КУППЕР ЭКСПЕРТ-15 (2.0)', 'cost' => 3336.71, 'retail' => 3850.00, 'stock' => 'Товар заканчивается'],
        'PS-011.843' => ['article' => 'ОК.76.000.00', 'name' => 'Котел КУППЕР ЭКСПЕРТ-22 (2.0)', 'cost' => 3753.80, 'retail' => 4335.00, 'stock' => 'Товар заканчивается'],
        'PS-011.029' => ['article' => 'ОК.79.000.00', 'name' => 'Котел КУППЕР ЭКСПЕРТ-30 (2.0)', 'cost' => 4332.78, 'retail' => 5000.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004320' => ['article' => 'ОК.80.000.00', 'name' => 'Котел КУППЕР ЭКСПЕРТ-45 (2.0)', 'cost' => 5936.94, 'retail' => 6855.00, 'stock' => 'Товар заканчивается'],

        // Electric boilers that still have an exact current equivalent.
        'PS-011.476' => ['article' => 'ОК.88.000.00', 'name' => 'Котел электрический КУППЕР СПУТНИК-6 (2.0) белый', 'cost' => 457.48, 'retail' => 528.00, 'stock' => 'Товар заканчивается'],
        'PS-011.477' => ['article' => 'ОК.88.000.00-03', 'name' => 'Котел электрический КУППЕР СПУТНИК-6 (2.0) черный', 'cost' => 457.48, 'retail' => 528.00, 'stock' => 'Товар заканчивается'],
        'PS-011.478' => ['article' => 'ОК.88.000.00-01', 'name' => 'Котел электрический КУППЕР СПУТНИК-9 (2.0) белый', 'cost' => 549.30, 'retail' => 634.00, 'stock' => 'Товар заканчивается'],

        // Sauna stoves already represented by exact site cards.
        'KOTLOV-004169' => ['article' => 'ПБ.33.000.00', 'name' => 'Печь РУСЬ-18 Л Профи', 'cost' => 1303.94, 'retail' => 1505.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004164' => ['article' => 'ПБ1.92.000.00', 'name' => 'Печь БЫЛИНА-24Ч (1.2)', 'cost' => 1423.17, 'retail' => 1640.00, 'stock' => 'В наличии'],
        'KOTLOV-004155' => ['article' => 'ПБ1.87.000.00', 'name' => 'Печь БЫЛИНА-18Ч (1.2)', 'cost' => 1191.52, 'retail' => 1375.00, 'stock' => 'В наличии'],
        'KOTLOV-004149' => ['article' => 'ПБ1.69.000.00', 'name' => 'Печь САХАРА-10 ЛБ (2.0)', 'cost' => 909.85, 'retail' => 1050.00, 'stock' => 'Товар заканчивается'],
        'KOTLOV-004137' => ['article' => 'ПБ1.96.000.00', 'name' => 'Печь РУСЬ-9 Л (1.1)', 'cost' => 610.88, 'retail' => 705.00, 'stock' => 'В наличии'],
        'KOTLOV-003880' => ['article' => 'ПБ1.96.000.00-01', 'name' => 'Печь РУСЬ-9 ЛУ (1.1)', 'cost' => 610.88, 'retail' => 705.00, 'stock' => 'В наличии'],
        'KOTLOV-003871' => ['article' => 'ПБ1.97.000.00', 'name' => 'Печь БЫЛИНА-12Ч (1.2)', 'cost' => 1156.84, 'retail' => 1335.00, 'stock' => 'В наличии'],
    ];

    /** @var list<string> */
    private const ARCHIVE_SKUS = [
        // No longer present in the current supplier stock workbook.
        'KOTLOV-004408', 'KOTLOV-003903',
        'KOTLOV-000356', 'KOTLOV-000353', 'KOTLOV-000352', 'KOTLOV-000351',
        'KOTLOV-000350', 'KOTLOV-000349', 'KOTLOV-000347', 'KOTLOV-000346',
        'KOTLOV-000345', 'KOTLOV-000342', 'PS-011.496', 'PS-011.481',
        'PS-011.480', 'PS-011.479', 'PS-011.272', 'PS-011.271',
        'PS-011.270', 'PS-007.329', 'PS-004.265', 'PS-004.264',
        'PS-000.869',

        // Superseded duplicates of canonical current cards.
        'KOTLOV-000355', 'KOTLOV-000354', 'KOTLOV-000343', 'KOTLOV-000341',
        'KOTLOV-000340', 'KOTLOV-000339', 'KOTLOV-000336', 'KOTLOV-000335',
        'KOTLOV-000334', 'KOTLOV-000330', 'KOTLOV-000329', 'PS-006.980',
        'PS-006.314', 'PS-006.072', 'PS-005.319', 'PS-004.263',
        'PS-004.261', 'PS-004.260',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if (! $supplier) {
            return;
        }

        DB::transaction(function () use ($supplier): void {
            $now = now();

            foreach (self::CURRENT_PRODUCTS as $sku => $item) {
                $product = DB::table('products')->where('sku', $sku)->first();
                if (! $product) {
                    continue;
                }

                $normalized = $this->normalizeArticle($item['article']);
                $compact = preg_replace('/\D+/u', '', $normalized) ?? '';

                $byArticle = DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where(function ($query) use ($item, $normalized): void {
                        $query->where('supplier_article', $item['article'])
                            ->orWhere('supplier_article_normalized', $normalized);
                    })
                    ->orderByDesc('id')
                    ->first();

                $byProduct = DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)
                    ->orderByDesc('id')
                    ->first();

                $target = $byArticle ?? $byProduct;
                $raw = json_encode([
                    'source' => 'bania_google_wholesale_price_list',
                    'google_price_list' => [
                        'name' => $item['name'],
                        'article' => $item['article'],
                        'price_column' => 'OPT with VAT',
                        'price_is_supplier_cost' => true,
                        'stock_text' => $item['stock'],
                    ],
                    'google_retail_price_list' => [
                        'article' => $item['article'],
                        'retail_price' => $item['retail'],
                    ],
                ], JSON_UNESCAPED_UNICODE);

                $values = [
                    'product_id' => $product->id,
                    'product_sku' => $sku,
                    'supplier_article' => $item['article'],
                    'supplier_article_normalized' => $normalized,
                    'supplier_article_compact' => $compact !== '' ? $compact : null,
                    'supplier_name' => $item['name'],
                    'source_url' => self::SOURCE_URL,
                    'price' => $item['cost'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $item['cost'],
                    'in_stock' => true,
                    'stock_quantity' => 1,
                    'stock_status' => 'in_stock',
                    'stock_text' => $item['stock'],
                    'match_status' => 'matched',
                    'match_confidence' => '100',
                    'raw' => $raw,
                    'last_stock_synced_at' => $now,
                    'last_synced_at' => $now,
                    'updated_at' => $now,
                ];

                if ($target) {
                    DB::table('supplier_products')->where('id', $target->id)->update($values);
                    $targetId = $target->id;
                } else {
                    $targetId = DB::table('supplier_products')->insertGetId($values + [
                        'supplier_id' => $supplier->id,
                        'created_at' => $now,
                    ]);
                }

                // Remove stale generic links such as "РУСЬ" and "БЫЛИНА", and
                // enforce a single Bania row for this product/article pair.
                DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where('id', '<>', $targetId)
                    ->where(function ($query) use ($product, $normalized): void {
                        $query->where('product_id', $product->id)
                            ->orWhere('supplier_article_normalized', $normalized);
                    })
                    ->delete();

                DB::table('products')->where('id', $product->id)->update([
                    'price' => $item['retail'],
                    'currency' => 'BYN',
                    'in_stock' => true,
                    'availability_status' => Product::AVAILABILITY_IN_STOCK,
                    'is_active' => true,
                    'is_archived' => false,
                    'updated_at' => $now,
                ]);
            }

            $archiveIds = DB::table('products')
                ->whereIn('sku', self::ARCHIVE_SKUS)
                ->pluck('id');

            if ($archiveIds->isNotEmpty()) {
                DB::table('products')->whereIn('id', $archiveIds)->update([
                    'is_active' => false,
                    'is_archived' => true,
                    'in_stock' => false,
                    'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
                    'updated_at' => $now,
                ]);

                DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('product_id', $archiveIds)
                    ->update([
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'out_of_stock',
                        'stock_text' => 'Архив: нет в актуальном прайсе или дубль',
                        'updated_at' => $now,
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Supplier catalogue corrections are intentionally not rolled back.
    }

    private function normalizeArticle(string $article): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-ZА-ЯЁ]+/u', '', trim($article)) ?? '');
    }
};
