<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;

/**
 * Fix images for Fireway колосники R103 и R104.
 * pechnoydom.ru URLs were returning unknown file type → replace with fireway.pro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Product::where('slug', 'fireway-kolosnik-bhb-r103')->update([
            'images' => ['https://fireway.pro/assets/media/products/165/small/bhb-r103-350x200-2.jpg'],
        ]);

        Product::where('slug', 'fireway-kolosnik-bhb-r104')->update([
            'images' => ['https://fireway.pro/assets/media/products/166/small/bhb-r104-380x250-2.jpg'],
        ]);
    }

    public function down(): void {}
};
