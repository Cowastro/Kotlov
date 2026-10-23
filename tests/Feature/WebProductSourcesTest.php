<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Suppliers\SupplierOfferCards;
use App\Services\Suppliers\WebProductSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebProductSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_shop_pages_and_builds_a_hidden_card_from_the_chosen_one(): void
    {
        config(['services.serper.key' => 'test-key']);
        Http::fake([
            'google.serper.dev/*' => Http::response(['organic' => [
                ['link' => 'https://www.wildberries.ru/catalog/123/detail.aspx', 'title' => 'Насос на WB'],
                ['link' => 'https://kotlov.by/tsirkulyatsionnyie/nasos', 'title' => 'Наш сайт'],
                ['link' => 'https://example-shop.by/catalog/nasos-grundfos-magna3-32-120f', 'title' => 'Grundfos MAGNA3 32-120 F'],
                ['link' => 'https://teplodvor.by/shop/nasosy/grundfos-magna3-32-120-f/', 'title' => 'Grundfos MAGNA3 32-120 F — Теплодвор'],
                ['link' => 'https://example-shop.by/', 'title' => 'Главная магазина'],
            ]]),
            '*' => Http::response('<html><body><h1>Grundfos MAGNA3 32-120 F</h1></body></html>'),
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'tsirkulyatsionnyie'], ['name' => 'Циркуляционные насосы', 'parent_id' => 0, 'is_active' => true]);

        $found = app(WebProductSources::class)->search('Grundfos MAGNA3 32-120 F');

        // Marketplaces, our own site and bare home pages are dropped; the trusted shop comes first.
        $this->assertSame([
            'https://teplodvor.by/shop/nasosy/grundfos-magna3-32-120-f/',
            'https://example-shop.by/catalog/nasos-grundfos-magna3-32-120f',
        ], array_keys($found));

        $product = app(SupplierOfferCards::class)->createFromWeb('Grundfos MAGNA3 32-120 F', 'Grundfos', $category->id, array_key_first($found), 9500);

        $this->assertFalse($product->is_active);
        $this->assertTrue($product->requires_price_confirmation);
        $this->assertSame(Product::CREATED_VIA_COMMERCIAL_OFFER, $product->created_via);
        $this->assertSame(9500.0, (float) $product->price);
        $this->assertSame('Grundfos', $product->brand->name);
        $this->assertSame('https://teplodvor.by/shop/nasosy/grundfos-magna3-32-120-f/', $product->content_source_url);
    }

    public function test_without_a_key_the_search_is_off(): void
    {
        config(['services.serper.key' => null]);
        putenv('SERPER_API_KEY');

        $this->assertFalse(app(WebProductSources::class)->isConfigured());
        $this->assertSame([], app(WebProductSources::class)->search('Grundfos MAGNA3'));
    }
}
