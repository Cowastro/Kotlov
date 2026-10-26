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

    public function test_the_tis_standard_series_number_does_not_block_a_pair_and_1c_rows_move(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], ['name' => 'Переходы', 'parent_id' => 0, 'is_active' => true]);
        $old = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 40, 'name' => 'Теплов и Сухов Адаптер Моно М-М 304-0.8 D150', 'slug' => 'tis-adapter-150', 'images' => ['/img/a.webp']]);
        $series = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 42, 'name' => 'ТиС Стандарт 50 Адаптер Моно М-М 304-0.8 D150', 'slug' => 'tis-standart-50-adapter-150']);
        $sourceId = DB::table('integration_sources')->insertGetId(['code' => 'onec-merge-test', 'name' => '1C', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('integration_products')->insert(['integration_source_id' => $sourceId, 'external_id' => 'x1', 'name' => 'Адаптер', 'product_id' => $series->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('catalog:merge-duplicate-cards', ['pairs' => ["{$old->id},{$series->id}"], '--apply' => true])
            ->expectsOutputToContain('metric.merge_cards.integration_moved=1')
            ->assertSuccessful();

        $this->assertTrue($series->fresh()->is_archived);
        $this->assertSame($old->id, (int) DB::table('integration_products')->where('external_id', 'x1')->value('product_id'));
    }
}
