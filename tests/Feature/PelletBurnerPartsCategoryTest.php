<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PelletBurnerPartsCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_detectors_keep_their_cards_and_old_links_in_the_burner_parts_category(): void
    {
        $items = [];
        foreach (['KOTLOV-034535' => 'pelletnye-gorelki', 'KOTLOV-023119' => 'zapchasti-dlya-kotlov', 'KOTLOV-022713' => 'datchiki'] as $sku => $slug) {
            $category = Category::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $product = Product::create(['category_id' => $category->id, 'sku' => $sku, 'slug' => strtolower($sku), 'name' => $sku, 'is_active' => true]);
            $items[$slug] = $product;
        }

        $migration = require database_path('migrations/2026_11_26_120000_group_pellet_burner_flame_detectors.php');
        $migration->up();
        $migration->up();

        $parts = Category::where('slug', 'zapchasti-dlya-pelletnyh-gorelok')->sole();
        $this->assertSame('pelletnye-gorelki', $parts->parent->slug);
        foreach ($items as $oldCategory => $product) {
            $this->assertEquals($parts->id, $product->fresh()->category_id);
            $newPath = "/{$parts->slug}/{$product->slug}";
            $this->get("/{$oldCategory}/{$product->slug}")->assertStatus(301)->assertRedirect($newPath);
            $this->get($newPath)->assertOk()->assertSee($product->sku);
        }
    }
}
