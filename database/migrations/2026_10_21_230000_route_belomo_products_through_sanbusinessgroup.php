<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || ! Schema::hasTable('supplier_products')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $sbg = DB::table('suppliers')
                ->where(fn ($query) => $query
                    ->where('code', 'sanbusinessgroup')
                    ->orWhere('name', 'like', '%СанБизнесГруп%'))
                ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
                ->first();
            $belomo = DB::table('suppliers')->where('code', 'belomo')->first();

            if (! $sbg) {
                return;
            }

            DB::table('suppliers')->where('id', $sbg->id)->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);

            $brandIds = Schema::hasTable('brands')
                ? DB::table('brands')
                    ->where(fn ($query) => $query
                        ->where('slug', 'belomo')
                        ->orWhereRaw('LOWER(name) = ?', ['беломо']))
                    ->pluck('id')
                : collect();

            if ($belomo && (int) $belomo->id !== (int) $sbg->id) {
                $links = DB::table('supplier_products')
                    ->when($brandIds->isNotEmpty(), function ($query) use ($belomo, $brandIds): void {
                        $query->where('supplier_id', $belomo->id)
                            ->whereIn('product_id', DB::table('products')->whereIn('brand_id', $brandIds)->select('id'));
                    }, fn ($query) => $query->whereRaw('1 = 0'))
                    ->get();

                foreach ($links as $link) {
                    $existing = DB::table('supplier_products')
                        ->where('supplier_id', $sbg->id)
                        ->where('supplier_article', $link->supplier_article)
                        ->where('id', '!=', $link->id)
                        ->first();

                    if ($existing) {
                        $legacyRaw = json_decode((string) ($link->raw ?? ''), true) ?: [];
                        $currentRaw = json_decode((string) ($existing->raw ?? ''), true) ?: [];
                        DB::table('supplier_products')->where('id', $existing->id)->update([
                            'supplier_sync_id' => $existing->supplier_sync_id ?: $link->supplier_sync_id,
                            'product_id' => $existing->product_id ?: $link->product_id,
                            'product_sku' => $existing->product_sku ?: $link->product_sku,
                            'price' => $existing->price ?? $link->price,
                            'price_byn' => $existing->price_byn ?? $link->price_byn,
                            'warehouse_name' => 'ООО «СанБизнесГруп»',
                            'raw' => json_encode(array_merge($legacyRaw, $currentRaw, [
                                'manufacturer' => 'БелОМО',
                                'procurement_source' => 'БелОМО',
                                'supplier' => 'ООО «СанБизнесГруп»',
                            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => $now,
                        ]);
                        DB::table('supplier_products')->where('id', $link->id)->delete();
                    } else {
                        $raw = json_decode((string) ($link->raw ?? ''), true) ?: [];
                        DB::table('supplier_products')->where('id', $link->id)->update([
                            'supplier_id' => $sbg->id,
                            'warehouse_name' => 'ООО «СанБизнесГруп»',
                            'raw' => json_encode(array_merge($raw, [
                                'manufacturer' => 'БелОМО',
                                'procurement_source' => 'БелОМО',
                                'supplier' => 'ООО «СанБизнесГруп»',
                            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('supplier_syncs')) {
                DB::table('supplier_syncs')->where('key', 'belomo_static_pricelist')->update([
                    'supplier_id' => $sbg->id,
                    'name' => 'ООО «СанБизнесГруп»',
                    'code' => 'sanbusinessgroup',
                    'title' => 'СанБизнесГруп: закупочный прайс БелОМО',
                    'description' => 'БелОМО — производитель и источник закупочной цены. Операционный поставщик и склад — ООО «СанБизнесГруп».',
                    'updated_at' => $now,
                ]);
            }

            if (Schema::hasTable('supplier_sources') && $belomo) {
                DB::table('supplier_sources')
                    ->where('supplier_id', $belomo->id)
                    ->where('name', 'like', '%БелОМО%')
                    ->update(['supplier_id' => $sbg->id, 'updated_at' => $now]);
            }

            if (Schema::hasTable('integration_sources')) {
                $source = DB::table('integration_sources')->where('code', 'belomo_wholesale')->first();
                if ($source) {
                    $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];
                    $settings = array_merge($settings, [
                        'partner_name' => 'ООО «СанБизнесГруп»',
                        'manufacturer' => 'БелОМО',
                        'procurement_source' => 'БелОМО',
                        'warehouse_label' => 'ООО «СанБизнесГруп»',
                    ]);
                    DB::table('integration_sources')->where('id', $source->id)->update([
                        'supplier_id' => $sbg->id,
                        'name' => 'СанБизнесГруп · закупочный прайс БелОМО',
                        'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('brand_supplier')) {
                foreach ($brandIds as $brandId) {
                    DB::table('brand_supplier')->updateOrInsert(
                        ['brand_id' => $brandId, 'supplier_id' => $sbg->id],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                    if ($belomo) {
                        DB::table('brand_supplier')
                            ->where('brand_id', $brandId)
                            ->where('supplier_id', $belomo->id)
                            ->delete();
                    }
                }
            }

            if ($brandIds->isNotEmpty()) {
                DB::table('products')->whereIn('brand_id', $brandIds)->orderBy('id')->each(function (object $product) use ($now): void {
                    $content = (string) ($product->content ?? '');
                    $content = str_replace(
                        ['Поставщик — БелОМО; склад поступления — ООО «СанБизнесГруп».', 'Поставщик — БелОМО; склад поступления — СанБизнесГруп.'],
                        'Поставщик и склад — ООО «СанБизнесГруп».',
                        $content,
                    );
                    if ($content !== (string) ($product->content ?? '')) {
                        DB::table('products')->where('id', $product->id)->update(['content' => $content, 'updated_at' => $now]);
                    }
                });
            }

            if ($belomo && ! DB::table('supplier_products')->where('supplier_id', $belomo->id)->exists()) {
                DB::table('suppliers')->where('id', $belomo->id)->update([
                    'is_active' => false,
                    'notes' => 'БелОМО — производитель и источник закупки. Операционный поставщик — ООО «СанБизнесГруп».',
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Factual routing correction: do not restore БелОМО as a sales supplier.
    }
};
