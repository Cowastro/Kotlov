<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'mercury';

    private const PARTNER_DISCOUNT_PERCENT = 10;

    /** @var array<string, float> */
    private const RETAIL_PRICES = [
        'PS-011.540' => 8490.00,
        'PS-011.541' => 7290.00,
        'PS-011.542' => 5640.00,
        'PS-011.543' => 3660.00,
        'PS-011.544' => 3660.00,
        'PS-011.545' => 3660.00,
        'PS-011.546' => 3660.00,
        'PS-011.547' => 5640.00,
        'PS-011.548' => 5640.00,
        'PS-011.549' => 5640.00,
        'PS-011.550' => 7290.00,
        'PS-011.551' => 7290.00,
        'PS-011.552' => 7290.00,
        'PS-011.553' => 7290.00,
        'PS-011.554' => 8490.00,
        'PS-011.555' => 8490.00,
        'PS-011.556' => 8490.00,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        DB::transaction(function (): void {
            $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
            if (! $supplier) {
                return;
            }

            $now = now();

            DB::table('suppliers')->where('id', $supplier->id)->update([
                'notes' => 'Партнёрская входная цена: скидка 10% от опубликованного прайса. Товары КСТБ считаются доступными минимум по 1 шт.; фактический остаток подтверждать перед крупным заказом.',
                'updated_at' => $now,
            ]);

            foreach (self::RETAIL_PRICES as $sku => $retailPrice) {
                $product = DB::table('products')->where('sku', $sku)->first();
                if (! $product) {
                    continue;
                }

                $minimumStock = $sku === 'PS-011.554' ? 7 : 1;
                $productStock = max((int) ($product->stock_qty ?? 0), $minimumStock);

                DB::table('products')->where('id', $product->id)->update([
                    'in_stock' => true,
                    'stock_qty' => $productStock,
                    'availability_status' => Product::AVAILABILITY_IN_STOCK,
                    'requires_price_confirmation' => false,
                    'updated_at' => $now,
                ]);

                $offer = DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)
                    ->first();

                if (! $offer) {
                    continue;
                }

                $partnerPrice = round($retailPrice * 0.9, 2);
                $offerStock = max((int) ($offer->stock_quantity ?? 0), $minimumStock);
                $raw = json_decode((string) $offer->raw, true);
                if (! is_array($raw)) {
                    $raw = [];
                }

                $raw = array_merge($raw, [
                    'published_retail_price_byn' => $retailPrice,
                    'partner_discount_percent' => self::PARTNER_DISCOUNT_PERCENT,
                    'price_is_supplier_cost' => true,
                    'partner_price_byn' => $partnerPrice,
                    'price_confirmed_at' => '2026-10-15',
                    'stock_source' => $sku === 'PS-011.554' ? 'manual_confirmation' : 'default_available',
                    'stock_confirmed_quantity' => $offerStock,
                    'stock_checked_at' => '2026-10-15',
                ]);

                DB::table('supplier_products')->where('id', $offer->id)->update([
                    'price' => $partnerPrice,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $partnerPrice,
                    'in_stock' => true,
                    'stock_quantity' => $offerStock,
                    'stock_status' => 'in_stock',
                    'stock_text' => 'В наличии: '.$offerStock.' шт.',
                    'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'last_synced_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Confirmed commercial terms and stock facts are intentionally durable.
    }
};
