<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\ChimneyCalculationRequests\Pages\ViewChimneyCalculationRequest;
use App\Models\Category;
use App\Models\ChimneyCalculationRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\ChimneyCalculationRequestTelegramNotifier;
use App\Services\TelegramApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class ChimneyCalculationRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_priced_server_side_and_telegram_confirmation_creates_one_order(): void
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
        $notificationApi->shouldReceive('sendMessage')->once()->withArgs(function ($chatId, $text, $extra) use ($calculation): bool {
            $markup = json_decode($extra['reply_markup'] ?? '', true);

            return (string) $chatId === '12345'
                && str_contains($text, 'Новый запрос на расчёт')
                && str_contains($text, 'Позиций в комплекте:')
                && str_contains($text, 'Итого:')
                && str_contains($text, 'Открыть расчёт в админке')
                && ! str_contains($text, 'Ø120, высота 5 м')
                && ! str_contains($text, '*Состав:*')
                && ($markup['inline_keyboard'][0][0]['callback_data'] ?? null) === 'confirm_chimney:'.$calculation->id;
        })->andReturn(['ok' => true, 'result' => ['message_id' => 55]]);
        (new ChimneyCalculationRequestTelegramNotifier($notificationApi))->notify($calculation);
        $this->assertSame(55, $calculation->fresh()->telegram_message_id);

        $telegram = $this->mock(TelegramApi::class);
        $telegram->shouldReceive('answerCallbackQuery')->twice()->andReturn(['ok' => true]);
        $telegram->shouldReceive('editMessageReplyMarkup')->twice()->andReturn(['ok' => true]);
        $telegram->shouldReceive('sendReply')->twice()->andReturn(['ok' => true]);
        $payload = [
            'callback_query' => [
                'id' => 'callback-1',
                'data' => 'confirm_chimney:'.$calculation->id,
                'from' => ['first_name' => 'Менеджер', 'username' => 'manager'],
                'message' => ['message_id' => 77, 'chat' => ['id' => 12345]],
            ],
        ];

        $this->postJson('/telegram/webhook', $payload)->assertOk()->assertJson(['ok' => true]);
        $this->postJson('/telegram/webhook', $payload)->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $order = Order::with('items')->firstOrFail();
        $this->assertSame('380.00', $order->total);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame($order->id, $calculation->fresh()->order_id);
        $this->assertSame('converted', $calculation->fresh()->status);
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

    public function test_admin_can_open_calculation_with_itemized_bundle(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $calculation = ChimneyCalculationRequest::query()->create([
            'number' => 'CHM-TEST-0001',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291112233',
            'items' => [[
                'name' => 'Труба сэндвич Ø120 мм',
                'quantity' => 2,
                'total' => 400,
            ]],
            'subtotal' => 400,
            'discount_percent' => 5,
            'discount' => 20,
            'total' => 380,
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewChimneyCalculationRequest::class, ['record' => $calculation->getRouteKey()])
            ->assertOk()
            ->assertSee('Труба сэндвич Ø120 мм × 2 — 400.00 BYN');
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
