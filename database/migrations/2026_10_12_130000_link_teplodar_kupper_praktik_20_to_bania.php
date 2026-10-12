<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_SKU = 'PS-007.679';
    private const LEGACY_SKU = 'KOTLOV-000331';
    private const SUPPLIER_CODE = 'bania';
    private const SUPPLIER_ARTICLE = 'ОК.90.000.00';
    private const SUPPLIER_ARTICLE_NORMALIZED = 'ОК9000000';
    private const SUPPLIER_ARTICLE_COMPACT = '9000000';
    private const SUPPLIER_COST = 1365.95;
    private const RETAIL_PRICE = 1575.00;

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $product = DB::table('products')->where('sku', self::CANONICAL_SKU)->first();
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if (! $product || ! $supplier) {
            return;
        }

        $now = now();
        $legacy = DB::table('products')->where('sku', self::LEGACY_SKU)->first();

        $productValues = [
            'price' => self::RETAIL_PRICE,
            'currency' => 'BYN',
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'updated_at' => $now,
        ];

        // The 1C product is canonical, but the legacy card has the verified
        // Praktik-20 image and richer technical description.
        if ($legacy) {
            foreach (['images', 'content', 'short_description', 'specs', 'warranty', 'weight'] as $field) {
                if ($legacy->{$field} !== null && $legacy->{$field} !== '') {
                    $productValues[$field] = $legacy->{$field};
                }
            }
        }

        DB::table('products')->where('id', $product->id)->update($productValues);

        $supplierProduct = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->where(function ($query) {
                $query->where('supplier_article', self::SUPPLIER_ARTICLE)
                    ->orWhere('supplier_article_normalized', self::SUPPLIER_ARTICLE_NORMALIZED);
            })
            ->first();

        $values = [
            'product_id' => $product->id,
            'product_sku' => self::CANONICAL_SKU,
            'supplier_article' => self::SUPPLIER_ARTICLE,
            'supplier_article_normalized' => self::SUPPLIER_ARTICLE_NORMALIZED,
            'supplier_article_compact' => self::SUPPLIER_ARTICLE_COMPACT,
            'supplier_name' => 'Котел КУППЕР ПРАКТИК-20 (1.1)',
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
                    'name' => 'Котел КУППЕР ПРАКТИК-20 (1.1)',
                    'article' => self::SUPPLIER_ARTICLE,
                    'price_column' => 'OPT with VAT',
                    'price_is_supplier_cost' => true,
                    'stock_text' => 'В наличии',
                ],
                'google_retail_price_list' => [
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

        if ($legacy) {
            DB::table('supplier_products')
                ->where('product_id', $legacy->id)
                ->where('supplier_id', $supplier->id)
                ->where('id', '<>', $supplierProduct->id ?? 0)
                ->update([
                    'product_id' => $product->id,
                    'product_sku' => self::CANONICAL_SKU,
                    'updated_at' => $now,
                ]);

            DB::table('products')->where('id', $legacy->id)->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Supplier catalogue corrections are intentionally not rolled back.
    }
};
