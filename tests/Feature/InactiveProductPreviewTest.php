<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveProductPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_stays_hidden_from_public_visitors(): void
    {
        [$category, $product] = $this->inactiveProduct();

        $this->get('https://kotlov.by/'.$category->slug.'/'.$product->slug.'?admin-preview=1')
            ->assertNotFound();
    }

    public function test_active_manager_can_preview_an_inactive_product(): void
    {
        [$category, $product] = $this->inactiveProduct();
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get('https://kotlov.by/'.$category->slug.'/'.$product->slug.'?admin-preview=1')
            ->assertOk()
            ->assertSee($product->name);
    }

    /** @return array{Category, Product} */
    private function inactiveProduct(): array
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Печи для предпросмотра',
            'slug' => 'preview-stoves',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Отключённая печь для предпросмотра',
            'slug' => 'inactive-preview-stove',
            'sku' => 'PREVIEW-STOVE-1',
            'price' => 0,
            'is_active' => false,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        return [$category, $product];
    }
}
