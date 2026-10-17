<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWishlistButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_working_wishlist_heart_action(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::query()->create([
            'name' => 'Тестовая категория',
            'slug' => 'wishlist-test-category',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для избранного',
            'slug' => 'wishlist-test-product',
            'sku' => 'WISHLIST-TEST-001',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->get('/'.$category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSee('class="product-extra-icon link wishlist-toggle"', false)
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('class="icon icon-heart"', false)
            ->assertSeeText('В избранное');

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('message', 'added')
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('wishlist_items', [
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('message', 'removed')
            ->assertJsonPath('count', 0);

        $this->assertSame(0, WishlistItem::query()->count());
    }
}
