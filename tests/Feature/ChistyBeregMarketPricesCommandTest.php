<?php

namespace Tests\Feature;

use App\Console\Commands\ChistyBeregMarketPricesCommand;
use App\Console\Commands\SyncChistyBeregCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Market\MarketWebResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ChistyBeregMarketPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_median_leaves_out_probable_offers_and_own_shops(): void
    {
        $this->assertSame(110.0, ChistyBeregMarketPricesCommand::marketPrice([
            ['domain' => 'shop-a.by', 'match' => 'exact', 'price_byn' => 100],
            ['domain' => 'shop-b.by', 'match' => 'exact', 'price_byn' => 120],
            ['domain' => 'shop-c.by', 'match' => 'probable', 'price_byn' => 500],
            ['domain' => 'www.bereg.by', 'match' => 'exact', 'price_byn' => 90],
            ['domain' => 'minsk.kotlov.by', 'match' => 'exact', 'price_byn' => 90],
        ]));
        $this->assertNull(ChistyBeregMarketPricesCommand::marketPrice([['domain' => 'bereg.by', 'match' => 'exact', 'price_byn' => 90]]));
        $this->assertSame(99.9, SyncChistyBeregCommand::retailPrice(80.5, 99.9, 150));
        $this->assertSame(150.0, SyncChistyBeregCommand::retailPrice(84.74, 84.74, 150));
        $this->assertSame(84.74, SyncChistyBeregCommand::retailPrice(84.74, 84.74, 80));
    }

    public function test_a_card_without_margin_gets_the_market_price_and_keeps_it(): void
    {
        $category = Category::query()->create(['name' => 'Радиаторы', 'slug' => 'stalnye-radiatory', 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        $noMargin = Product::query()->create(['category_id' => $category->id, 'name' => 'Радиатор 21х500х400', 'slug' => 'rad-400', 'sku' => 'BEREG-00102323', 'price' => 84.74, 'is_active' => true]);
        $withMargin = Product::query()->create(['category_id' => $category->id, 'name' => 'Радиатор 33х300х1300', 'slug' => 'rad-1300', 'sku' => 'BEREG-00102061', 'price' => 302.65, 'is_active' => true]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $noMargin->id, 'supplier_article' => '00102323', 'supplier_name' => 'Радиатор 21х500х400',
            'price_byn' => 84.74, 'in_stock' => true, 'match_status' => 'matched', 'match_confidence' => 'maker_code', 'raw' => ['retail' => 84.74],
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $withMargin->id, 'supplier_article' => '00102061', 'supplier_name' => 'Радиатор 33х300х1300',
            'price_byn' => 294.18, 'in_stock' => true, 'match_status' => 'matched', 'match_confidence' => 'maker_code', 'raw' => ['retail' => 302.65],
        ]);

        $this->mock(MarketWebResearchService::class, function (MockInterface $mock) use ($noMargin): void {
            $mock->shouldReceive('research')->once()
                ->withArgs(fn (Product $product, float $quote): bool => $product->id === $noMargin->id && $quote === 84.74)
                ->andReturn(['status' => 'ready', 'offers' => [
                    ['domain' => '21vek.by', 'match' => 'exact', 'price_byn' => 98.4],
                    ['domain' => 'teplodvor.by', 'match' => 'exact', 'price_byn' => 103.2],
                ]]);
        });

        $this->artisan('supplier:chisty-bereg-market-prices')->expectsOutputToContain('metric.chisty_bereg_market.due=1')->assertSuccessful();
        $this->assertSame(84.74, (float) $noMargin->fresh()->price);

        $this->artisan('supplier:chisty-bereg-market-prices', ['--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg_market.priced=1')
            ->assertSuccessful();
        $this->assertSame(100.8, (float) $noMargin->fresh()->price);
        $this->assertSame(100.8, $offer->fresh()->raw['market_price']);
        $this->assertSame(302.65, (float) $withMargin->fresh()->price);

        // Checked recently: the next run researches nothing.
        $this->artisan('supplier:chisty-bereg-market-prices', ['--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg_market.due=0')
            ->assertSuccessful();
    }

    public function test_a_failed_research_leaves_the_card_in_the_queue_and_outage_misses_go_back(): void
    {
        $category = Category::query()->create(['name' => 'Радиаторы', 'slug' => 'stalnye-radiatory', 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Радиатор 22х500х600', 'slug' => 'rad-600', 'sku' => 'BEREG-00102400', 'price' => 90, 'is_active' => true]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $card->id, 'supplier_article' => '00102400', 'supplier_name' => 'Радиатор 22х500х600',
            'price_byn' => 90, 'in_stock' => true, 'match_status' => 'matched', 'raw' => ['retail' => 90],
        ]);
        $this->mock(MarketWebResearchService::class, fn (MockInterface $mock) => $mock->shouldReceive('research')
            ->andReturn(['status' => 'error', 'message' => 'Insufficient Balance', 'offers' => []]));

        $this->artisan('supplier:chisty-bereg-market-prices', ['--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg_market.errors=1')
            ->assertSuccessful();
        $this->assertArrayNotHasKey('market_checked_at', (array) $offer->fresh()->raw);

        // A miss marked during an outage goes back to the queue.
        $offer->forceFill(['raw' => ['retail' => 90, 'market_checked_at' => now()->toDateTimeString(), 'market_price' => null]])->save();
        $this->artisan('supplier:chisty-bereg-market-prices', ['--reset-misses-since' => now()->subHour()->toDateTimeString(), '--limit' => 1])
            ->expectsOutputToContain('metric.chisty_bereg_market.reset=1')
            ->assertSuccessful();
    }
}
