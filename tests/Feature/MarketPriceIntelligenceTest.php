<?php

namespace Tests\Feature;

use App\Filament\Resources\MarketAnalysis\MarketAnalysisResource;
use App\Filament\Resources\MarketPriceObservations\MarketPriceObservationResource;
use App\Filament\Resources\MarketPriceSources\MarketPriceSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\MarketPriceObservation;
use App\Models\MarketPriceSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketPriceIndicator;
use App\Services\Market\MarketPriceObservationRecorder;
use App\Services\Market\MarketPriceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketPriceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_normalizes_price_and_is_idempotent_for_the_same_source_snapshot(): void
    {
        [$product, $source] = $this->fixture('record');
        $payload = [
            'url' => 'https://example.by/item?utm_source=test',
            'observed_price' => 100,
            'currency' => 'EUR',
            'exchange_rate_to_byn' => 3.4,
            'observed_at' => '2026-10-10 10:00:00',
            'match_confidence' => 0.95,
            'is_confirmed' => true,
            'is_comparable' => true,
        ];

        $first = app(MarketPriceObservationRecorder::class)->record($source, $product, $payload);
        $second = app(MarketPriceObservationRecorder::class)->record($source, $product, [
            ...$payload,
            'observed_price' => 105,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MarketPriceObservation::query()->count());
        $this->assertSame('357.00', $second->fresh()->price_byn);
        $this->assertSame('EUR', $second->fresh()->currency);
    }

    public function test_market_corridor_uses_one_latest_fresh_confirmed_comparable_offer_per_source(): void
    {
        [$product] = $this->fixture('summary', productPrice: 120);
        $asOf = CarbonImmutable::parse('2026-10-10 12:00:00');

        foreach ([90, 100, 110] as $index => $price) {
            $source = $this->source('summary-'.$index);
            $this->record($source, $product, $price - 20, $asOf->subHours(2), suffix: 'older');
            $this->record($source, $product, $price, $asOf->subHour(), suffix: 'latest');
        }

        $staleSource = $this->source('stale', freshnessHours: 2);
        $this->record($staleSource, $product, 40, $asOf->subHours(4));
        $unconfirmedSource = $this->source('unconfirmed');
        $this->record($unconfirmedSource, $product, 300, $asOf->subHour(), confirmed: false);
        $flaggedSource = $this->source('flagged');
        $this->record($flaggedSource, $product, 10, $asOf->subHour(), flags: ['wrong_package']);

        $summary = app(MarketPriceSummary::class)->forProduct($product->fresh(), $asOf);

        $this->assertSame('ready', $summary['status']);
        $this->assertSame('Выше рынка', $summary['label']);
        $this->assertSame(3, $summary['sources_count']);
        $this->assertSame(90.0, $summary['minimum']);
        $this->assertSame(100.0, $summary['median']);
        $this->assertSame(110.0, $summary['maximum']);
        $this->assertSame(20.0, $summary['delta_byn']);
        $this->assertSame(20.0, $summary['delta_percent']);
    }

    public function test_market_summary_is_honest_when_there_are_fewer_than_three_independent_sources(): void
    {
        [$product] = $this->fixture('insufficient', productPrice: 100);
        $asOf = CarbonImmutable::parse('2026-10-10 12:00:00');

        foreach ([90, 110] as $index => $price) {
            $this->record($this->source('insufficient-'.$index), $product, $price, $asOf->subHour());
        }

        $summary = app(MarketPriceSummary::class)->forProduct($product->fresh(), $asOf);

        $this->assertSame('insufficient', $summary['status']);
        $this->assertSame('Недостаточно данных', $summary['label']);
        $this->assertSame(2, $summary['sources_count']);
        $this->assertNull($summary['median']);
        $this->assertStringContainsString('минимум 3', $summary['reason']);
    }

    public function test_manager_can_read_market_evidence_but_cannot_manage_sources_or_observations(): void
    {
        [$product, $source] = $this->fixture('access', productPrice: 120);
        $this->record($source, $product, 100, now()->subHour());
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($manager);
        $this->assertTrue(MarketAnalysisResource::canViewAny());
        $this->assertTrue(MarketPriceObservationResource::canViewAny());
        $this->assertFalse(MarketPriceObservationResource::canCreate());
        $this->assertFalse(MarketPriceSourceResource::canViewAny());

        $this->get(MarketAnalysisResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Рынок и цены')
            ->assertSeeText('Недостаточно данных');
        $this->get(MarketPriceObservationResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText($product->name)
            ->assertSeeText($source->name);
        $this->get(MarketPriceObservationResource::getUrl('create', panel: 'admin'))->assertForbidden();
        $this->get(MarketPriceSourceResource::getUrl('index', panel: 'admin'))->assertForbidden();

        $this->actingAs($admin)
            ->get(MarketPriceSourceResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText($source->name);
    }

    public function test_indicator_explains_ready_and_stale_market_evidence(): void
    {
        [$product] = $this->fixture('indicator', productPrice: 120);
        $asOf = CarbonImmutable::parse('2026-10-10 12:00:00');

        foreach ([90, 100, 110] as $index => $price) {
            $this->record($this->source('indicator-'.$index), $product, $price, $asOf->subHour());
        }

        $indicator = app(MarketPriceIndicator::class)->forProduct($product->fresh(), $asOf);

        $this->assertSame('Выше рынка +20,0%', $indicator['indicator_label']);
        $this->assertStringContainsString('Наша 120,00 BYN', $indicator['indicator_description']);
        $this->assertStringContainsString('медиана 100,00 BYN', $indicator['indicator_description']);
        $this->assertSame('danger', $indicator['indicator_color']);
        $this->assertTrue($indicator['evidence']->every(fn (array $evidence): bool => $evidence['eligible']));

        [$staleProduct, $staleSource] = $this->fixture('stale-indicator');
        $staleSource->update(['freshness_hours' => 1]);
        $this->record($staleSource, $staleProduct, 95, $asOf->subHours(2));

        $stale = app(MarketPriceIndicator::class)->forProduct($staleProduct->fresh(), $asOf);

        $this->assertSame('Недостаточно данных', $stale['indicator_label']);
        $this->assertSame('stale', $stale['evidence']->first()['code']);
        $this->assertNotNull($stale['last_checked_at']);
        $this->assertSame('warning', $stale['indicator_color']);
    }

    public function test_order_indicator_aggregates_products_without_inventing_market_conclusions(): void
    {
        [$pricedProduct] = $this->fixture('order-priced', productPrice: 120);
        [$unknownProduct] = $this->fixture('order-unknown', productPrice: 80);

        foreach ([90, 100, 110] as $index => $price) {
            $this->record($this->source('order-'.$index), $pricedProduct, $price, now()->subHour());
        }

        $order = Order::query()->create([
            'number' => 'MARKET-ORDER-1',
            'status' => 'new',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 200,
            'total' => 200,
        ]);
        foreach ([$pricedProduct, $unknownProduct] as $product) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'price' => $product->price,
                'quantity' => 1,
                'total' => $product->price,
            ]);
        }

        $indicator = app(MarketPriceIndicator::class)->forOrder($order->fresh());

        $this->assertSame('Выше рынка: 1', $indicator['label']);
        $this->assertSame(1, $indicator['ready_count']);
        $this->assertSame(1, $indicator['above_count']);
        $this->assertSame(1, $indicator['insufficient_count']);
        $this->assertStringContainsString('проверено 1 из 2 позиций', $indicator['description']);
        $this->assertSame('Недостаточно данных', $indicator['rows'][1]['indicator']['indicator_label']);
    }

    public function test_market_details_panel_renders_evidence_links_and_check_time(): void
    {
        [$product] = $this->fixture('details-panel', productPrice: 120);
        foreach ([90, 100, 110] as $index => $price) {
            $this->record($this->source('details-'.$index), $product, $price, now()->subHour());
        }

        $indicator = app(MarketPriceIndicator::class)->forProduct($product->fresh());
        $html = view('filament.market.price-details', [
            'overview' => [
                'label' => $indicator['indicator_label'],
                'description' => $indicator['indicator_description'],
            ],
            'rows' => collect([[
                'item' => null,
                'product' => $product,
                'indicator' => $indicator,
            ]]),
        ])->render();

        $this->assertStringContainsString('Рыночные предложения не являются закупочными ценами', $html);
        $this->assertStringContainsString('Проверено', $html);
        $this->assertStringContainsString('https://market-source-details-0.example/offer', $html);
        $this->assertStringContainsString('Учитывается', $html);
    }

    public function test_product_and_order_workbenches_show_the_same_market_indicator(): void
    {
        [$product] = $this->fixture('workbench', productPrice: 120);
        foreach ([90, 100, 110] as $index => $price) {
            $this->record($this->source('workbench-'.$index), $product, $price, now()->subHour());
        }

        $order = Order::query()->create([
            'number' => 'MARKET-WORKBENCH-1',
            'status' => 'new',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375290000001',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 120,
            'total' => 120,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 120,
            'quantity' => 1,
            'total' => 120,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);

        $this->get(ProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Рынок')
            ->assertSeeText('Выше рынка +20,0%');

        $this->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Рынок')
            ->assertSeeText('Выше рынка: 1');
    }

    /** @return array{Product, MarketPriceSource} */
    private function fixture(string $suffix, float $productPrice = 100): array
    {
        $category = Category::query()->create([
            'name' => 'Категория '.$suffix,
            'slug' => 'market-category-'.$suffix,
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Рыночный товар '.$suffix,
            'slug' => 'market-product-'.$suffix,
            'sku' => 'MARKET-'.strtoupper($suffix),
            'price' => $productPrice,
            'currency' => 'BYN',
        ]);

        return [$product, $this->source($suffix)];
    }

    private function source(string $suffix, int $freshnessHours = 48): MarketPriceSource
    {
        return MarketPriceSource::query()->create([
            'code' => 'market-source-'.$suffix,
            'name' => 'Источник '.$suffix,
            'kind' => 'competitor',
            'collection_method' => 'manual',
            'currency' => 'BYN',
            'region' => 'Беларусь',
            'freshness_hours' => $freshnessHours,
            'minimum_match_confidence' => 0.85,
            'is_active' => true,
        ]);
    }

    private function record(
        MarketPriceSource $source,
        Product $product,
        float $price,
        $observedAt,
        bool $confirmed = true,
        array $flags = [],
        string $suffix = 'offer',
    ): MarketPriceObservation {
        return app(MarketPriceObservationRecorder::class)->record($source, $product, [
            'url' => 'https://'.$source->code.'.example/'.$suffix,
            'external_name' => 'Сопоставимое предложение',
            'observed_price' => $price,
            'currency' => 'BYN',
            'exchange_rate_to_byn' => 1,
            'availability_status' => 'in_stock',
            'match_method' => 'exact_model',
            'match_confidence' => 0.95,
            'is_confirmed' => $confirmed,
            'is_comparable' => true,
            'validation_flags' => $flags,
            'observed_at' => $observedAt,
        ]);
    }
}
