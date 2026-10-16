<?php

namespace Tests\Feature;

use App\Console\Commands\PublishKotlovCeramicBoilerCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishKotlovCeramicBoilerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_the_confirmed_package_idempotently_without_changing_the_burner(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Пеллетные котлы', 'is_active' => true, 'parent_id' => 0]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'kotlov'], ['name' => 'KOTLOV']);
        $burner = Product::query()->create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'sku' => 'burner-test',
            'slug' => 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt', 'name' => 'KOTLOV Ceramic PRO 100',
            'price' => 12960, 'in_stock' => true, 'stock_qty' => 3,
        ]);
        $before = $burner->fresh()->getAttributes();
        $initialCount = Product::query()->count();
        $this->artisan('catalog:publish-kotlov-ceramic-boiler')->assertSuccessful();
        $this->assertSame($initialCount, Product::query()->count());
        $this->artisan('catalog:publish-kotlov-ceramic-boiler', ['--apply' => true])->assertSuccessful();
        $boiler = Product::query()->where('sku', PublishKotlovCeramicBoilerCommand::SKU)->firstOrFail();
        $this->assertSame('25600.00', $boiler->price);
        $this->assertFalse($boiler->in_stock);
        $this->assertFalse($boiler->requires_price_confirmation);
        $this->assertCount(4, $boiler->images);
        $this->assertStringContainsString('1,5 м³', $boiler->content);
        $this->assertStringContainsString('не нужно покупать отдельный интернет-модуль', $boiler->content);
        $this->assertStringContainsString('95 кВт', $boiler->name);
        $this->assertStringNotContainsString('КПД 95', $boiler->content);
        $boiler->update(['stock_qty' => 2, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK]);
        $this->artisan('catalog:publish-kotlov-ceramic-boiler', ['--apply' => true])->assertSuccessful();
        $this->assertSame($initialCount + 1, Product::query()->count());
        $this->assertSame(2.0, (float) $boiler->fresh()->stock_qty);
        $this->assertTrue($boiler->fresh()->in_stock);
        $this->assertSame($before, $burner->fresh()->getAttributes());
    }

    public function test_it_refuses_to_overwrite_a_different_product(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Пеллетные котлы', 'is_active' => true, 'parent_id' => 0]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'kotlov'], ['name' => 'KOTLOV']);
        Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'sku' => 'burner', 'slug' => 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt', 'name' => 'Burner']);
        $other = Product::query()->create(['category_id' => $category->id, 'sku' => 'other', 'slug' => PublishKotlovCeramicBoilerCommand::SLUG, 'name' => 'Other', 'price' => 123]);
        $before = $other->fresh()->getAttributes();
        $this->artisan('catalog:publish-kotlov-ceramic-boiler', ['--apply' => true])->assertFailed();
        $this->assertSame($before, $other->fresh()->getAttributes());
    }
}
