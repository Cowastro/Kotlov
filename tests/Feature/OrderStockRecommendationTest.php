<?php

namespace Tests\Feature;

use App\Filament\Pages\StockDemandAnalytics;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderStockRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderStockRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recommendations_use_confirmed_demand_and_subtract_current_own_stock(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create([
            'name' => 'Категория аналитики',
            'slug' => 'stock-analysis',
            'parent_id' => 0,
        ]);
        $popular = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Популярная позиция',
            'slug' => 'popular-stock-item',
            'sku' => 'POPULAR-1',
        ]);
        $old = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Старая продажа',
            'slug' => 'old-stock-item',
            'sku' => 'OLD-1',
        ]);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $popular->id,
            'external_id' => 'popular-onec',
            'name' => $popular->name,
            'price' => 50,
            'stock_quantity' => 1,
            'match_status' => 'matched',
            'stock_confirmed_at' => now(),
        ]);

        $this->orderWithItem('ORD-ANALYTICS-1', $popular, 3, 100, now()->subDays(10));
        $this->orderWithItem('ORD-ANALYTICS-2', $popular, 2, 100, now()->subDays(40));
        $this->orderWithItem('ORD-ANALYTICS-CANCELLED', $popular, 100, 100, now()->subDays(5), 'cancelled');
        $this->orderWithItem('ORD-ANALYTICS-OLD', $old, 1, 80, now()->subDays(220));
        $this->orderWithItem('ORD-ANALYTICS-LEAD', $popular, 50, 100, now()->subDays(5), 'new');

        $rows = app(OrderStockRecommendationService::class)->recommendations(180);
        $popularRow = $rows->firstWhere('product_id', $popular->id);
        $oldRow = $rows->firstWhere('product_id', $old->id);

        $this->assertSame(2, $popularRow['orders_all']);
        $this->assertSame(5, $popularRow['quantity_all']);
        $this->assertSame(5, $popularRow['quantity_recent']);
        $this->assertSame(1, $popularRow['interest_orders_recent']);
        $this->assertSame(50, $popularRow['interest_quantity_recent']);
        $this->assertSame(1.0, $popularRow['current_own_stock']);
        $this->assertSame(3, $popularRow['target_stock']);
        $this->assertSame(2, $popularRow['recommended_purchase']);
        $this->assertSame(36.0, $popularRow['stock_coverage_days']);
        $this->assertSame('below_target', $popularRow['stock_state']);
        $this->assertStringContainsString('рекомендуется добавить 2 шт.', $popularRow['explanation']);
        $this->assertStringContainsString('в закупку не учитываются', $popularRow['explanation']);
        $this->assertSame(1, $oldRow['orders_all']);
        $this->assertSame(0, $oldRow['quantity_recent']);
        $this->assertSame(0, $oldRow['recommended_purchase']);

        $this->artisan('orders:recommend-stock', ['--days' => 180, '--top' => 10])
            ->expectsOutputToContain('Рекомендации рассчитаны без изменения')
            ->assertSuccessful();
    }

    public function test_manager_can_use_read_only_stock_demand_screen_while_client_cannot(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create([
            'name' => 'Категория экрана спроса',
            'slug' => 'stock-demand-screen',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для пополнения склада',
            'slug' => 'stock-demand-product',
            'sku' => 'DEMAND-1',
        ]);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'demand-screen-onec',
            'name' => $product->name,
            'price' => 50,
            'stock_quantity' => 0,
            'match_status' => 'matched',
            'stock_confirmed_at' => now(),
        ]);
        $this->orderWithItem('ORD-DEMAND-SCREEN', $product, 4, 100, now()->subDays(10));

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $url = StockDemandAnalytics::getUrl(panel: 'admin');

        $this->actingAs($manager)
            ->get($url)
            ->assertOk()
            ->assertSeeText('Спрос и собственный склад')
            ->assertSeeText('Товар для пополнения склада')
            ->assertSeeText('рекомендуется добавить 4 шт.')
            ->assertSeeText('Формула цели использует только оплаченные или подтверждённые менеджером заказы')
            ->assertSeeText('Подтверждено 1С')
            ->assertSeeText('Новые неоплаченные заявки показаны отдельно');

        $this->actingAs($client)
            ->get($url)
            ->assertForbidden();

        $this->assertSame(0.0, (float) IntegrationProduct::query()->findOrFail(
            IntegrationProduct::query()->where('external_id', 'demand-screen-onec')->value('id'),
        )->stock_quantity);
    }

    public function test_missing_or_stale_stock_evidence_never_becomes_a_zero_stock_purchase_recommendation(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create([
            'name' => 'Контроль достоверности склада',
            'slug' => 'stock-evidence',
            'parent_id' => 0,
        ]);
        $notLinked = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Шина Varfix VM36302 без привязки',
            'slug' => 'stock-not-linked',
            'sku' => 'NOT-LINKED',
        ]);
        $stale = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Устаревший остаток',
            'slug' => 'stock-stale',
            'sku' => 'STALE-STOCK',
        ]);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $stale->id,
            'external_id' => 'stale-onec-offer',
            'name' => $stale->name,
            'price' => 40,
            'stock_quantity' => 0,
            'match_status' => 'matched',
            'stock_confirmed_at' => now()->subHour(),
        ]);
        $this->orderWithItem('ORD-NOT-LINKED', $notLinked, 3, 100, now()->subDays(5));
        $this->orderWithItem('ORD-STALE-STOCK', $stale, 2, 100, now()->subDays(5));

        $rows = app(OrderStockRecommendationService::class)->recommendations(180);
        $notLinkedRow = $rows->firstWhere('product_id', $notLinked->id);
        $staleRow = $rows->firstWhere('product_id', $stale->id);

        $this->assertNull($notLinkedRow['current_own_stock']);
        $this->assertNull($notLinkedRow['recommended_purchase']);
        $this->assertSame('not_linked', $notLinkedRow['stock_data_status']);
        $this->assertNull($notLinkedRow['stock_integration_product_id']);
        $this->assertStringContainsString('Решение о закупке заблокировано', $notLinkedRow['explanation']);
        $this->assertSame(0.0, $staleRow['current_own_stock']);
        $this->assertNull($staleRow['recommended_purchase']);
        $this->assertSame('stale', $staleRow['stock_data_status']);
        $this->assertSame(
            IntegrationProduct::query()->where('external_id', 'stale-onec-offer')->value('id'),
            $staleRow['stock_integration_product_id'],
        );

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $expectedSearchUrl = IntegrationProductResource::getUrl('index', [
            'tab' => 'all',
            'search' => 'VM36302',
        ]);
        $expectedEditUrl = IntegrationProductResource::getUrl('edit', [
            'record' => $staleRow['stock_integration_product_id'],
        ]);
        $this->actingAs($manager)
            ->get(StockDemandAnalytics::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Нет привязки к 1С')
            ->assertSeeText('Данные остатка устарели')
            ->assertSeeText('После проверки')
            ->assertSeeText('Найти и привязать')
            ->assertSeeText('Открыть связь 1С')
            ->assertSee($expectedSearchUrl)
            ->assertSee($expectedEditUrl);

        $this->assertStringContainsString('search=VM36302', $expectedSearchUrl);
        $this->assertStringNotContainsString('tableSearch=', $expectedSearchUrl);
    }

    public function test_new_unpaid_legacy_order_is_interest_only_while_paid_new_order_counts_as_demand(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $category = Category::query()->create(['name' => 'Старые заявки', 'slug' => 'legacy-leads', 'parent_id' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар из старой заявки',
            'slug' => 'legacy-lead-product',
            'sku' => 'LEGACY-LEAD',
        ]);

        $this->orderWithItem('ORD-LEGACY-NEW', $product, 12, 100, now()->subDays(20), 'new');
        $this->orderWithItem('ORD-PAID-NEW', $product, 2, 100, now()->subDays(10), 'new', 'paid');

        $row = app(OrderStockRecommendationService::class)->recommendations(180)->firstWhere('product_id', $product->id);

        $this->assertSame(1, $row['orders_recent']);
        $this->assertSame(2, $row['quantity_recent']);
        $this->assertSame(1, $row['interest_orders_recent']);
        $this->assertSame(12, $row['interest_quantity_recent']);
        $this->assertSame(2, $row['target_stock']);
        $this->assertNull($row['recommended_purchase']);
    }

    private function orderWithItem(
        string $number,
        Product $product,
        int $quantity,
        float $price,
        Carbon $createdAt,
        string $status = 'confirmed',
        string $paymentStatus = 'pending',
    ): void {
        $order = Order::query()->create([
            'number' => $number,
            'status' => $status,
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => $paymentStatus,
            'subtotal' => $price * $quantity,
            'total' => $price * $quantity,
        ]);
        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => $price,
            'quantity' => $quantity,
            'total' => $price * $quantity,
        ]);
        $item->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }
}
