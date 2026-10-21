<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlignMetabelTsarPechiSanBusinessGroupCommand extends Command
{
    protected $signature = 'supplier:align-metabel-tsarpechi-sbg
        {--apply : Persist the verified supplier links}';

    protected $description = 'Assign verified Meta-Bel and Tsar-Pechi catalogue routes to ООО «СанБизнесГруп».';

    /** Products matched manually to the SanBusinessGroup Tsar-Pechi retail price list. */
    private const TSAR_VERIFIED_PRODUCT_IDS = [
        9906, 5312, 9907, 5313, 5887, 9908, 9945, 11982, 11981, 9946,
        5888, 9947, 9948, 9949, 9950, 9951, 9952, 9953, 9954, 9958,
        9959, 9960, 9961, 9962, 9963, 9964, 9965, 9955, 9957, 9929,
        9930, 9931, 9932, 5624, 5797, 9933, 9935, 9934, 9936, 9937,
        5321, 9938, 7224, 8881, 9939, 9940, 12112, 12111,
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('supplier_products') || ! Schema::hasTable('brand_supplier')) {
            $this->error('Required supplier catalogue tables are missing.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $sbg = DB::table('suppliers')
            ->where(fn ($query) => $query
                ->where('code', 'sanbusinessgroup')
                ->orWhere('name', 'like', '%СанБизнесГруп%'))
            ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
            ->first();

        if (! $sbg) {
            $this->error('ООО «СанБизнесГруп» не найден в справочнике поставщиков.');

            return self::FAILURE;
        }

        $metaBrand = DB::table('brands')
            ->where(fn ($query) => $query
                ->whereIn('slug', ['meta-bel', 'metabel'])
                ->orWhereIn('name', ['Мета-Бел', 'МЕТА-БЕЛ']))
            ->orderByRaw("CASE WHEN slug = 'metabel' THEN 0 ELSE 1 END")
            ->first();
        $tsarBrand = DB::table('brands')
            ->where(fn ($query) => $query
                ->where('slug', 'tsar-pechi')
                ->orWhereIn('name', ['Царь-Печи', 'Царь Печи']))
            ->orderByRaw("CASE WHEN slug = 'tsar-pechi' THEN 0 ELSE 1 END")
            ->first();
        $legacyMetaSupplier = DB::table('suppliers')->where('code', 'metabel')->first();

        $metrics = [
            'sbg_active' => (bool) $sbg->is_active ? 1 : 0,
            'metabel_products' => $metaBrand ? DB::table('products')->where('brand_id', $metaBrand->id)->count() : 0,
            'metabel_links_to_move' => 0,
            'metabel_links_to_verify' => 0,
            'tsar_verified_products' => 0,
            'tsar_links_to_create' => 0,
            'brand_links_to_create' => 0,
            'links_written' => 0,
        ];

        $metaLinks = collect();
        if ($metaBrand && $legacyMetaSupplier) {
            $metaLinks = DB::table('supplier_products')
                ->join('products', 'products.id', '=', 'supplier_products.product_id')
                ->where('products.brand_id', $metaBrand->id)
                ->where('supplier_products.supplier_id', $legacyMetaSupplier->id)
                ->select('supplier_products.*')
                ->get();
            $metrics['metabel_links_to_move'] = $metaLinks->count();
        }
        if ($metaBrand) {
            $metrics['metabel_links_to_verify'] = DB::table('supplier_products')
                ->join('products', 'products.id', '=', 'supplier_products.product_id')
                ->where('products.brand_id', $metaBrand->id)
                ->where('supplier_products.supplier_id', $sbg->id)
                ->where('supplier_products.match_confidence', '!=', 'manual_price_list')
                ->count();
        }

        $tsarProducts = $tsarBrand
            ? DB::table('products')
                ->where('brand_id', $tsarBrand->id)
                ->whereIn('id', self::TSAR_VERIFIED_PRODUCT_IDS)
                ->get(['id', 'sku', 'name', 'content_source_url'])
            : collect();
        $metrics['tsar_verified_products'] = $tsarProducts->count();
        $metrics['tsar_links_to_create'] = $tsarProducts->filter(function (object $product) use ($sbg): bool {
            return ! DB::table('supplier_products')
                ->where('supplier_id', $sbg->id)
                ->where('product_id', $product->id)
                ->exists();
        })->count();

        foreach ([$metaBrand, $tsarBrand] as $brand) {
            if ($brand && ! DB::table('brand_supplier')->where('brand_id', $brand->id)->where('supplier_id', $sbg->id)->exists()) {
                $metrics['brand_links_to_create']++;
            }
        }

        if ($apply) {
            DB::transaction(function () use ($sbg, $metaBrand, $tsarBrand, $legacyMetaSupplier, $metaLinks, $tsarProducts, &$metrics): void {
                $now = now();
                DB::table('suppliers')->where('id', $sbg->id)->update([
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                foreach ([$metaBrand, $tsarBrand] as $brand) {
                    if ($brand) {
                        DB::table('brand_supplier')->updateOrInsert(
                            ['brand_id' => $brand->id, 'supplier_id' => $sbg->id],
                            ['created_at' => $now, 'updated_at' => $now],
                        );
                    }
                }

                if ($metaBrand && Schema::hasTable('supplier_syncs')) {
                    DB::table('supplier_syncs')->where('key', 'metabel_price')->update([
                        'supplier_id' => $sbg->id,
                        'name' => 'ООО «СанБизнесГруп»',
                        'code' => 'sanbusinessgroup',
                        'title' => 'СанБизнесГруп: Мета-Бел — цены и карточки',
                        'updated_at' => $now,
                    ]);
                }

                foreach ($metaLinks as $link) {
                    $payload = (array) $link;
                    unset($payload['id'], $payload['supplier_id'], $payload['created_at']);
                    $payload['updated_at'] = $now;
                    $payload['match_confidence'] = 'manual_price_list';

                    DB::table('supplier_products')->updateOrInsert(
                        ['supplier_id' => $sbg->id, 'supplier_article' => $link->supplier_article],
                        array_merge($payload, ['created_at' => $now]),
                    );
                    DB::table('supplier_products')->where('id', $link->id)->delete();
                    $metrics['links_written']++;
                }

                if ($metaBrand) {
                    $metaProductIds = DB::table('products')->where('brand_id', $metaBrand->id)->pluck('id');
                    DB::table('supplier_products')
                        ->where('supplier_id', $sbg->id)
                        ->whereIn('product_id', $metaProductIds)
                        ->update([
                            'match_confidence' => 'manual_price_list',
                            'updated_at' => $now,
                        ]);
                }

                foreach ($tsarProducts as $product) {
                    $article = 'TSAR-PECHI-'.($product->sku ?: $product->id);
                    $payload = [
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'supplier_name' => $product->name,
                        'source_url' => $product->content_source_url,
                        'price' => null,
                        'currency' => 'BYN',
                        'in_stock' => false,
                        'match_status' => 'matched',
                        'match_confidence' => 'manual_price_list',
                        'raw' => json_encode([
                            'brand' => 'Царь-Печи',
                            'supplier' => 'ООО «СанБизнесГруп»',
                            'price_list' => 'Прайс_Царь-печи_31.08.2026.xlsx',
                            'purchase_price_status' => 'по запросу',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'last_synced_at' => $now,
                        'updated_at' => $now,
                    ];
                    foreach ([
                        'supplier_article_normalized' => $article,
                        'supplier_article_compact' => str_replace('-', '', $article),
                        'price_byn' => null,
                        'stock_quantity' => null,
                        'stock_status' => 'preorder',
                        'stock_text' => 'Наличие и закупочная цена по запросу у ООО «СанБизнесГруп»',
                        'warehouse_name' => 'ООО «СанБизнесГруп»',
                    ] as $column => $value) {
                        if (Schema::hasColumn('supplier_products', $column)) {
                            $payload[$column] = $value;
                        }
                    }

                    DB::table('supplier_products')->updateOrInsert(
                        ['supplier_id' => $sbg->id, 'supplier_article' => $article],
                        array_merge($payload, ['created_at' => $now]),
                    );
                    $metrics['links_written']++;
                }

                if ($legacyMetaSupplier && (int) $legacyMetaSupplier->id !== (int) $sbg->id
                    && ! DB::table('supplier_products')->where('supplier_id', $legacyMetaSupplier->id)->exists()) {
                    DB::table('suppliers')->where('id', $legacyMetaSupplier->id)->update([
                        'is_active' => false,
                        'notes' => 'Мета-Бел — бренд. Фактический поставщик карточек — ООО «СанБизнесГруп».',
                        'updated_at' => $now,
                    ]);
                }
            });

            Cache::forget('home:data');
            Cache::forget('home:data:v4');
        }

        foreach (['metabel' => $metaBrand, 'tsar' => $tsarBrand] as $key => $brand) {
            $products = $brand
                ? Product::query()
                    ->where('brand_id', $brand->id)
                    ->where('is_active', true)
                    ->where('is_archived', false)
                    ->with([
                        'supplierProducts.supplier.latestChannelTransition',
                        'integrationProducts.source.supplier',
                        'purchasePriceSources.supplier',
                    ])
                    ->get()
                : collect();
            $metrics[$key.'_active_products'] = $products->count();
            $metrics[$key.'_operational_products'] = $products->filter(
                fn (Product $product): bool => $product->hasAssignedSupplier()
            )->count();
        }

        $this->line('metric.status='.($apply ? 'applied' : 'dry_run'));
        foreach ($metrics as $name => $value) {
            $this->line("metric.{$name}={$value}");
        }

        return self::SUCCESS;
    }
}
