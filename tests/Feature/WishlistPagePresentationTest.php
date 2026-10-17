<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistPagePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_uses_product_image_resolver_and_renders_counts(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Избранное тест',
            'slug' => 'wishlist-presentation-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с локальным фото',
            'slug' => 'wishlist-local-image-test',
            'sku' => 'WISHLIST-IMAGE-001',
            'price' => 125,
            'images' => ['img/products/wishlist-test-photo.jpg'],
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        WishlistItem::query()->create([
            'user_id' => $user->id,
            'session_id' => 'wishlist-presentation-session',
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->get('/wishlist')
            ->assertOk()
            ->assertSee('src="/img/products/wishlist-test-photo.jpg"', false)
            ->assertDontSee('https://kotlov.by/images/product/img/products/wishlist-test-photo.jpg', false)
            ->assertSee('class="count wishlist-count">1</span>', false)
            ->assertSee('class="toolbar-count wishlist-count">1</span>', false)
            ->assertSee('data-wishlist-remove', false);
    }

    public function test_guest_wishlist_toggle_returns_current_session_count(): void
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Гостевое избранное',
            'slug' => 'guest-wishlist-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Гостевой товар',
            'slug' => 'guest-wishlist-product',
            'sku' => 'WISHLIST-GUEST-001',
            'price' => 75,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('wishlist_items', [
            'product_id' => $product->id,
            'user_id' => null,
        ]);
    }
}
