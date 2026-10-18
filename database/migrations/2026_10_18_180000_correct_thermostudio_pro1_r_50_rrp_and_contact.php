<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SKU = 'PS-000.17381';

    private const SUPPLIER_ARTICLE = '3700589';

    private const SUPPLIER_CONTACT = '+375 (29) 308-06-20 · +375 (17) 374-94-72 · +375 (17) 374-88-66';

    public function up(): void
    {
        DB::table('products')
            ->where('sku', self::SKU)
            ->update(['price' => 430.00, 'updated_at' => now()]);

        $supplierId = DB::table('suppliers')->where('code', 'thermostudio')->value('id');
        if (! $supplierId) {
            return;
        }

        DB::table('suppliers')->where('id', $supplierId)->update([
            'contact' => self::SUPPLIER_CONTACT,
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->where('sku', self::SKU)->value('id');
        if (! $productId || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $linkedOfferId = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->value('id');
        $articleOfferId = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('supplier_article', self::SUPPLIER_ARTICLE)
            ->value('id');

        if ($linkedOfferId && (! $articleOfferId || (int) $articleOfferId === (int) $linkedOfferId)) {
            $update = [
                'supplier_article' => self::SUPPLIER_ARTICLE,
                'supplier_article_normalized' => self::SUPPLIER_ARTICLE,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
                $update['supplier_article_compact'] = self::SUPPLIER_ARTICLE;
            }

            DB::table('supplier_products')->where('id', $linkedOfferId)->update($update);
        }
    }

    public function down(): void
    {
        // The previous retail price and contact were invalid source data.
    }
};
