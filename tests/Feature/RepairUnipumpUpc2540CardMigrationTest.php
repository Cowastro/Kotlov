<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairUnipumpUpc2540CardMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_the_canonical_card_and_archives_the_incorrect_duplicate_with_a_redirect(): void
    {
        $category = Category::query()->create([
            'name' => 'Циркуляционные насосы',
            'slug' => 'tsirkulyatsionnyie',
            'parent_id' => 0,
        ]);
        $canonical = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.471',
            'name' => 'Насос циркуляционный Unipump UPC 25-40 180',
            'slug' => 'nasos-tsirkulyatsionnyiy-unipump-upc-25-40-180',
            'price' => 99,
            'is_active' => true,
        ]);
        $duplicate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.659',
            'name' => 'Насос Unipump UPC 25-40 180',
            'slug' => 'nasos-unipump-upc-25-40-180',
            'price' => 144,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'stock_qty' => 10,
        ]);

        $migration = require database_path('migrations/2026_10_13_000200_repair_unipump_upc_25_40_card.php');
        $migration->up();

        $canonical->refresh();
        $duplicate->refresh();

        $this->assertSame('Насос циркуляционный UNIPUMP UPC 25-40 180', $canonical->name);
        $this->assertSame('176.92', $canonical->price);
        $this->assertSame('4,5 м', collect($canonical->specs)->firstWhere('key', 'Максимальный напор')['value']);
        $this->assertStringContainsString('aqualider.by', (string) $canonical->content_source_url);
        $this->assertTrue($canonical->is_active);
        $this->assertFalse($canonical->is_archived);

        $this->assertFalse($duplicate->is_active);
        $this->assertTrue($duplicate->is_archived);
        $this->assertFalse($duplicate->in_stock);
        $this->assertNull($duplicate->stock_qty);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/tsirkulyatsionnyie/nasos-unipump-upc-25-40-180',
            'to_url' => '/tsirkulyatsionnyie/nasos-tsirkulyatsionnyiy-unipump-upc-25-40-180',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}
