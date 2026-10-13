<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_SKU = 'KOTLOV-000726';

    private const DUPLICATE_SKU = 'PS-000.18762';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        $canonical = DB::table('products')->where('sku', self::CANONICAL_SKU)->first();
        $duplicate = DB::table('products')->where('sku', self::DUPLICATE_SKU)->first();
        $category = DB::table('categories')->where('slug', 'elektricheskie-konvektoryi')->first();

        if (! $canonical || ! $duplicate || ! $category) {
            return;
        }

        DB::transaction(function () use ($canonical, $duplicate, $category): void {
            $now = now();

            if (Schema::hasTable('integration_products')) {
                DB::table('integration_products')
                    ->where('product_id', $duplicate->id)
                    ->update([
                        'product_id' => $canonical->id,
                        'match_status' => 'matched',
                        'match_method' => 'manual_search',
                        'matched_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            if (Schema::hasTable('supplier_product_mappings')) {
                DB::table('supplier_product_mappings')
                    ->where('product_id', $duplicate->id)
                    ->update([
                        'product_id' => $canonical->id,
                        'product_sku' => self::CANONICAL_SKU,
                        'updated_at' => $now,
                    ]);
            }

            $availableStock = $this->availableStock($canonical->id);

            DB::table('products')->where('id', $canonical->id)->update([
                'category_id' => $category->id,
                'brand_id' => $duplicate->brand_id ?: $canonical->brand_id,
                'name' => 'Комплект шасси Electrolux Rapid Transformer System EFT/RR',
                'h1' => 'Комплект шасси Electrolux Rapid Transformer System EFT/RR',
                'price' => 33.00,
                'price_old' => null,
                'short_description' => $duplicate->short_description,
                'content' => $duplicate->content,
                'images' => $duplicate->images,
                'specs' => $duplicate->specs,
                'content_source_url' => $duplicate->content_source_url,
                'meta_title' => 'Комплект шасси Electrolux EFT/RR — купить в Беларуси | KOTLOV',
                'meta_description' => 'Комплект колёсных опор Electrolux EFT/RR для напольной установки конвекторов Rapid Transformer System.',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => $availableStock > 0,
                'stock_qty' => $availableStock > 0 ? $availableStock : null,
                'availability_status' => $availableStock > 0 ? 'in_stock' : 'check',
                'updated_at' => $now,
            ]);

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

            $canonicalPath = '/elektricheskie-konvektoryi/'.$canonical->slug;
            $duplicateCategory = DB::table('categories')->where('id', $duplicate->category_id)->value('slug');
            $paths = [
                '/obogrevateli/'.$canonical->slug,
                $duplicateCategory && $duplicate->slug ? '/'.$duplicateCategory.'/'.$duplicate->slug : null,
            ];

            foreach (array_unique(array_filter($paths)) as $fromPath) {
                if ($fromPath === $canonicalPath) {
                    continue;
                }

                DB::table('redirects')->updateOrInsert(
                    ['from_url' => $fromPath],
                    [
                        'to_url' => $canonicalPath,
                        'status_code' => 301,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        });
    }

    private function availableStock(int $productId): float
    {
        $legacyStock = 0.0;
        if (Schema::hasTable('supplier_products')) {
            $legacyStock = (float) DB::table('supplier_products')
                ->where('product_id', $productId)
                ->where(function ($query): void {
                    $query->where('in_stock', true)->orWhere('stock_quantity', '>', 0);
                })
                ->max('stock_quantity');
        }

        $integrationStock = 0.0;
        if (Schema::hasTable('integration_products') && Schema::hasTable('integration_sources')) {
            $integrationStock = (float) DB::table('integration_products as ip')
                ->join('integration_sources as src', 'src.id', '=', 'ip.integration_source_id')
                ->where('ip.product_id', $productId)
                ->where('ip.match_status', 'matched')
                ->where('src.is_active', true)
                ->where('ip.stock_quantity', '>', 0)
                ->max('ip.stock_quantity');
        }

        return max($legacyStock, $integrationStock);
    }

    public function down(): void
    {
        // The duplicate contained a misleading product identity and must not be restored.
    }
};
