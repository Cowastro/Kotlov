<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateKgElektronikCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_cards_and_preorder_offers_come_from_the_data_file(): void
    {
        foreach (['regulyatory', 'zapchasti-dlya-kotlov', 'datchiki'] as $slug) {
            Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => 0, 'is_active' => true]);
        }
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'is_active' => true]);

        $this->artisan('catalog:create-kg-elektronik')->expectsOutputToContain('metric.status=dry_run')->assertSuccessful();
        $this->assertSame(0, Product::query()->where('sku', 'like', 'KG-%')->count());

        $this->artisan('catalog:create-kg-elektronik', ['--apply' => true])
            ->expectsOutputToContain('metric.kg_elektronik.created=66')
            ->expectsOutputToContain('metric.kg_elektronik.hidden=5')
            ->assertSuccessful();

        $brand = Brand::query()->where('slug', 'kg-elektronik')->sole();
        $this->assertTrue(DB::table('brand_supplier')->where(['brand_id' => $brand->id, 'supplier_id' => $supplier->id])->exists());
        $sp10 = Product::query()->where('sku', 'KG-SP-10-2P')->sole();
        $this->assertSame(449.0, (float) $sp10->price);
        $this->assertTrue($sp10->is_active);
        $this->assertSame(Product::AVAILABILITY_CHECK, $sp10->availability_status);
        $this->assertNotEmpty($sp10->images);
        $this->assertFileExists(public_path($sp10->images[0]));
        $offer = SupplierProduct::query()->where('product_id', $sp10->id)->sole();
        $this->assertSame($supplier->id, $offer->supplier_id);
        $this->assertSame('preorder', $offer->stock_status);
        $this->assertSame(14, (int) $offer->delivery_days);
        $this->assertEqualsWithDelta(337 * 0.7 * 0.7335 * 1.12 * 1.2, (float) $offer->price_byn, 0.01);
        $this->assertFalse(Product::query()->where('sku', 'KG-CPW-1')->sole()->is_active);

        // A second run keeps the cards and their prices: no duplicates.
        $sp10->forceFill(['price' => 470])->save();
        $this->artisan('catalog:create-kg-elektronik', ['--apply' => true])->expectsOutputToContain('metric.kg_elektronik.kept=66')->assertSuccessful();
        $this->assertSame(66, Product::query()->where('sku', 'like', 'KG-%')->count());
        $this->assertSame(66, SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'like', 'KG-%')->count());
        $this->assertSame(470.0, (float) $sp10->fresh()->price);
    }
}
