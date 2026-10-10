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
use App\Services\Orders\PurchasePlanManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasePlanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_creates_an_idempotent_snapshot_plan_and_confirms_it_separately(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [$source, $offer, $manager] = $this->demandFixture();
        $recommendations = app(OrderStockRecommendationService::class)->recommendations(180, $source->code);
        $stockBefore = (float) $offer->stock_quantity;

        $first = app(PurchasePlanManager::class)->createDraft($recommendations, $source, 180, $manager);
        $second = app(PurchasePlanManager::class)->createDraft($recommendations, $source, 180, $manager);
        $plan = $first['plan']->fresh(['items', 'statusHistories']);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($plan->id, $second['plan']->id);
        $this->assertSame('draft', $plan->status);
        $this->assertSame(1, $plan->items_count);
        $this->assertSame('4.000', $plan->total_quantity);
        $this->assertSame('48.00', $plan->purchase_total);
        $this->assertSame('12.00', $plan->items->first()->unit_purchase_price);
        $this->assertSame(4, $plan->items->first()->planned_quantity);
        $this->assertSame('draft', $plan->statusHistories->first()->status_to);
        $this->assertDatabaseCount('purchase_plans', 1);
        $this->assertSame($stockBefore, (float) $offer->fresh()->stock_quantity);

        $confirmed = app(PurchasePlanManager::class)->confirm($plan, $manager);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame($manager->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertDatabaseHas('purchase_plan_status_histories', [
            'purchase_plan_id' => $plan->id,
            'status_from' => 'draft',
            'status_to' => 'confirmed',
        ]);
        $this->assertSame($stockBefore, (float) $offer->fresh()->stock_quantity);
    }

    public function test_confirmation_is_blocked_when_current_stock_changed_after_draft(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [$source, $offer, $manager] = $this->demandFixture();
        $plan = app(PurchasePlanManager::class)->createDraft(
            app(OrderStockRecommendationService::class)->recommendations(180, $source->code),
            $source,
            180,
            $manager,
        )['plan'];

        $offer->update(['stock_quantity' => 4, 'stock_confirmed_at' => now()]);

        try {
            app(PurchasePlanManager::class)->confirm($plan->fresh('items'), $manager);
            $this->fail('Confirmation must require a fresh plan after stock changes.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('изменились', collect($exception->errors())->flatten()->first());
        }

        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertSame(4.0, (float) $offer->fresh()->stock_quantity);
    }

    public function test_stock_screen_exposes_selection_and_safe_plan_controls_to_manager(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [, , $manager] = $this->demandFixture();

        $this->actingAs($manager)
            ->get(StockDemandAnalytics::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSeeText('План закупки')
            ->assertSeeText('Создать черновик')
            ->assertSeeText('Черновик не меняет остатки');

        Livewire::actingAs($manager)
            ->test(StockDemandAnalytics::class)
            ->call('selectRecommended')
            ->assertSet('selectedProductIds', fn (array $ids): bool => count($ids) === 1)
            ->call('createPurchasePlan');

        $this->assertDatabaseHas('purchase_plans', [
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseCount('purchase_plan_items', 1);
    }

    /** @return array{IntegrationSource,IntegrationProduct,User} */
    private function demandFixture(): array
    {
        $category = Category::query()->create([
            'name' => 'Планы закупки',
            'slug' => 'purchase-plans',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Дымоход для плана',
            'slug' => 'purchase-plan-chimney',
            'sku' => 'PLAN-CHIMNEY',
        ]);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $settings = $source->settings;
        $settings['price_tax_mode'] = IntegrationSource::PRICE_TAX_EXCLUSIVE;
        $settings['vat_rate'] = 20;
        $source->update(['settings' => $settings]);
        $offer = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'purchase-plan-offer',
            'name' => $product->name,
            'price' => 10,
            'stock_quantity' => 1,
            'match_status' => 'matched',
            'stock_confirmed_at' => now(),
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-PURCHASE-PLAN',
            'status' => 'confirmed',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 500,
            'total' => 500,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 100,
            'quantity' => 5,
            'total' => 500,
        ]);

        return [$source, $offer, User::factory()->create(['role' => 'manager', 'is_active' => true])];
    }
}
