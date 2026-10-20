<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'ecokamin';
    private const SUPPLIER_NAME = 'ООО «СанБизнесГруп»';

    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        $now = now();
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if (! $supplier) {
            return;
        }

        DB::table('suppliers')->where('id', $supplier->id)->update([
            'name' => self::SUPPLIER_NAME,
            'notes' => 'Фактический поставщик и импортёр продукции ЭкоКамин — ООО «СанБизнесГруп». Сайт ecokamin.ru используется как источник карточек и розничных цен; это не закупочный прайс.',
            'updated_at' => $now,
        ]);

        if (Schema::hasTable('supplier_syncs')) {
            $syncs = [
                'ecokamin_fireboxes' => [
                    'title' => 'СанБизнесГруп: ЭкоКамин — каминные топки',
                    'description' => 'Обновляет карточки и розничные цены продукции ЭкоКамин с ecokamin.ru. Фактический поставщик и импортёр — ООО «СанБизнесГруп».',
                ],
                'ecokamin_stoves' => [
                    'title' => 'СанБизнесГруп: ЭкоКамин — печи-камины',
                    'description' => 'Обновляет карточки и розничные цены продукции ЭкоКамин с ecokamin.ru. Фактический поставщик и импортёр — ООО «СанБизнесГруп».',
                ],
            ];

            foreach ($syncs as $key => $copy) {
                $update = [
                    'name' => self::SUPPLIER_NAME,
                    'title' => $copy['title'],
                    'description' => $copy['description'],
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('supplier_syncs', 'supplier_id')) {
                    $update['supplier_id'] = $supplier->id;
                }

                DB::table('supplier_syncs')->where('key', $key)->update($update);
            }
        }

        if (Schema::hasTable('brand_supplier') && Schema::hasTable('brands')) {
            $brandId = DB::table('brands')->where('slug', 'ecokamin')->value('id');

            if ($brandId) {
                DB::table('brand_supplier')->updateOrInsert(
                    ['brand_id' => $brandId, 'supplier_id' => $supplier->id],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        if (Schema::hasTable('order_items')
            && Schema::hasColumn('order_items', 'supply_supplier_id')
            && Schema::hasColumn('order_items', 'supply_supplier_name')) {
            DB::table('order_items')
                ->where('supply_supplier_id', $supplier->id)
                ->update(['supply_supplier_name' => self::SUPPLIER_NAME]);
        }

        if (Schema::hasTable('order_items')
            && Schema::hasColumn('order_items', 'fulfillment_supplier_id')
            && Schema::hasColumn('order_items', 'fulfillment_supplier_name')) {
            DB::table('order_items')
                ->where('fulfillment_supplier_id', $supplier->id)
                ->update(['fulfillment_supplier_name' => self::SUPPLIER_NAME]);
        }
    }

    public function down(): void
    {
        // This corrects the real legal supplier and must not be rolled back.
    }
};
