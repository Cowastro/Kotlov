<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncDealByBrandPricesProMetallTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_item_is_found_by_the_manufacturer_article_of_the_supplier(): void
    {
        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'deal-pm-test', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Печь банная "Атмосфера L" сетка нержавейка', 'slug' => 'deal-pm-atm-l',
            'sku' => 'PROMETALL-P0048', 'price' => 4588, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::query()->where('code', 'prometall')->firstOrFail();
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $card->id, 'supplier_article' => 'p0048', 'match_status' => 'matched']);

        $this->mock(DealByClient::class, fn (MockInterface $mock) => $mock->shouldReceive('listProducts')->andReturn(['products' => [
            ['id' => 176713078, 'sku' => 'p0048', 'name' => 'Печь банная ПроМеталл Атмосфера L сетка из нержавеющей стали', 'price' => 4130],
            ['id' => 176716401, 'sku' => '', 'name' => 'Банная печь ПроМеталл Атмосфера 2XL', 'price' => 0],
        ]]));

        $this->artisan('deal:sync-brand-prices', ['--supplier' => 'prometall'])
            ->expectsOutputToContain('metric.deal_brand_prices.matched=1')
            ->expectsOutputToContain("audit.deal_brand_prices=176713078:{$card->id}:prometall:413000:458800")
            ->assertSuccessful();
    }
}
