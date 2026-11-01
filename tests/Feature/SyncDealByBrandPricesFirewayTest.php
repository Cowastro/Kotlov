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

class SyncDealByBrandPricesFirewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fireway_shop_item_is_found_by_the_model_of_its_name(): void
    {
        $category = Category::query()->create(['name' => 'Банные печи', 'slug' => 'deal-fw-test', 'is_active' => true]);
        $card = fn (string $name, string $slug, float $price) => Product::query()->create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug,
            'price' => $price, 'is_active' => true, 'is_archived' => false]);
        $kovka = $card('Банная печь Fireway ПароВар 18 Ковка (K201)', 'deal-fw-kovka', 1371);
        $prut = $card('Банная печь Fireway ПароВар 18 (К201)', 'deal-fw-prut', 1215);
        $door = $card('Дверь для бани стеклянная Fireway 1900×800 графит', 'deal-fw-door', 444);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'kaminproff'], ['name' => 'Каминпрофессионал', 'currency' => 'BYN', 'is_active' => true]);
        foreach ([$kovka, $prut, $door] as $product) {
            SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'fw-'.$product->slug,
                'supplier_name' => $product->name, 'match_status' => 'matched']);
        }

        $this->mock(DealByClient::class, fn (MockInterface $mock) => $mock->shouldReceive('listProducts')->andReturn(['products' => [
            ['id' => 501, 'sku' => '', 'name' => 'Банная печь FireWay ПароВар 18 Ковка (К201)', 'price' => 1300],
            ['id' => 502, 'sku' => '', 'name' => 'Печь для бани FireWay ПароВар 18 (K201)', 'price' => 1215],
            ['id' => 503, 'sku' => '', 'name' => 'Дверь для бани стеклянная Fireway 1900x800 графит (стекло 8 мм, 3 петли)', 'price' => 420],
        ]]));

        $this->artisan('deal:sync-brand-prices', ['--supplier' => 'kaminproff'])
            ->expectsOutputToContain('metric.deal_brand_prices.matched=3')
            ->expectsOutputToContain("audit.deal_brand_prices=501:{$kovka->id}:kaminproff:130000:137100")
            ->expectsOutputToContain("audit.deal_brand_prices=503:{$door->id}:kaminproff:42000:44400")
            ->assertSuccessful();
    }
}
