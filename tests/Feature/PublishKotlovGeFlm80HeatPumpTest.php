<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublishKotlovGeFlm80HeatPumpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_the_flm80_250_under_kotlov_ge_with_sanbusinessgroup_route(): void
    {
        $migration = require database_path('migrations/2026_10_21_231000_publish_kotlov_ge_flm80_250_r290.php');
        $migration->up();
        $migration->up();

        $product = DB::table('products')->where('slug', 'kotlov-ge-nl-flm80-250ii-r290-25-2-kvt')->first();
        $this->assertNotNull($product);
        $this->assertSame(21300.0, (float) $product->price);
        $this->assertTrue((bool) $product->is_active);
        $this->assertFalse((bool) $product->requires_price_confirmation);
        $this->assertStringContainsString('NL-FLM80-250II/R290', $product->name);
        $this->assertStringNotContainsString('NULITE', $product->name);

        $brand = DB::table('brands')->where('id', $product->brand_id)->first();
        $this->assertSame('KOTLOV GE', $brand->name);

        $offer = DB::table('supplier_products')->where('product_id', $product->id)->first();
        $supplier = DB::table('suppliers')->where('id', $offer->supplier_id)->first();
        $this->assertSame('sanbusinessgroup', $supplier->code);
        $this->assertSame('ООО «СанБизнесГруп»', $offer->warehouse_name);
        $this->assertSame('preorder', $offer->stock_status);

        $images = json_decode($product->images, true);
        $this->assertCount(2, $images);
        foreach ($images as $image) {
            $this->assertFileExists(public_path($image));
        }
    }
}
