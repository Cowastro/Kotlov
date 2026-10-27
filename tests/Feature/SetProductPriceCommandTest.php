<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetProductPriceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_the_price_and_shows_it(): void
    {
        $category = Category::query()->create(['name' => 'Переходы', 'slug' => 'perehody-adaptery-dymohod', 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Переход d60 мм на d80 мм', 'slug' => 'perexod-60-80', 'price' => 0, 'is_active' => true, 'requires_price_confirmation' => true]);

        $this->artisan('catalog:set-price', ['pairs' => ["{$product->id}:14,5", '999999:10']])
            ->expectsOutputToContain("audit.set_price.card={$product->id}:0.00:14.50")
            ->expectsOutputToContain('metric.set_price.skipped=1')
            ->assertSuccessful();
        $this->assertSame(0.0, (float) $product->fresh()->price);

        $this->artisan('catalog:set-price', ['pairs' => ["{$product->id}:14.5"], '--apply' => true])->assertSuccessful();

        $this->assertSame(14.5, (float) $product->fresh()->price);
        $this->assertFalse($product->fresh()->requires_price_confirmation);
    }
}
