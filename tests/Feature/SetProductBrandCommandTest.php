<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetProductBrandCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_a_card_to_another_brand_only_with_apply(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'membrannye-baki'], ['name' => 'Мембранные баки', 'parent_id' => 0, 'is_active' => true]);
        $unipump = Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $wester = Brand::query()->create(['name' => 'Wester', 'slug' => 'wester', 'is_active' => true]);
        $tank = Product::query()->create(['category_id' => $category->id, 'brand_id' => $unipump->id, 'is_active' => true, 'price' => 90, 'name' => 'Бак Wester WAV 8', 'slug' => 'wester-wav-8']);

        $this->artisan('catalog:set-product-brand', ['product' => $tank->id, 'brand' => 'wester'])
            ->expectsOutputToContain("audit.product_brand.change={$tank->id}:wester-wav-8:unipump:wester")
            ->assertSuccessful();
        $this->assertSame($unipump->id, (int) $tank->fresh()->brand_id);

        $this->artisan('catalog:set-product-brand', ['product' => $tank->id, 'brand' => 'wester', '--apply' => true])
            ->expectsOutputToContain('metric.product_brand.updated=1')
            ->assertSuccessful();
        $this->assertSame($wester->id, (int) $tank->fresh()->brand_id);
    }
}
