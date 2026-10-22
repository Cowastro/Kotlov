<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArchiveRehauBrandAndProductsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_rehau_catalog_and_creates_category_redirects(): void
    {
        $floor = Category::query()->create([
            'name' => 'Тёплый пол', 'slug' => 'teplyj-pol', 'parent_id' => 0, 'is_active' => true,
        ]);
        $pipes = Category::query()->create([
            'name' => 'Трубы и фитинги', 'slug' => 'truby-i-fitingi', 'parent_id' => 0, 'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'REHAU', 'slug' => 'rehau', 'is_active' => true, 'is_featured' => true,
        ]);
        $otherBrand = Brand::query()->create(['name' => 'Другой бренд', 'slug' => 'other-brand']);

        $thermostat = $this->product($floor, $brand, 'Терморегулятор REHAU', 'termoregulyator-rehau-solelec-optima');
        $sleeve = $this->product($pipes, $brand, 'Монтажная гильза', 'montazhnye-gilzy-rautitan-px-d16');
        $genericListed = $this->product($pipes, $otherBrand, 'Старый комплект', 'kryuki-s-dyubelem');
        $unrelated = $this->product($pipes, $otherBrand, 'Латунная муфта', 'latunnaya-mufta-test');

        $migration = require __DIR__.'/../../database/migrations/2026_10_22_090000_archive_rehau_brand_and_products.php';
        $migration->up();

        foreach ([$thermostat, $sleeve, $genericListed] as $product) {
            $product->refresh();
            $this->assertFalse($product->is_active);
            $this->assertTrue($product->is_archived);
            $this->assertFalse($product->in_stock);
            $this->assertSame(0, $product->stock_qty);
        }

        $this->assertTrue($unrelated->fresh()->is_active);
        $this->assertFalse($brand->fresh()->is_active);
        $this->assertFalse($brand->fresh()->is_featured);

        $this->assertDatabaseHas('redirects', [
            'from_url' => '/teplyj-pol/termoregulyator-rehau-solelec-optima',
            'to_url' => '/teplyj-pol',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/truby-i-fitingi/kryuki-s-dyubelem',
            'to_url' => '/truby-i-fitingi',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/brands/rehau',
            'to_url' => '/truby-i-fitingi',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertSame(37, DB::table('redirects')->whereIn('to_url', ['/teplyj-pol', '/truby-i-fitingi'])->count());

        $this->get('/teplyj-pol/termoregulyator-rehau-solelec-optima')
            ->assertStatus(301)
            ->assertRedirect('/teplyj-pol');
        $this->get('/truby-i-fitingi/rautitan-flex-d16')
            ->assertStatus(301)
            ->assertRedirect('/truby-i-fitingi');
        $this->get('/brands/rehau')
            ->assertStatus(301)
            ->assertRedirect('/truby-i-fitingi');
    }

    private function product(Category $category, Brand $brand, string $name, string $slug): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => 'TEST-'.uniqid(),
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'stock_qty' => 3,
        ]);
    }
}
