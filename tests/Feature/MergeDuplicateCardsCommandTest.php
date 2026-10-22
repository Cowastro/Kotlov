<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeDuplicateCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_the_fuller_card_and_redirects_the_other(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'nasosnye-stancii'], ['name' => 'Насосные станции', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $bare = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 250, 'name' => 'Насосная станция UNIPUMP AUTO QB 80-5', 'slug' => 'bare-qb-80-5']);
        $full = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 260, 'name' => 'Насосная станция Unipump AUTO QB 80-5', 'slug' => 'full-qb-80-5', 'images' => ['/img/a.webp']]);
        $other = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 300, 'name' => 'Насосная станция UNIPUMP AUTO QB 70', 'slug' => 'qb-70']);

        $this->artisan('catalog:merge-duplicate-cards', ['pairs' => ["{$bare->id},{$full->id}", "{$bare->id},{$other->id}"]])
            ->expectsOutputToContain("audit.merge_cards.pair={$full->id}:{$bare->id}:full-qb-80-5:bare-qb-80-5")
            ->expectsOutputToContain('metric.merge_cards.skipped=1')
            ->assertSuccessful();
        $this->assertFalse($bare->fresh()->is_archived);

        $this->artisan('catalog:merge-duplicate-cards', ['pairs' => ["{$bare->id},{$full->id}"], '--apply' => true])->assertSuccessful();

        $this->assertTrue($bare->fresh()->is_archived);
        $this->assertFalse($full->fresh()->is_archived);
        $this->assertSame('/nasosnye-stancii/full-qb-80-5', DB::table('redirects')->where('from_url', '/nasosnye-stancii/bare-qb-80-5')->value('to_url'));
    }
}
