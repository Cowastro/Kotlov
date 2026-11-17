<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnrichFromUrlShopTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shops_sales_line_is_not_saved_as_the_description(): void
    {
        $sales = 'Купить Увлажнитель Ballu AW-505 по скидке за 15670 ₽ в официальном магазине РУСКЛИМАТ в Москве — Скидка 3% при онлайн оплате';
        Http::fake(['*' => Http::response('<html><head><title>AW-505</title><meta property="og:description" content="'.$sales.'"><meta name="description" content="'.$sales.'"></head><body><h1>Ballu AW-505</h1></body></html>')]);
        $category = Category::query()->firstOrCreate(['slug' => 'klimat'], ['name' => 'Климат', 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Ballu AW-505', 'slug' => 'ballu-aw-505', 'price' => 585, 'content' => $sales]);

        $this->artisan('catalog:enrich-from-url', ['product' => $product->id, 'url' => 'https://www.rusklimat.ru/product/x', '--apply' => true])
            ->expectsOutputToContain('audit.enrich_from_url.shop_text='.$product->id)
            ->assertSuccessful();

        $this->assertStringNotContainsString('₽', (string) $product->fresh()->content);
    }
}
