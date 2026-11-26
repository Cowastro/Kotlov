<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('code', 'santehsklad')->where('is_active', true)->first();
        if (! $supplier) {
            return;
        }

        DB::transaction(function () use ($supplier): void {
            foreach ([26 => 'KOTLOV-000293', 36 => 'KOTLOV-000296', 50 => 'KOTLOV-000294'] as $power => $sku) {
                $product = DB::table('products')->where('sku', $sku)
                    ->where('slug', 'pelletnyi-kotel-sakovich-pellet-universal-'.$power)
                    ->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }

                $offer = DB::table('supplier_products')->where('supplier_id', $supplier->id)
                    ->where('supplier_article_normalized', 'PELLET-UNIVERSAL-'.$power)
                    ->lockForUpdate()->first();
                if (! $offer || ($offer->product_id && (int) $offer->product_id !== (int) $product->id)) {
                    continue;
                }

                $source = json_decode($offer->raw ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                $retail = $source['retail'] ?? null;
                if (($source['identity'] ?? '') !== 'pellet_universal_'.$power || ! is_numeric($retail) || $retail <= 0) {
                    continue;
                }

                DB::table('supplier_products')->where('id', $offer->id)->update([
                    'product_id' => $product->id,
                    'product_sku' => $sku,
                    'match_status' => 'matched',
                    'match_confidence' => 'manual_price_list',
                    'updated_at' => now(),
                ]);
                DB::table('products')->where('id', $product->id)->update([
                    'price' => $retail,
                    'requires_price_confirmation' => false,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Supplier links and prices may have been updated by a subsequent import.
    }
};
