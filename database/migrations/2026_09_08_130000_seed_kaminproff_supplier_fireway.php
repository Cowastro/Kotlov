<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Product;

/**
 * Create supplier Каминпрофессионал and link 5 new Fireway products with wholesale prices.
 */
return new class extends Migration
{
    private const SUPPLIER_CODE = 'kaminproff';

    // [slug => [opt_price, rrc_price, supplier_name]]
    private const PRODUCTS = [
        'kaminnaya-topka-fireway-elena'          => [2873.00, 3735.00, 'Каминная топка ELENA'],
        'kaminnaya-topka-fireway-sofia-pravaya'  => [4299.00, 5589.00, 'Каминная топка SOFIA правая'],
        'kaminnaya-topka-fireway-sofia-levaya'   => [4299.00, 5589.00, 'Каминная топка SOFIA левая'],
        'pech-kamin-fireway-konnecta'            => [2383.00, 3098.00, 'Печь-камин KONNECTA'],
        'otopitelno-varochnaya-pech-fireway-skif'=> [7170.00, 9321.00, 'Печь SKIF отопительно-варочная'],
    ];

    public function up(): void
    {
        // 1. Создать поставщика (если нет)
        $supplier = Supplier::firstOrCreate(
            ['code' => self::SUPPLIER_CODE],
            [
                'name'          => 'Каминпрофессионал (kaminproff.by)',
                'currency'      => 'BYN',
                'currency_rate' => 1.0,
                'contact'       => 'kaminproff@mail.ru | kaminproff.by | УНП 590839275',
                'notes'         => 'Официальный дистрибьютор Fireway в Беларуси. Прайс 07.2026.',
                'is_active'     => true,
            ]
        );

        // 2. Привязать товары
        foreach (self::PRODUCTS as $slug => [$opt, $rrc, $supplierName]) {
            $product = Product::where('slug', $slug)->first();
            if (!$product) {
                continue;
            }

            // Идемпотентно — не дублировать
            if (SupplierProduct::where('supplier_id', $supplier->id)
                ->where('product_id', $product->id)
                ->exists()) {
                continue;
            }

            SupplierProduct::create([
                'supplier_id'               => $supplier->id,
                'product_id'                => $product->id,
                'supplier_article'          => 'fireway-' . $slug,
                'supplier_article_normalized' => 'fireway-' . $slug,
                'supplier_article_compact'  => 'fireway-' . $slug,
                'supplier_name'             => $supplierName,
                'price'                     => $opt,
                'price_byn'                 => $opt,
                'currency'                  => 'BYN',
                'currency_rate'             => 1.0,
                'in_stock'                  => false,
                'stock_status'              => 'preorder',
                'match_status'              => 'matched',
                'match_confidence'          => 100,
                'last_synced_at'            => now(),
            ]);
        }
    }

    public function down(): void
    {
        $supplier = Supplier::where('code', self::SUPPLIER_CODE)->first();
        if ($supplier) {
            $slugs    = array_keys(self::PRODUCTS);
            $products = Product::whereIn('slug', $slugs)->pluck('id');
            SupplierProduct::where('supplier_id', $supplier->id)
                ->whereIn('product_id', $products)
                ->delete();
            // Не удаляем самого поставщика — может быть уже привязан к другим товарам
        }
    }
};
