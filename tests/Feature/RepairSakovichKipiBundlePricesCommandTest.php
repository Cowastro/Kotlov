<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairSakovichKipiBundlePricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_all_bundle_prices_and_detaches_boiler_only_offers(): void
    {
        $category = Category::create(['name' => 'Котлы', 'slug' => 'repair-sakovich-bundles']);
        $brand = Brand::create(['name' => 'Sakovich', 'slug' => 'repair-sakovich']);
        $supplier = Supplier::create(['name' => 'Сантехсклад', 'code' => 'santehsklad']);
        $bundles = [
            16 => [12577, 'KOTLOV-000299', 10380.00, 8200.50],
            20 => [12591, 'KOTLOV-000313', 11300.00, 8932.00],
            26 => [12594, 'KOTLOV-000316', 12650.00, 9991.00],
            36 => [12593, 'KOTLOV-000315', 14320.00, 11302.40],
            50 => [12592, 'KOTLOV-000314', 17960.00, 14170.30],
        ];

        foreach ($bundles as $power => [$id, $sku]) {
            $product = Product::forceCreate([
                'id' => $id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => "Пеллетный котел Sakovich Pellet Universal {$power} с горелкой KIPI Rotary и бункером",
                'slug' => "sakovich-pellet-universal-{$power}-repair-test",
                'sku' => $sku,
                'price' => 1000 + $power,
                'price_old' => 2000 + $power,
                'is_sale' => true,
                'is_active' => true,
            ]);
            SupplierProduct::create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_article' => "PELLET-UNIVERSAL-{$power}",
                'supplier_article_normalized' => "PELLET-UNIVERSAL-{$power}",
                'supplier_name' => "Boiler only {$power}",
                'price' => 500,
                'price_byn' => 500,
                'currency' => 'BYN',
                'match_status' => 'matched',
            ]);
        }

        $this->artisan('catalog:repair-sakovich-kipi-bundle-prices')
            ->expectsOutput('metric.sakovich_bundle.price_updates=5')
            ->expectsOutput('metric.sakovich_bundle.boiler_links_detached=5')
            ->expectsOutput('metric.sakovich_bundle.bundle_links_upserted=5')
            ->assertSuccessful();
        $this->assertSame('1020.00', Product::findOrFail(12591)->price);

        $this->artisan('catalog:repair-sakovich-kipi-bundle-prices', ['--apply' => true])
            ->expectsOutput('metric.sakovich_bundle.errors=0')
            ->assertSuccessful();

        foreach ($bundles as $power => [$id, $sku, $retail, $purchase]) {
            $product = Product::findOrFail($id);
            $this->assertSame(number_format($retail, 2, '.', ''), $product->price);
            $this->assertSame(number_format($purchase, 2, '.', ''), $product->manual_purchase_price_byn);
            $this->assertNull($product->price_old);
            $this->assertFalse($product->is_sale);
            $this->assertFalse($product->requires_price_confirmation);
            $this->assertDatabaseHas('supplier_products', [
                'supplier_id' => $supplier->id,
                'supplier_article_normalized' => "PELLET-UNIVERSAL-{$power}",
                'product_id' => null,
                'match_status' => 'unmatched',
            ]);
            $this->assertDatabaseHas('supplier_products', [
                'supplier_id' => $supplier->id,
                'supplier_article_normalized' => "PELLET-UNIVERSAL-{$power}-KIPI-BIO-BUNDLE",
                'product_id' => $id,
                'price_byn' => number_format($purchase, 2, '.', ''),
                'match_status' => 'matched',
                'match_confidence' => 'manual_price_list',
            ]);
        }
    }
}
