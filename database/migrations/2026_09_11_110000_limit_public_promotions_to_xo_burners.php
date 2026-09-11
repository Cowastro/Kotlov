<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('is_sale', true)
            ->whereNotIn('slug', Product::PUBLIC_SALE_SLUGS)
            ->update([
                'is_sale' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Старые массовые флаги акций были недостоверными, поэтому не восстанавливаем их автоматически.
    }
};
