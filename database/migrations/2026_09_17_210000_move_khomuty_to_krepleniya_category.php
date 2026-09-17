<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Models\Supplier;
use App\Models\Category;

/**
 * The 14 хомут (clamp) cards created from the 17.09.26 Thermostudio price
 * list landed in "Заглушки и оголовки" (the generic default category for
 * unrecognised Teplov i Sukhov component types). Move them to "Крепления и
 * монтаж", which is where mounting hardware like this actually belongs.
 * Matched by supplier_products.supplier_article — precise, no name guessing.
 */
return new class extends Migration
{
    private const ARTICLES = [
        'TS.KMP.HMT.0100.74074',
        'TS.KMP.HMT.0110.74075',
        'TS.KMP.HMT.0115.74076',
        'TS.KMP.HMT.0130.76419',
        'TS.KMP.HMT.0140.76420',
        'TS.KMP.HMT.0200.74084',
        'TS.KMP.HMT.0080.74186',
        'TS.KMP.HMR.0180.74012',
        'TS.KMP.HMR.0200.74013',
        'TS.KMP.HMR.0210.74014',
        'TS.KMP.KRU.0240.77104',
        'TS.KMP.HMR.0260.74015',
        'TS.KMP.KRU.0280.77417',
        'TS.KMP.KRU.0310.77415',
    ];

    public function up(): void
    {
        $supplierId = Supplier::where('code', 'teplov')->value('id');
        $categoryId = Category::where('name', 'Крепления и монтаж')->value('id');

        if (! $supplierId || ! $categoryId) {
            return;
        }

        $productIds = SupplierProduct::where('supplier_id', $supplierId)
            ->whereIn('supplier_article', self::ARTICLES)
            ->pluck('product_id')
            ->filter()
            ->unique();

        Product::whereIn('id', $productIds)->update(['category_id' => $categoryId]);
    }

    public function down(): void {}
};
