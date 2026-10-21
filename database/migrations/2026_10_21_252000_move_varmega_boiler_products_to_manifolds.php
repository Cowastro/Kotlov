<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brandId = DB::table('brands')
            ->whereRaw('LOWER(name) = ?', ['varmega'])
            ->value('id');
        $boilersId = DB::table('categories')->where('slug', 'kotly')->value('id');
        $manifoldsId = DB::table('categories')->where('slug', 'grebenki')->value('id');

        if (! $brandId || ! $boilersId || ! $manifoldsId) {
            return;
        }

        DB::table('products')
            ->where('brand_id', $brandId)
            ->where('category_id', $boilersId)
            ->update([
                'category_id' => $manifoldsId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Не возвращаем коллекторы Varmega в заведомо неверную категорию котлов.
    }
};
