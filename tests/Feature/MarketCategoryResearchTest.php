<?php

namespace Tests\Feature;

use App\Filament\Pages\MarketCategoryResearch as MarketCategoryResearchPage;
use App\Models\Category;
use App\Models\MarketPriceSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketCategoryResearch;
use App\Services\Market\MarketPriceObservationRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketCategoryResearchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_category_research_combines_honest_price_trends_availability_leader_and_confirmed_demand(): void
    {
        CarbonImmutable::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create([
            'name' => 'Дымоходы из нержавеющей стали',
            'slug' => 'market-category-research',
            'parent_id' => 0,
        ]);
        $products = collect([1, 2])->map(fn (int $number): Product => Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Дымоход '.$number,
            'slug' => 'market-category-product-'.$number,
            'sku' => 'MARKET-CATEGORY-'.$number,
            'price' => 150 * $number,
            'currency' => 'BYN',
        ]));
        $sources = collect([
            $this->source('leader', 'Ценовой лидер'),
            $this->source('middle', 'Средний источник'),
            $this->source('high', 'Дорогой источник'),
        ]);

        foreach ($products as $productIndex => $product) {
            foreach ($sources as $sourceIndex => $source) {
                $current = (90 + $sourceIndex * 10) * ($productIndex + 1);
                $this->record($source, $product, $current / 1.1, now()->subDays(20), 'in_stock', 'old');
                $this->record(
                    $source,
                    $product,
                    $current,
                    now()->subHour(),
                    $sourceIndex === 2 ? 'out_of_stock' : 'in_stock',
                    'current',
                );
            }
        }

        $this->orderWithItem('CATEGORY-DEMAND-CONFIRMED', $products->first(), 3, 'confirmed');
        $this->orderWithItem('CATEGORY-DEMAND-UNPAID', $products->first(), 50, 'new');
        $this->orderWithItem('CATEGORY-DEMAND-CANCELLED', $products->first(), 70, 'cancelled');

        $row = app(MarketCategoryResearch::class)->rows()->sole();

        $this->assertSame($category->id, $row['category_id']);
        $this->assertSame(2, $row['fresh_products_count']);
        $this->assertSame(6, $row['fresh_offers_count']);
        $this->assertSame(3, $row['fresh_sources_count']);
        $this->assertSame(66.7, $row['availability_percent']);
        $this->assertNull($row['trends'][7]['percent']);
        $this->assertSame(10.0, $row['trends'][30]['percent']);
        $this->assertSame(6, $row['trends'][30]['pairs']);
        $this->assertSame(10.0, $row['trends'][90]['percent']);
        $this->assertSame('Ценовой лидер', $row['price_leader']['name']);
        $this->assertSame(2, $row['price_leader']['wins']);
        $this->assertSame(1, $row['demand_orders_90d']);
        $this->assertSame(3, $row['demand_quantity_90d']);
        $this->assertSame('Спрос подтверждён', $row['opportunity']['label']);
    }

    public function test_category_research_requires_three_historical_pairs_and_is_manager_read_only(): void
    {
        CarbonImmutable::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create([
            'name' => 'Категория с малым количеством данных',
            'slug' => 'market-category-insufficient',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с одной парой',
            'slug' => 'market-category-insufficient-product',
            'sku' => 'MARKET-CATEGORY-INSUFFICIENT',
            'price' => 100,
            'currency' => 'BYN',
        ]);
        $source = $this->source('insufficient', 'Один источник');
        $this->record($source, $product, 90, now()->subDays(5), 'in_stock', 'old');
        $this->record($source, $product, 100, now()->subHour(), 'in_stock', 'current');

        $row = app(MarketCategoryResearch::class)->rows()->sole();
        $this->assertNull($row['trends'][7]['percent']);
        $this->assertSame(1, $row['trends'][7]['pairs']);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $url = MarketCategoryResearchPage::getUrl(panel: 'admin');

        $this->actingAs($manager)
            ->get($url)
            ->assertOk()
            ->assertSeeText('Рынок по категориям')
            ->assertSeeText($category->name)
            ->assertSeeText('Нет данных')
            ->assertSeeText('Спрос не подтверждён')
            ->assertSeeText('Цены автоматически не меняются');

        $this->actingAs($client)->get($url)->assertForbidden();
    }

    private function source(string $code, string $name): MarketPriceSource
    {
        return MarketPriceSource::query()->create([
            'code' => 'category-'.$code,
            'name' => $name,
            'kind' => 'competitor',
            'collection_method' => 'manual',
            'currency' => 'BYN',
            'region' => 'Беларусь',
            'freshness_hours' => 48,
            'minimum_match_confidence' => 0.85,
            'is_active' => true,
        ]);
    }

    private function record(
        MarketPriceSource $source,
        Product $product,
        float $price,
        $observedAt,
        string $availability,
        string $suffix,
    ): void {
        app(MarketPriceObservationRecorder::class)->record($source, $product, [
            'url' => 'https://'.$source->code.'.example/'.$product->id.'/'.$suffix,
            'external_name' => $product->name,
            'observed_price' => $price,
            'currency' => 'BYN',
            'exchange_rate_to_byn' => 1,
            'availability_status' => $availability,
            'match_method' => 'exact_model',
            'match_confidence' => 0.95,
            'is_confirmed' => true,
            'is_comparable' => true,
            'observed_at' => $observedAt,
        ]);
    }

    private function orderWithItem(string $number, Product $product, int $quantity, string $status): void
    {
        $order = Order::query()->create([
            'number' => $number,
            'status' => $status,
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100 * $quantity,
            'total' => 100 * $quantity,
        ]);
        $order->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->saveQuietly();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 100,
            'quantity' => $quantity,
            'total' => 100 * $quantity,
        ]);
    }
}
