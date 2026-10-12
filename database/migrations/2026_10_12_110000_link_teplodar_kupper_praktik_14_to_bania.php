<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCT_SKU = 'PS-007.378';
    private const SUPPLIER_CODE = 'bania';
    private const SUPPLIER_ARTICLE = 'ОК.87.000.00';
    private const SUPPLIER_ARTICLE_NORMALIZED = 'ОК8700000';
    private const SUPPLIER_ARTICLE_COMPACT = '8700000';
    private const SUPPLIER_COST = 1179.37;
    private const RETAIL_PRICE = 1360.00;

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $product = DB::table('products')->where('sku', self::PRODUCT_SKU)->first();
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if (! $product || ! $supplier) {
            return;
        }

        $now = now();
        $supplierProduct = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->where(function ($query) {
                $query->where('supplier_article', self::SUPPLIER_ARTICLE)
                    ->orWhere('supplier_article_normalized', self::SUPPLIER_ARTICLE_NORMALIZED);
            })
            ->first();

        $values = [
            'product_id' => $product->id,
            'product_sku' => self::PRODUCT_SKU,
            'supplier_article' => self::SUPPLIER_ARTICLE,
            'supplier_article_normalized' => self::SUPPLIER_ARTICLE_NORMALIZED,
            'supplier_article_compact' => self::SUPPLIER_ARTICLE_COMPACT,
            'supplier_name' => 'Котел КУППЕР ПРАКТИК-14 (1.1)',
            'source_url' => 'https://drive.google.com/drive/folders/108zmF6VlM-AWiRgXaMK9BFFqgz2iAdNG',
            'price' => self::SUPPLIER_COST,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => self::SUPPLIER_COST,
            'in_stock' => true,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'stock_text' => 'В наличии',
            'match_status' => 'matched',
            'match_confidence' => '100',
            'raw' => json_encode([
                'google_price_list' => [
                    'row' => 1526,
                    'name' => 'Котел КУППЕР ПРАКТИК-14 (1.1)',
                    'article' => self::SUPPLIER_ARTICLE,
                    'price_column' => 'OPT with VAT',
                    'price_is_supplier_cost' => true,
                    'stock_text' => 'В наличии',
                ],
                'google_retail_price_list' => [
                    'row' => 2713,
                    'article' => self::SUPPLIER_ARTICLE,
                    'retail_price' => self::RETAIL_PRICE,
                ],
            ], JSON_UNESCAPED_UNICODE),
            'last_stock_synced_at' => $now,
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if ($supplierProduct) {
            DB::table('supplier_products')->where('id', $supplierProduct->id)->update($values);
        } else {
            DB::table('supplier_products')->insert($values + [
                'supplier_id' => $supplier->id,
                'created_at' => $now,
            ]);
        }

        DB::table('products')->where('id', $product->id)->update([
            'price' => self::RETAIL_PRICE,
            'currency' => 'BYN',
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Supplier catalogue corrections are intentionally not rolled back.
    }
};
