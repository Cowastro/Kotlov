<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRODUCT_SKU = 'PS-011.554';

    private const SUPPLIER_CODE = 'mercury';

    private const STOCK_QUANTITY = 7;

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        DB::transaction(function (): void {
            $product = DB::table('products')->where('sku', self::PRODUCT_SKU)->first();
            $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');

            if (! $product || ! $supplierId) {
                return;
            }

            $now = now();

            DB::table('products')->where('id', $product->id)->update([
                'in_stock' => true,
                'stock_qty' => self::STOCK_QUANTITY,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
                'requires_price_confirmation' => false,
                'updated_at' => $now,
            ]);

            $offer = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('product_id', $product->id)
                ->first();

            if (! $offer) {
                return;
            }

            $raw = json_decode((string) $offer->raw, true);
            if (! is_array($raw)) {
                $raw = [];
            }

            $raw = array_merge($raw, [
                'stock_source' => 'manual_confirmation',
                'stock_confirmed_quantity' => self::STOCK_QUANTITY,
                'stock_checked_at' => '2026-10-15',
                'price_confirmation_source' => 'official_manufacturer_catalog',
                'price_confirmed_at' => '2026-10-15',
            ]);

            DB::table('supplier_products')->where('id', $offer->id)->update([
                'in_stock' => true,
                'stock_quantity' => self::STOCK_QUANTITY,
                'stock_status' => 'in_stock',
                'stock_text' => 'В наличии: 7 шт.',
                'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        // A confirmed stock fact must not be silently reverted on rollback.
    }
};
