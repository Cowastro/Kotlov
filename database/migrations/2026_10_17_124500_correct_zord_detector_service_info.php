<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SKUS = ['PS-008.168', 'PS-011.094'];

    private const SERVICE_INFO = [
        'Производитель' => 'ООО «ЗОРД Электротеплоприбор», г. Минск, ул. Олешева, 14, каб. 320',
        'Страна происхождения' => 'Беларусь',
        'Гарантия' => '24 месяца',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Product::query()->whereIn('sku', self::SKUS)->each(function (Product $product): void {
            $product->forceFill(['service_info' => self::SERVICE_INFO])->save();
        });
    }

    public function down(): void
    {
        // Do not restore service metadata copied from an unrelated product.
    }
};
