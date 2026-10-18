<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveFerolifeBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ferolife_is_archived_without_losing_commercial_data(): void
    {
        $brand = Brand::create(['name' => 'Феролайф', 'slug' => 'ferolife-test', 'is_active' => true, 'is_featured' => true]);
        $other = Brand::create(['name' => 'Другой бренд', 'slug' => 'other-archive-test', 'is_active' => true]);
        $category = Category::create(['name' => 'Дымоходы', 'slug' => 'archive-chimneys-test', 'parent_id' => 0]);
        $products = collect([$brand, $other])->map(fn ($item) => Product::create([
            'name' => 'Дымоход '.$item->id, 'slug' => 'archive-test-'.$item->id,
            'sku' => 'ARCHIVE-TEST-'.$item->id, 'brand_id' => $item->id, 'category_id' => $category->id,
            'is_active' => true, 'is_archived' => false, 'price' => 123, 'stock_qty' => 7,
        ]));

        $migration = require database_path('migrations/2026_10_18_120000_archive_ferolife_brand.php');
        $migration->up();
        $migration->up();

        $archived = $products[0]->fresh();
        $this->assertTrue($archived->is_archived);
        $this->assertFalse($archived->is_active);
        $this->assertFalse($archived->canBePurchased());
        $this->assertEquals(123, $archived->price);
        $this->assertEquals(7, $archived->stock_qty);
        $this->assertFalse($brand->fresh()->is_active);
        $this->assertFalse($brand->fresh()->is_featured);
        $this->assertTrue($other->fresh()->is_active);
        $this->assertTrue($products[1]->fresh()->is_active);
        $this->assertFalse($products[1]->fresh()->is_archived);
    }
}
