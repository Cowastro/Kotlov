<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_SKU = 'PS-011.178';

    private const DUPLICATE_SKU = 'PS-012.178';

    private const MODEL = 'NL-FLM30-100II/R290';

    private const ONE_C_ARTICLE = 'УТ-00009643';

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $canonical = DB::table('products')->where('sku', self::CANONICAL_SKU)->first();
        $duplicate = DB::table('products')->where('sku', self::DUPLICATE_SKU)->first();

        if (! $canonical || ! $duplicate
            || ! str_contains(mb_strtoupper((string) $canonical->name), self::MODEL)
            || ! str_contains(mb_strtoupper((string) $duplicate->name), self::MODEL)) {
            return;
        }

        DB::transaction(function () use ($canonical, $duplicate): void {
            $now = now();

            foreach (['integration_products', 'supplier_products', 'supplier_product_mappings'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $update = ['product_id' => $canonical->id, 'updated_at' => $now];
                if (Schema::hasColumn($table, 'product_sku')) {
                    $update['product_sku'] = self::CANONICAL_SKU;
                }
                if ($table === 'integration_products') {
                    $update['match_status'] = 'matched';
                    $update['match_method'] = 'manual_duplicate_merge';
                    $update['matched_at'] = $now;
                }

                DB::table($table)->where('product_id', $duplicate->id)->update($update);
            }

            if (Schema::hasTable('integration_products') && Schema::hasTable('integration_sources')) {
                $sourceIds = DB::table('integration_sources')
                    ->where('name', '1С СанБизнесГруп')
                    ->orWhere('code', 'onec')
                    ->pluck('id');

                DB::table('integration_products')
                    ->whereIn('integration_source_id', $sourceIds)
                    ->where(function ($query): void {
                        $query->where('external_id', self::ONE_C_ARTICLE)
                            ->orWhere('external_sku', self::ONE_C_ARTICLE);
                    })
                    ->update([
                        'product_id' => $canonical->id,
                        'match_status' => 'matched',
                        'match_method' => 'manual_user_confirmed',
                        'match_confidence' => 1,
                        'candidates' => null,
                        'matched_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('product_warehouse_stocks')) {
                $stocks = DB::table('product_warehouse_stocks')->where('product_id', $duplicate->id)->get();
                foreach ($stocks as $stock) {
                    $existing = DB::table('product_warehouse_stocks')
                        ->where('product_id', $canonical->id)
                        ->where('supplier_id', $stock->supplier_id)
                        ->where('warehouse_code', $stock->warehouse_code)
                        ->first();

                    if (! $existing) {
                        DB::table('product_warehouse_stocks')->where('id', $stock->id)->update([
                            'product_id' => $canonical->id,
                            'updated_at' => $now,
                        ]);

                        continue;
                    }

                    DB::table('product_warehouse_stocks')->where('id', $existing->id)->update([
                        'total_quantity' => max((float) $existing->total_quantity, (float) $stock->total_quantity),
                        'reserved_quantity' => max((float) $existing->reserved_quantity, (float) $stock->reserved_quantity),
                        'shipping_quantity' => max((float) $existing->shipping_quantity, (float) $stock->shipping_quantity),
                        'available_quantity' => max((float) $existing->available_quantity, (float) $stock->available_quantity),
                        'updated_at' => $now,
                    ]);
                    DB::table('product_warehouse_stocks')->where('id', $stock->id)->delete();
                }
            }

            if (Schema::hasTable('reviews')) {
                DB::table('reviews')
                    ->where('reviewable_type', 'App\\Models\\Product')
                    ->where('reviewable_id', $duplicate->id)
                    ->update(['reviewable_id' => $canonical->id, 'updated_at' => $now]);
            }

            if (Schema::hasTable('wishlist_items')) {
                $wishlistRows = DB::table('wishlist_items')->where('product_id', $duplicate->id)->get();
                foreach ($wishlistRows as $row) {
                    DB::table('wishlist_items')->insertOrIgnore([
                        'product_id' => $canonical->id,
                        'user_id' => $row->user_id,
                        'session_id' => $row->session_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $now,
                    ]);
                }
                DB::table('wishlist_items')->where('product_id', $duplicate->id)->delete();
            }
            if (Schema::hasTable('compare_items')) {
                $compareRows = DB::table('compare_items')->where('product_id', $duplicate->id)->get();
                foreach ($compareRows as $row) {
                    DB::table('compare_items')->insert([
                        'product_id' => $canonical->id,
                        'user_id' => $row->user_id,
                        'session_id' => $row->session_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $now,
                    ]);
                }
                DB::table('compare_items')->where('product_id', $duplicate->id)->delete();
            }

            DB::table('products')->where('id', $duplicate->id)->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'stock_qty' => null,
                'availability_status' => 'out_of_stock',
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('redirects')) {
                return;
            }

            $canonicalCategory = DB::table('categories')->where('id', $canonical->category_id)->value('slug');
            $duplicateCategory = DB::table('categories')->where('id', $duplicate->category_id)->value('slug');
            if ($canonicalCategory && $duplicateCategory && $canonical->slug && $duplicate->slug) {
                DB::table('redirects')->updateOrInsert(
                    ['from_url' => '/'.$duplicateCategory.'/'.$duplicate->slug],
                    [
                        'to_url' => '/'.$canonicalCategory.'/'.$canonical->slug,
                        'status_code' => 301,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        // The duplicate represented the same physical model and must stay archived.
    }
};
