<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'izosil-plita-kalcii-silikatnaia-seraia-1000122030-mm';

    private const SUPPLIER_CODE = 'bania';

    /**
     * The BANIA stock workbook has no article for this row. Keep the same
     * stable fallback identifier used by SyncBaniaPricelistCommand so future
     * imports can update the cost and availability by the exact product title.
     */
    private const SUPPLIER_ARTICLE = 'BANIA-PRICE-ROW-1965';

    private const SUPPLIER_ARTICLE_NORMALIZED = 'BANIAPRICEROW1965';

    private const SUPPLIER_NAME = 'Плита IZOSIL серая (Силикат кальция) 1000х1220х30мм';

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $product = DB::table('products')
            ->where('slug', self::PRODUCT_SLUG)
            ->first(['id', 'sku']);
        $supplierId = DB::table('suppliers')
            ->where('code', self::SUPPLIER_CODE)
            ->value('id');

        if (! $product || ! $supplierId) {
            return;
        }

        $now = now();
        $raw = json_encode([
            'source' => 'bania_google_wholesale_price_list',
            'google_price_list' => [
                'row' => 1965,
                'name' => self::SUPPLIER_NAME,
                'article' => null,
                'price_column' => 'OPT with VAT',
                'price_is_supplier_cost' => true,
                'stock_text' => 'В наличии',
                'price_list_date' => '2026-10-29',
            ],
            'link_reason' => 'Exact title and dimensions confirmed in the BANIA.by wholesale workbook.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $existing = DB::table('supplier_products')
            ->where('supplier_id', (int) $supplierId)
            ->where(function ($query): void {
                $query->where('supplier_article', self::SUPPLIER_ARTICLE)
                    ->orWhere('supplier_name', self::SUPPLIER_NAME);
            })
            ->orderByRaw('product_id IS NULL DESC')
            ->first(['id']);

        $payload = [
            'supplier_sync_id' => null,
            'product_id' => (int) $product->id,
            'product_sku' => (string) $product->sku,
            'supplier_name' => self::SUPPLIER_NAME,
            'price' => 125.40,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => 125.40,
            'in_stock' => true,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'stock_text' => 'В наличии',
            'warehouse_name' => 'Склад партнёра',
            'delivery_days' => null,
            'match_status' => 'matched',
            'match_confidence' => 'manual',
            'raw' => $raw,
            'last_stock_synced_at' => $now,
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('supplier_products')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('supplier_products')->insert($payload + [
            'supplier_id' => (int) $supplierId,
            'supplier_article' => self::SUPPLIER_ARTICLE,
            'supplier_article_normalized' => self::SUPPLIER_ARTICLE_NORMALIZED,
            'supplier_article_compact' => self::SUPPLIER_ARTICLE_NORMALIZED,
            'source_url' => null,
            'source_wp_id' => null,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_products') || ! Schema::hasTable('suppliers')) {
            return;
        }

        $supplierId = DB::table('suppliers')
            ->where('code', self::SUPPLIER_CODE)
            ->value('id');

        if (! $supplierId) {
            return;
        }

        DB::table('supplier_products')
            ->where('supplier_id', (int) $supplierId)
            ->where('supplier_article', self::SUPPLIER_ARTICLE)
            ->delete();
    }
};
