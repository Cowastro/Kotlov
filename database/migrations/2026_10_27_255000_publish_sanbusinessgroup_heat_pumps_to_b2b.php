<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $source = DB::table('integration_sources')->where('code', 'onec')->first();
        if (! $source) {
            return;
        }

        $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];
        $settings['b2b_enabled'] = true;
        $settings['b2b_price_role'] = 'sale';
        $settings['partner_name'] = 'ООО «СанБизнесГруп»';

        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        $categoryIds = DB::table('categories')
            ->whereIn('slug', [
                'teplovyie-nasosyi',
                'teplovye-nasosy-vozduh-voda',
                'geotermalnye-teplovye-nasosy',
                'teplovye-nasosy-dlya-basseynov',
                'komplektuyushchie-dlya-teplovykh-nasosov',
            ])
            ->pluck('id');

        $productIds = DB::table('products')
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
            ->pluck('id');

        DB::table('integration_products')
            ->where('integration_source_id', $source->id)
            ->where('match_status', 'matched')
            ->whereIn('product_id', $productIds)
            ->update([
                'b2b_enabled' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Настроенные операционные B2B-цены не откатываем автоматически.
    }
};
