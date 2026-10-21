<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeRizonDuplicatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_the_new_card_takes_the_larger_gallery_and_redirects_the_old_one(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'tverdotoplivnye'], ['name' => 'Твердотопливные котлы', 'parent_id' => 0, 'is_active' => true]);
        $keep = Product::query()->forceCreate([
            'id' => 12639, 'category_id' => $category->id, 'is_active' => true, 'price' => 2483,
            'name' => 'Твердотопливный котел Теплоприбор Rizon M 10 A', 'slug' => 'new-rizon-m-10-a', 'images' => ['/img/new.webp'],
        ]);
        $dupe = Product::query()->forceCreate([
            'id' => 10068, 'category_id' => $category->id, 'is_active' => true, 'price' => 2760,
            'name' => 'Твердотопливный котёл Теплоприбор Rizon M 10 A', 'slug' => 'old-rizon-m-10-a', 'images' => ['/img/a.jpg', '/img/b.jpg'],
        ]);

        $this->artisan('catalog:merge-rizon-duplicates')
            ->expectsOutputToContain('metric.rizon_merge.pairs=1')
            ->expectsOutputToContain('metric.rizon_merge.skipped=3')
            ->assertSuccessful();
        $this->assertFalse($dupe->fresh()->is_archived);

        $this->artisan('catalog:merge-rizon-duplicates --apply')->assertSuccessful();
        $this->artisan('catalog:merge-rizon-duplicates --apply')->expectsOutputToContain('metric.rizon_merge.pairs=0')->assertSuccessful();

        $this->assertTrue($dupe->fresh()->is_archived);
        $this->assertFalse($dupe->fresh()->is_active);
        $this->assertSame(['/img/a.jpg', '/img/b.jpg', '/img/new.webp'], $keep->fresh()->images);
        $this->assertSame('/tverdotoplivnye/new-rizon-m-10-a', DB::table('redirects')->where('from_url', '/tverdotoplivnye/old-rizon-m-10-a')->value('to_url'));
        $this->get('/tverdotoplivnye/old-rizon-m-10-a')->assertRedirect('/tverdotoplivnye/new-rizon-m-10-a');
    }
}
