<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SKU = 'KOTLOV-008072';

    private const PRODUCT_SLUG = 'teplov-i-suxov-kreplenie-podves-700-mc-black-t-kraski-200s';

    private const OLD_CATEGORY_SLUG = 'zaglushki-dymohod';

    private const NEW_CATEGORY_SLUG = 'krepleniya-dymohod';

    public function up(): void
    {
        $targetCategoryId = DB::table('categories')
            ->where('slug', self::NEW_CATEGORY_SLUG)
            ->value('id');

        if (! $targetCategoryId) {
            return;
        }

        DB::table('products')
            ->where(function ($query): void {
                $query->where('sku', self::PRODUCT_SKU)
                    ->orWhere('slug', self::PRODUCT_SLUG);
            })
            ->update([
                'category_id' => $targetCategoryId,
                'updated_at' => now(),
            ]);

        DB::table('redirects')->updateOrInsert(
            ['from_url' => '/'.self::OLD_CATEGORY_SLUG.'/'.self::PRODUCT_SLUG],
            [
                'to_url' => '/'.self::NEW_CATEGORY_SLUG.'/'.self::PRODUCT_SLUG,
                'status_code' => 301,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $oldCategoryId = DB::table('categories')
            ->where('slug', self::OLD_CATEGORY_SLUG)
            ->value('id');
        $newCategoryId = DB::table('categories')
            ->where('slug', self::NEW_CATEGORY_SLUG)
            ->value('id');

        if ($oldCategoryId && $newCategoryId) {
            DB::table('products')
                ->where(function ($query): void {
                    $query->where('sku', self::PRODUCT_SKU)
                        ->orWhere('slug', self::PRODUCT_SLUG);
                })
                ->where('category_id', $newCategoryId)
                ->update([
                    'category_id' => $oldCategoryId,
                    'updated_at' => now(),
                ]);
        }

        DB::table('redirects')
            ->where('from_url', '/'.self::OLD_CATEGORY_SLUG.'/'.self::PRODUCT_SLUG)
            ->where('to_url', '/'.self::NEW_CATEGORY_SLUG.'/'.self::PRODUCT_SLUG)
            ->delete();
    }
};
