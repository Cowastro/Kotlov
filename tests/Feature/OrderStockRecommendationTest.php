<?php

namespace Tests\Feature;

use App\Filament\Pages\StockDemandAnalytics;
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

    public function test_recommendations_use_non_cancelled_history_and_subtract_current_own_stock(): void
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
        ]);

        $this->orderWithItem('ORD-ANALYTICS-1', $popular, 3, 100, now()->subDays(10));
        $this->orderWithItem('ORD-ANALYTICS-2', $popular, 2, 100, now()->subDays(40));
        $this->orderWithItem('ORD-ANALYTICS-CANCELLED', $popular, 100, 100, now()->subDays(5), 'cancelled');
        $this->orderWithItem('ORD-ANALYTICS-OLD', $old, 1, 80, now()->subDays(220));

        $rows = app(OrderStockRecommendationService::class)->recommendations(180);
        $popularRow = $rows->firstWhere('product_id', $popular->id);
        $oldRow = $rows->firstWhere('product_id', $old->id);

        $this->assertSame(2, $popularRow['orders_all']);
        $this->assertSame(5, $popularRow['quantity_all']);
        $this->assertSame(5, $popularRow['quantity_recent']);
        $this->assertSame(1.0, $popularRow['current_own_stock']);
        $this->assertSame(3, $popularRow['target_stock']);
        $this->assertSame(2, $popularRow['recommended_purchase']);
        $this->assertSame(36.0, $popularRow['stock_coverage_days']);
        $this->assertSame('below_target', $popularRow['stock_state']);
        $this->assertStringContainsString('рекомендуется добавить 2 шт.', $popularRow['explanation']);
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
            ->assertSeeText('Только аналитика: склад автоматически не меняется.');

        $this->actingAs($client)
            ->get($url)
            ->assertForbidden();

        $this->assertSame(0.0, (float) IntegrationProduct::query()->findOrFail(
            IntegrationProduct::query()->where('external_id', 'demand-screen-onec')->value('id'),
        )->stock_quantity);
    }

    private function orderWithItem(
        string $number,
        Product $product,
        int $quantity,
        float $price,
        Carbon $createdAt,
        string $status = 'new',
    ): void {
        $order = Order::query()->create([
            'number' => $number,
            'status' => $status,
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
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
