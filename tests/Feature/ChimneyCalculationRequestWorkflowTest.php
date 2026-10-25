<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Pages\ChimneyCalculator;
use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use App\Models\Category;
use App\Models\ChimneyCalculationRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\ChimneyCalculationRequestService;
use App\Services\ChimneyCalculationRequestTelegramNotifier;
use App\Services\TelegramApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class ChimneyCalculationRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_priced_server_side_and_notification_opens_unified_calculator(): void
    {
        Event::fake([NewOrderCreated::class]);
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.contact_requests_chat_id' => '12345',
            'shop.chimney_bundle_discount.chimney_category_slugs' => ['dymohody', 'truby-sendvich'],
        ]);
        $product = $this->chimneyProduct();

        $this->get(route('chimney-selector'))->assertOk();
        $token = session('chimney_selector_token');
        $this->mock(ChimneyCalculationRequestTelegramNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notify')->once();
        });

        $this->post(route('chimney-calculation-requests.store'), [
            'customer_name' => 'Иван',
            'customer_phone' => '+375291112233',
            'source_url' => route('chimney-selector'),
            'configuration' => 'Ø120, высота 5 м',
            'selector_token' => $token,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect()->assertSessionHas('success');

        $calculation = ChimneyCalculationRequest::firstOrFail();
        $this->assertSame('pending', $calculation->status);
        $this->assertSame('400.00', $calculation->subtotal);
        $this->assertSame('20.00', $calculation->discount);
        $this->assertSame('380.00', $calculation->total);
        $this->assertSame('self_selection', $calculation->promotion_mode);

        $notificationApi = \Mockery::mock(TelegramApi::class);
        $notificationApi->shouldReceive('sendMessage')->once()->withArgs(function ($chatId, $text, $extra = []) use ($calculation): bool {
            return (string) $chatId === '12345'
                && str_contains($text, 'Новый запрос на расчёт')
                && str_contains($text, 'Позиций в комплекте:')
                && str_contains($text, 'Итого:')
                && str_contains($text, 'Открыть заявку в калькуляторе')
                && str_contains($text, 'calculation='.$calculation->id)
                && ! str_contains($text, 'Ø120, высота 5 м')
                && ! str_contains($text, '*Состав:*')
                && ! isset($extra['reply_markup']);
        })->andReturn(['ok' => true, 'result' => ['message_id' => 55]]);
        (new ChimneyCalculationRequestTelegramNotifier($notificationApi))->notify($calculation);
        $this->assertSame(55, $calculation->fresh()->telegram_message_id);

        $result = app(ChimneyCalculationRequestService::class)->confirm($calculation, 'Менеджер CRM');
        app(ChimneyCalculationRequestService::class)->confirm($calculation->fresh(), 'Менеджер CRM');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $order = Order::with('items')->firstOrFail();
        $this->assertSame('380.00', $order->total);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame($order->id, $calculation->fresh()->order_id);
        $this->assertSame('converted', $calculation->fresh()->status);
        $this->assertTrue($result['created']);
        Event::assertDispatchedTimes(NewOrderCreated::class, 1);
    }

    public function test_request_rejects_a_stale_selector_token(): void
    {
        $product = $this->chimneyProduct();

        $this->from(route('chimney-selector'))->post(route('chimney-calculation-requests.store'), [
            'customer_name' => 'Иван',
            'customer_phone' => '+375291112233',
            'selector_token' => str_repeat('x', 48),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertRedirect(route('chimney-selector'))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('chimney_calculation_requests', 0);
    }

    public function test_admin_manages_requests_inside_the_unified_calculator(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = $this->chimneyProduct();
        $calculation = ChimneyCalculationRequest::query()->create([
            'number' => 'CHM-TEST-0001',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291112233',
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => 200,
                'quantity' => 2,
                'total' => 400,
                'chimney_selector_selected' => true,
            ]],
            'subtotal' => 400,
            'discount_percent' => 5,
            'discount' => 20,
            'total' => 380,
        ]);

        $this->actingAs($admin);

        $this->get(ChimneyCalculator::getUrl(['requests' => 1]))
            ->assertOk()
            ->assertSee('Все клиентские заявки и расчёты менеджеров')
            ->assertSee($calculation->number)
            ->assertSee('Тестовый клиент');

        $this->get(ChimneyCalculator::getUrl(['calculation' => $calculation->id]))
            ->assertOk()
            ->assertSee($calculation->number)
            ->assertSee('Тестовый клиент')
            ->assertSee('Создать заказ')
            ->assertSee('manager_mode=1', false)
            ->assertSee('calculation='.$calculation->id, false)
            ->assertDontSee('Схема сборки дымохода');

        $this->get(ChimneyCalculationRequestResource::getUrl('index'))
            ->assertRedirect(ChimneyCalculator::getUrl(['requests' => 1]));
        $this->get(ChimneyCalculationRequestResource::getUrl('view', ['record' => $calculation]))
            ->assertRedirect(ChimneyCalculator::getUrl(['calculation' => $calculation->id]));
    }

    public function test_manager_can_create_and_revise_a_calculation_in_the_standalone_calculator(): void
    {
        config(['shop.chimney_bundle_discount.chimney_category_slugs' => ['dymohody', 'truby-sendvich']]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = $this->chimneyProduct();
        $this->actingAs($admin);

        $this->get(ChimneyCalculator::getUrl())->assertOk()->assertSee('Калькулятор дымохода');
        $this->get(route('chimney-selector', ['manager_mode' => 1]))
            ->assertOk()
            ->assertSee('Новый расчёт дымохода');

        $token = session('chimney_selector_token');
        $created = $this->postJson(route('admin.chimney-calculator.save'), [
            'customer_name' => 'Клиент менеджера',
            'customer_phone' => '+375291234567',
            'source_url' => route('chimney-selector', ['kit' => 1, 'items' => $product->id.':2']),
            'configuration' => 'Прямая схема, высота 5 м',
            'selector_token' => $token,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJson(['success' => true]);

        $calculation = ChimneyCalculationRequest::query()->findOrFail($created->json('request_id'));
        $this->assertSame('400.00', $calculation->subtotal);
        $this->assertNull($calculation->telegram_notified_at);

        $this->postJson(route('admin.chimney-calculator.save'), [
            'calculation_id' => $calculation->id,
            'customer_name' => 'Клиент менеджера',
            'customer_phone' => '+375291234567',
            'source_url' => route('chimney-selector', ['kit' => 1, 'items' => $product->id.':3']),
            'configuration' => 'Прямая схема, высота 6 м',
            'selector_token' => $token,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertOk()->assertJson(['success' => true, 'request_id' => $calculation->id]);

        $calculation->refresh();
        $this->assertSame(3, $calculation->items[0]['quantity']);
        $this->assertSame('600.00', $calculation->subtotal);
        $this->assertSame('570.00', $calculation->total);
    }

    private function chimneyProduct(): Product
    {
        $root = Category::query()->firstOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0]);
        $category = Category::query()->firstOrCreate(['slug' => 'truby-sendvich'], ['name' => 'Трубы сэндвич', 'parent_id' => $root->id]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Труба сэндвич Ø120 мм',
            'slug' => 'workflow-chimney-pipe',
            'sku' => 'WORKFLOW-PIPE',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $supplier = Supplier::query()->create(['code' => 'workflow-supplier', 'name' => 'Тестовый поставщик', 'is_active' => true]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'WORKFLOW-PIPE',
            'price_byn' => 100,
            'match_confidence' => 'manual_price_list',
            'stock_status' => 'unknown',
        ]);

        return $product->load(['category', 'supplierProducts.supplier']);
    }
}
