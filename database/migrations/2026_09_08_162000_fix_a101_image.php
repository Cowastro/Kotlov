<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;

return new class extends Migration
{
    public function up(): void
    {
        // A101: правильное главное фото (не /small/ thumbnail)
        Product::where('slug', 'fireway-a101-nabor-furnitury')->update([
            'images' => ['https://fireway.pro/assets/media/products/30/291599719-w800-h640-a101.jpg'],
        ]);
        // Набор крёпежных К4: тоже использовал A101-thumbnail — убираем
        Product::where('slug', 'fireway-nabor-krepezha-k4')->update([
            'images' => [],
        ]);
    }

    public function down(): void {}
};
