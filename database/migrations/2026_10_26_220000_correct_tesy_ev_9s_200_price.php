<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = '305054-200l-napolnyi-vodonagrevatel-kosvennogo-nagreva-ev-9-s-200-60-w-tesy';

    public function up(): void
    {
        $productId = DB::table('products')->where('slug', self::SLUG)->value('id');
        if (! $productId) {
            return;
        }

        DB::table('products')->where('id', $productId)->update([
            'price' => 2200,
            'requires_price_confirmation' => false,
            'updated_at' => now(),
        ]);

        DB::table('supplier_products')
            ->where('product_id', $productId)
            ->where('stock_status', 'missing_from_feed')
            ->update([
                'price' => null,
                'price_byn' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')->where('slug', self::SLUG)->update([
            'price' => 1739,
            'updated_at' => now(),
        ]);
    }
};
