<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlignKotlovSanBusinessGroupCommand extends Command
{
    protected $signature = 'supplier:align-kotlov-sbg
        {--apply : Persist the supplier links and release eligible product cards}';

    protected $description = 'Assign the KOTLOV brand to ООО «СанБизнесГруп» and release priced active cards for ordering.';

    public function handle(): int
    {
        if (! Schema::hasTable('supplier_products') || ! Schema::hasTable('brand_supplier')) {
            $this->error('Required supplier catalogue tables are missing.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $supplier = DB::table('suppliers')
            ->where(fn ($query) => $query
                ->where('code', 'sanbusinessgroup')
                ->orWhere('name', 'like', '%СанБизнесГруп%'))
            ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
            ->first();
        $brands = DB::table('brands')
            ->where(fn ($query) => $query
                ->whereIn('slug', ['kotlov', 'kotlov-by'])
                ->orWhereRaw('UPPER(name) LIKE ?', ['KOTLOV%']))
            ->orderByRaw("CASE WHEN slug = 'kotlov' THEN 0 WHEN name = 'KOTLOV' THEN 1 ELSE 2 END")
            ->get();

        if (! $supplier) {
            $this->error('ООО «СанБизнесГруп» не найден в справочнике поставщиков.');

            return self::FAILURE;
        }
        if ($brands->isEmpty()) {
            $this->error('Бренды семейства KOTLOV не найдены.');

            return self::FAILURE;
        }

        $brandIds = $brands->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $products = DB::table('products')
            ->whereIn('brand_id', $brandIds)
            ->get(['id', 'sku', 'name', 'is_active', 'is_archived', 'price', 'availability_status', 'requires_price_confirmation']);
        $linkedProductIds = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $eligible = $products->filter(fn (object $product): bool => (bool) $product->is_active
            && ! (bool) $product->is_archived
            && (float) $product->price > 0);

        $metrics = [
            'brand_id' => (int) $brands->first()->id,
            'brand_count' => $brands->count(),
            'supplier_id' => (int) $supplier->id,
            'brand_products' => $products->count(),
            'existing_supplier_links' => $linkedProductIds->count(),
            'supplier_links_to_create' => $products->count() - $linkedProductIds->count(),
            'eligible_to_release' => $eligible->count(),
            'price_holds_to_release' => $eligible->where('requires_price_confirmation', true)->count(),
            'links_written' => 0,
            'products_released' => 0,
        ];

        if ($apply) {
            DB::transaction(function () use ($supplier, $brands, $products, $eligible, &$metrics): void {
                $now = now();
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
                foreach ($brands as $brand) {
                    DB::table('brand_supplier')->updateOrInsert(
                        ['brand_id' => $brand->id, 'supplier_id' => $supplier->id],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                }

                foreach ($products as $product) {
                    $existing = DB::table('supplier_products')
                        ->where('supplier_id', $supplier->id)
                        ->where('product_id', $product->id)
                        ->orderBy('id')
                        ->first();
                    $article = 'KOTLOV-BRAND-'.($product->sku ?: $product->id);
                    $payload = [
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'supplier_name' => $product->name,
                        'currency' => 'BYN',
                        'in_stock' => false,
                        'match_status' => 'matched',
                        'match_confidence' => 'manual_price_list',
                        'raw' => json_encode([
                            'brand' => 'KOTLOV',
                            'supplier' => 'ООО «СанБизнесГруп»',
                            'route' => 'owner_brand',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'last_synced_at' => $now,
                        'updated_at' => $now,
                    ];
                    foreach ([
                        'stock_quantity' => null,
                        'stock_status' => 'preorder',
                        'stock_text' => 'Доступно к заказу у ООО «СанБизнесГруп»',
                        'warehouse_name' => 'ООО «СанБизнесГруп»',
                    ] as $column => $value) {
                        if (Schema::hasColumn('supplier_products', $column)) {
                            $payload[$column] = $value;
                        }
                    }

                    if ($existing) {
                        DB::table('supplier_products')->where('id', $existing->id)->update($payload);
                    } else {
                        $payload = array_merge($payload, [
                            'supplier_id' => $supplier->id,
                            'supplier_article' => $article,
                            'created_at' => $now,
                        ]);
                        if (Schema::hasColumn('supplier_products', 'supplier_article_normalized')) {
                            $payload['supplier_article_normalized'] = $article;
                        }
                        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
                            $payload['supplier_article_compact'] = str_replace('-', '', $article);
                        }

                        DB::table('supplier_products')->insert($payload);
                    }
                    $metrics['links_written']++;
                }

                foreach ($eligible as $product) {
                    $changes = [
                        'requires_price_confirmation' => false,
                        'updated_at' => $now,
                    ];
                    if (! in_array($product->availability_status, [Product::AVAILABILITY_IN_STOCK, Product::AVAILABILITY_CHECK], true)) {
                        $changes['availability_status'] = Product::AVAILABILITY_CHECK;
                    }

                    DB::table('products')->where('id', $product->id)->update($changes);
                    $metrics['products_released']++;
                }
            });

            Cache::forget('home:data');
            Cache::forget('home:data:v6');
        }

        $metrics['operational_products'] = Product::query()
            ->whereIn('brand_id', $brandIds)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'supplierProducts.supplier.latestChannelTransition',
                'integrationProducts.source.supplier',
                'purchasePriceSources.supplier',
            ])
            ->get()
            ->filter(fn (Product $product): bool => $product->hasAssignedSupplier())
            ->count();

        $this->line('metric.status='.($apply ? 'applied' : 'dry_run'));
        foreach ($metrics as $name => $value) {
            $this->line("metric.{$name}={$value}");
        }

        return self::SUCCESS;
    }
}
