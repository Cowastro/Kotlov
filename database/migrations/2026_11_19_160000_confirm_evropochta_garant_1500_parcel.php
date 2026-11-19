<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PARCEL = [
        'weight' => 12.5,
        'length' => 40,
        'width' => 18,
        'height' => 25,
        'source' => 'confirmed_garant_1500',
    ];

    public function up(): void
    {
        $this->changeParcel(true);
    }

    public function down(): void
    {
        $this->changeParcel(false);
    }

    private function changeParcel(bool $enable): void
    {
        DB::transaction(function () use ($enable): void {
            $product = DB::table('products')
                ->where('sku', 'OMENERGO-E0201-0007')
                ->where('slug', 'ibp-garant-1500-24v-1500va-e0201-0007')
                ->lockForUpdate()
                ->first(['id', 'shipping_parcel']);

            if ($product === null) {
                return;
            }

            $previous = json_decode($product->shipping_parcel ?? 'null', true) ?? [];
            if ($enable ? array_filter($previous) !== [] : $previous != self::PARCEL) {
                return;
            }

            DB::table('products')->where('id', $product->id)->update([
                'shipping_parcel' => $enable ? json_encode(self::PARCEL, JSON_THROW_ON_ERROR) : null,
            ]);
        });
    }
};
