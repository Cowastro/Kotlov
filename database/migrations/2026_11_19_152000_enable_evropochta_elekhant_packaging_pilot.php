<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PILOT = [
        'weight' => 1,
        'length' => 24,
        'width' => 24,
        'height' => 20,
        'box' => 'box-24-24-20',
        'weight_mode' => 'band',
        'source' => 'pilot',
    ];

    public function up(): void
    {
        // This is a packing target with allowance, not a recorded measurement.
        $this->changeParcel(true);
    }

    public function down(): void
    {
        $this->changeParcel(false);
    }

    private function changeParcel(bool $enable): void
    {
        DB::transaction(function () use ($enable): void {
            $products = DB::table('products')
                ->where('sku', 'PS-007.059')
                ->where('slug', 'schetchik-gaza-bytovoj-yelexant-sgb-18');
            $product = $products->lockForUpdate()->first(['id', 'shipping_parcel']);
            if ($product === null) {
                return;
            }

            $previous = json_decode($product->shipping_parcel ?? 'null', true) ?? [];
            if ($enable ? array_filter($previous) !== [] : $previous != self::PILOT) {
                return;
            }

            DB::table('products')->where('id', $product->id)->update([
                'shipping_parcel' => $enable ? json_encode(self::PILOT, JSON_THROW_ON_ERROR) : null,
            ]);
        });
    }
};
