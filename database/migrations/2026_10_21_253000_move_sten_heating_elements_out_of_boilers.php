<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brandId = DB::table('brands')
            ->whereIn('name', ['СТЭН', 'стэн', 'STEN', 'Sten'])
            ->value('id');
        $boilerCategoryIds = DB::table('categories')
            ->whereIn('slug', ['kotly', 'elektricheskie'])
            ->pluck('id');
        $heatingElementsId = DB::table('categories')
            ->where('slug', 'elektricheskie-teny-dlya-otopleniya')
            ->value('id');

        if (! $brandId || $boilerCategoryIds->isEmpty() || ! $heatingElementsId) {
            return;
        }

        DB::table('products')
            ->where('brand_id', $brandId)
            ->whereIn('category_id', $boilerCategoryIds)
            ->where(function ($query): void {
                $query->where('name', 'like', '%ТЭНБ%')
                    ->orWhere('name', 'like', '%тэнб%')
                    ->orWhere('name', 'like', '%ТНБР%')
                    ->orWhere('name', 'like', '%тнбр%');
            })
            ->update([
                'category_id' => $heatingElementsId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Не возвращаем ТЭНы СТЭН в заведомо неверный раздел котлов.
    }
};
