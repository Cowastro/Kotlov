<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;
use App\Models\Brand;

/**
 * The 17.09.26 Thermostudio price list lists "Фартук D 260 (М)" twice under
 * two different supplier articles (TS.KMP.FRK.0260.79671-01 = 61.62 BYN on
 * the "ТиС 0.8" sheet, TS.KMP.FRK.0260.79671-02 = 47.14 BYN on the "УПШ,
 * ТермоВент, ППУ" sheet) with an identical display name, so both got created
 * as separate cards. User decision: keep the 61.62 card (main "ТиС 0.8"
 * sheet), archive the 47.14 duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $brandId = Brand::where('name', 'Теплов и Сухов')->value('id');

        Product::where('brand_id', $brandId)
            ->where('name', 'like', '%Фартук D 260%')
            ->where('price', 47.14)
            ->update([
                'is_archived' => true,
                'is_active' => false,
            ]);
    }

    public function down(): void {}
};
