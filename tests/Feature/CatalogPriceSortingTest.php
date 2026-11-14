<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPriceSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_sort_is_monotonic_across_stock_sources_and_keeps_consultation_cards_last(): void
    {
        $category = Category::create(['name' => 'Price order', 'slug' => 'price-order-test', 'is_active' => true]);
        $create = fn (string $sku, int $price, bool $manual, array $extra = []) => Product::create([
            'category_id' => $category->id, 'name' => $sku, 'sku' => $sku, 'slug' => $sku,
            'price' => $price, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'manual_stock_management' => $manual, 'requires_price_confirmation' => false,
            ...$extra,
        ]);
        $cheap = $create('price-cheap', 100, false, ['popularity_score' => 1000]);
        $expensive = $create('price-expensive', 300, true, ['popularity_score' => 10]);
        $middle = $create('price-middle', 200, true, ['popularity_score' => 5]);
        $consultation = $create('price-consultation', 50, false, [
            'requires_price_confirmation' => true, 'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $this->get('/'.$category->slug)->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$expensive->id, $middle->id, $cheap->id, $consultation->id]);
        foreach (['price_asc' => [$cheap->id, $middle->id, $expensive->id], 'price_desc' => [$expensive->id, $middle->id, $cheap->id]] as $sort => $expected) {
            $this->get('/'.$category->slug.'?sort='.$sort)->assertOk()
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === [...$expected, $consultation->id]);
            $this->get('/'.$category->slug.'?sort='.$sort.'&in_stock=1')->assertOk()
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === $expected);
        }
    }
}
