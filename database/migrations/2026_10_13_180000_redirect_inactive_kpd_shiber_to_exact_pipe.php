<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_SKU = 'KOTLOV-022436';

    private const LEGACY_SLUG = 'kpd-kpd-cernyi-siber-1000mm-2mm-f120';

    private const LEGACY_CATEGORY = 'dymohody-nerzhaveyushchie';

    private const CANONICAL_SKU = 'KOTLOV-022646';

    private const CANONICAL_SLUG = 'kpd-cernyi-truba-dymoxodnaia-1000-mm-o120-2-mm';

    private const CANONICAL_CATEGORY = 'truby-mono';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories') || ! Schema::hasTable('redirects')) {
            return;
        }

        $legacy = DB::table('products')->where('sku', self::LEGACY_SKU)->first();
        $canonical = DB::table('products')->where('sku', self::CANONICAL_SKU)->first();
        if (! $legacy || ! $canonical
            || $legacy->slug !== self::LEGACY_SLUG
            || $canonical->slug !== self::CANONICAL_SLUG) {
            return;
        }

        $legacyCategory = DB::table('categories')->where('id', $legacy->category_id)->value('slug');
        $canonicalCategory = DB::table('categories')->where('id', $canonical->category_id)->value('slug');
        if ($legacyCategory !== self::LEGACY_CATEGORY || $canonicalCategory !== self::CANONICAL_CATEGORY) {
            return;
        }

        DB::transaction(function () use ($legacy): void {
            $now = now();

            DB::table('products')->where('id', $legacy->id)->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'availability_status' => 'out_of_stock',
                'stock_qty' => null,
                'updated_at' => $now,
            ]);

            DB::table('redirects')->updateOrInsert(
                ['from_url' => '/'.self::LEGACY_CATEGORY.'/'.self::LEGACY_SLUG],
                [
                    'to_url' => '/'.self::CANONICAL_CATEGORY.'/'.self::CANONICAL_SLUG,
                    'status_code' => 301,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            DB::table('products')->where('sku', self::LEGACY_SKU)->update([
                'is_active' => false,
                'is_archived' => false,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('redirects')) {
            DB::table('redirects')
                ->where('from_url', '/'.self::LEGACY_CATEGORY.'/'.self::LEGACY_SLUG)
                ->where('to_url', '/'.self::CANONICAL_CATEGORY.'/'.self::CANONICAL_SLUG)
                ->delete();
        }
    }
};
