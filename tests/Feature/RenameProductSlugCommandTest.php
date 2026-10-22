<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenameProductSlugCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renames_the_slug_and_redirects_old_addresses_without_chains(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'pechi-kaminy'], ['name' => 'Печи-камины', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 6380, 'name' => 'Камин КРИТ графит', 'slug' => 'pech-kamin-ecokamin-bavariya-s-to']);
        DB::table('redirects')->insert(['from_url' => '/old-krit', 'to_url' => '/pechi-kaminy/pech-kamin-ecokamin-bavariya-s-to', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('catalog:rename-product-slug', ['product' => $product->id, 'slug' => 'kamin-ecokamin-krit-grafit'])
            ->expectsOutputToContain('metric.product_slug.chained_redirects=1')->assertSuccessful();
        $this->assertSame('pech-kamin-ecokamin-bavariya-s-to', $product->fresh()->slug);

        $this->artisan('catalog:rename-product-slug', ['product' => $product->id, 'slug' => 'kamin-ecokamin-krit-grafit', '--apply' => true])->assertSuccessful();

        $this->assertSame('kamin-ecokamin-krit-grafit', $product->fresh()->slug);
        $this->assertSame('/pechi-kaminy/kamin-ecokamin-krit-grafit', DB::table('redirects')->where('from_url', '/old-krit')->value('to_url'));
        $this->get('/pechi-kaminy/pech-kamin-ecokamin-bavariya-s-to')->assertRedirect('/pechi-kaminy/kamin-ecokamin-krit-grafit');

        $this->artisan('catalog:rename-product-slug', ['product' => $product->id, 'slug' => 'Bad Slug'])->assertFailed();
    }
}
