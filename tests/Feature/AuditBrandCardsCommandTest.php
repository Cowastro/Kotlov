<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditBrandCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_brand_cards_and_sets_the_missing_brand(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'nasosnye-stancii'], ['name' => 'Насосные станции', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $bare = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 250, 'name' => 'Насосная станция UNIPUMP AUTO QB 80', 'slug' => 'auto-qb-80']);
        Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 250, 'name' => 'Насосная станция Unipump AUTO QB 80', 'slug' => 'auto-qb-80-2']);

        $this->artisan('catalog:audit-brand-cards', ['brand' => 'unipump'])
            ->expectsOutputToContain("audit.brand_cards.card={$bare->id}:nasosnye-stancii:auto-qb-80:1:brand.unlinked.images.content.specs")
            ->expectsOutputToContain('metric.brand_cards.duplicate_groups=1')
            ->expectsOutputToContain('metric.brand_cards.brand_missing=1')
            ->assertSuccessful();
        $this->assertNull($bare->fresh()->brand_id);

        $this->artisan('catalog:audit-brand-cards', ['brand' => 'unipump', '--apply' => true])->assertSuccessful();
        $this->assertSame($brand->id, (int) $bare->fresh()->brand_id);
    }
}
