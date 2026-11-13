<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncDealByCatalogPricesTest extends TestCase
{
    use RefreshDatabase;

    private function card(): Product
    {
        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'deal-catalog-test', 'is_active' => true]);

        return Product::query()->create(['category_id' => $category->id, 'name' => 'Печь 18 М', 'slug' => 'deal-catalog-stove',
            'sku' => 'CATALOG-18M', 'price' => 1852, 'is_active' => true, 'is_archived' => false, 'requires_price_confirmation' => false]);
    }

    private function remote(float $price): array
    {
        return ['products' => [['id' => 100, 'sku' => 'CATALOG-18M', 'name' => 'Печь 18 М', 'price' => $price, 'currency' => 'BYN']]];
    }

    public function test_preview_does_not_write_remote_prices_or_permanent_links(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->once()->andReturn($this->remote(1494));
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices')->expectsOutputToContain('metric.deal_catalog_prices.update=1')->assertSuccessful();
        Storage::disk('local')->assertMissing('deal-by/product-links.json');
    }

    public function test_apply_verifies_api_and_persists_the_stable_mapping(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($this->remote(1494), $this->remote(1852));
            $mock->shouldReceive('editProducts')->once()->with([['id' => 100, 'price' => 1852.0]])->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->expectsOutputToContain('metric.deal_catalog_prices.linked=1')->assertSuccessful();
        $links = json_decode(Storage::disk('local')->get('deal-by/product-links.json'), true);
        $this->assertSame($card->id, $links[100]['local_id']);
        $this->assertSame(1852.0, (float) $card->fresh()->price);
    }

    public function test_an_accepted_write_with_unchanged_api_price_is_a_failure_and_is_not_linked(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($this->remote(1494));
            $mock->shouldReceive('editProducts')->once()->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->expectsOutputToContain('metric.deal_catalog_prices.verification_failures=1')->assertFailed();
        $this->assertSame([], json_decode(Storage::disk('local')->get('deal-by/product-links.json'), true));
    }

    public function test_malformed_catalog_cannot_trigger_partial_price_writes(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->once()->andReturn(['unexpected' => []]);
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->assertFailed();
    }
}
