<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY_SLUG = 'service-products';

    private const PRODUCT_SLUG = 'webpay-control-payment';

    private const SUPPLIER_CODE = 'webpay-control';

    public function up(): void
    {
        $now = now();

        DB::table('categories')->updateOrInsert(
            ['slug' => self::CATEGORY_SLUG],
            [
                'parent_id' => 0,
                'name' => 'Служебные товары',
                'h1' => 'Служебные товары',
                'type' => 'main',
                'sort_order' => 9999,
                'is_active' => false,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => 'Контрольная оплата WEBPAY',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'notes' => 'Служебный поставщик для одного контрольного платежа WEBPAY.',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');
        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');

        DB::table('products')->updateOrInsert(
            ['slug' => self::PRODUCT_SLUG],
            [
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'name' => 'Тестовая оплата WEBPAY',
                'h1' => 'Тестовая оплата WEBPAY',
                'sku' => 'WEBPAY-TEST-1BYN',
                'price' => 1.03,
                'price_old' => null,
                'manual_purchase_price_byn' => 0.50,
                'currency' => 'BYN',
                'short_description' => 'Служебный товар для контрольной оплаты 1 BYN через WEBPAY.',
                'content' => '<p>Служебная позиция для проверки боевой интеграции WEBPAY. После скидки 3% итог составляет 1,00 BYN.</p>',
                'unit' => 'шт',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'stock_qty' => 1,
                'availability_status' => 'in_stock',
                'requires_price_confirmation' => false,
                'manual_stock_management' => true,
                'is_featured' => false,
                'is_new' => false,
                'is_sale' => false,
                'sort_order' => 9999,
                'meta_title' => 'Тестовая оплата WEBPAY',
                'meta_description' => 'Служебная карточка для контрольного платежа WEBPAY.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $productId = (int) DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');

        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'supplier_article' => 'WEBPAY-TEST-1BYN',
            ],
            [
                'product_id' => $productId,
                'product_sku' => 'WEBPAY-TEST-1BYN',
                'supplier_name' => 'Тестовая оплата WEBPAY',
                'price' => 0.50,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => 0.50,
                'in_stock' => true,
                'stock_quantity' => 1,
                'stock_status' => 'always_available',
                'stock_text' => 'Служебный тест',
                'match_status' => 'matched',
                'match_confidence' => 'manual_price_list',
                'last_stock_synced_at' => $now,
                'last_synced_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');

        if ($supplierId !== null) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('supplier_article', 'WEBPAY-TEST-1BYN')
                ->delete();
        }

        if ($productId !== null) {
            DB::table('products')->where('id', $productId)->delete();
        }

        if ($supplierId !== null) {
            DB::table('suppliers')->where('id', $supplierId)->delete();
        }

        DB::table('categories')->where('slug', self::CATEGORY_SLUG)->delete();
    }
};
