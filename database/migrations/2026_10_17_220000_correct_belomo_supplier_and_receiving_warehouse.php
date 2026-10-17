<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $supplierId = DB::table('suppliers')->where('code', 'belomo')->value('id');
            if (! $supplierId) {
                $supplierId = DB::table('suppliers')->insertGetId([
                    'code' => 'belomo', 'name' => 'БелОМО', 'currency' => 'BYN',
                    'currency_rate' => 1, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $sync = DB::table('supplier_syncs')->where('key', 'belomo_static_pricelist')->first();
            if ($sync) {
                // Only the direct BelOMO price list; never move 1C stock or other suppliers.
                foreach (DB::table('supplier_products')->where('supplier_sync_id', $sync->id)->get() as $offer) {
                    $conflict = DB::table('supplier_products')->where('supplier_id', $supplierId)
                        ->where('supplier_article', $offer->supplier_article)->where('id', '!=', $offer->id)->exists();
                    if ($conflict) {
                        throw new RuntimeException('Duplicate BelOMO supplier article; manual reconciliation required.');
                    }
                    DB::table('supplier_products')->where('id', $offer->id)->update([
                        'supplier_id' => $supplierId,
                        'warehouse_name' => 'СанБизнесГруп · ожидается поступление',
                    ]);
                    $product = DB::table('products')->where('id', $offer->product_id)->first();
                    if ($product && str_contains((string) $product->content, 'Карточка привязана к закупочному прайсу ООО «СанБизнесГруп».')) {
                        DB::table('products')->where('id', $product->id)->update([
                            'content' => str_replace(
                                'Карточка привязана к закупочному прайсу ООО «СанБизнесГруп».',
                                'Поставщик — БелОМО; склад поступления — ООО «СанБизнесГруп».',
                                $product->content,
                            ),
                        ]);
                    }
                }
                DB::table('supplier_syncs')->where('id', $sync->id)->update([
                    'name' => 'БелОМО', 'code' => 'belomo', 'title' => 'БелОМО: оптовый прайс счетчиков',
                ]);
            }

            $oldSupplierId = DB::table('suppliers')->where('code', 'sanbusinessgroup')->value('id');
            if ($oldSupplierId) {
                DB::table('supplier_sources')->where('supplier_id', $oldSupplierId)
                    ->where('name', 'Прайс БелОМО 15.09.2026')->update(['supplier_id' => $supplierId]);
            }

            $source = DB::table('integration_sources')->where('code', 'belomo_wholesale')->first();
            if ($source) {
                $settings = json_decode($source->settings ?? '{}', true) ?: [];
                $settings['partner_name'] = 'БелОМО';
                $settings['warehouse_label'] = 'СанБизнесГруп · ожидается поступление';
                DB::table('integration_sources')->where('id', $source->id)->update([
                    'supplier_id' => $supplierId, 'name' => 'БелОМО · оптовые счетчики',
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        // A factual supplier correction must not restore the wrong supplier.
    }
};
