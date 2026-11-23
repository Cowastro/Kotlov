<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatusSmsTest extends TestCase
{
    use RefreshDatabase;

    private const SMS_URL = 'https://app.sms.by/api/v1/sendQuickSMS';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms_by.enabled' => true,
            'services.sms_by.base_url' => 'https://app.sms.by',
            'services.sms_by.token' => 'test-token',
            'services.sms_by.alphaname_id' => '42',
        ]);
        Http::preventStrayRequests();
    }

    public function test_order_lifecycle_sends_the_buyer_each_status_and_includes_the_tracking_number_on_handover(): void
    {
        $sent = [];
        Http::fake([
            self::SMS_URL => function (Request $request) use (&$sent) {
                $this->assertSame('375291110000', $request['phone']);
                $this->assertSame('42', $request['alphaname_id']);
                $sent[] = $request['message'];

                return Http::response(['sms_id' => 'order-test-'.count($sent)]);
            },
        ]);

        $order = $this->order();
        Http::assertNothingSent();
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->transitionTo('checking');
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->transitionTo('confirmed');
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->transitionTo('processing');
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->forceFill(['evropochta_tracking_number' => 'BY080027046773'])->save();
        app(OrderWorkflow::class)->advance($order, null);
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $this->assertTrue($order->fresh()->markDeliveryRefused('Клиент отказался при получении.'));
        $order->fresh()->transitionTo('cancelled');

        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $this->artisan('orders:send-status-sms')->assertSuccessful();

        $this->assertSame([
            'KOTLOV: ORD-2026-0010 принят.',
            'KOTLOV: ORD-2026-0010 проверяем наличие.',
            'KOTLOV: ORD-2026-0010 принят в обработку. Состав и сроки — в письме или у менеджера.',
            'KOTLOV: ORD-2026-0010 комплектация.',
            'KOTLOV: ORD-2026-0010 передан в доставку. Трек BY080027046773.',
            'KOTLOV: ORD-2026-0010 отменён.',
        ], $sent);
        $this->assertDatabaseHas('order_status_sms', [
            'order_id' => $order->id, 'order_status' => 'cancelled',
            'state' => 'accepted', 'sms_id' => 'order-test-6',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('СМС покупателю')
            ->assertSee('Принято SMS.BY')
            ->assertSee('KOTLOV: ORD-2026-0010 отменён.');
    }

    public function test_a_manually_created_order_in_processing_gets_one_acceptance_sms_and_pickup_uses_its_own_text(): void
    {
        Http::fake([self::SMS_URL => Http::response(['sms_id' => 'order-test'])]);
        $order = $this->order(['status' => 'processing', 'delivery_type' => 'pickup']);
        $this->assertSame(['new'], $order->statusSms()->pluck('order_status')->all());
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->transitionTo('shipped');
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $order->transitionTo('delivered');
        $this->artisan('orders:send-status-sms')->assertSuccessful();

        $this->assertSame([
            'KOTLOV: ORD-2026-0010 принят.',
            'KOTLOV: ORD-2026-0010 готов к самовывозу.',
            'KOTLOV: ORD-2026-0010 доставлен.',
        ], Http::recorded()->map(fn (array $entry): string => $entry[0]['message'])->all());
    }

    public function test_rollback_and_marketplace_import_do_not_send_and_uncertain_sends_are_not_retried(): void
    {
        Http::fake([self::SMS_URL => Http::failedConnection()]);
        DB::beginTransaction();
        $this->order();
        DB::rollBack();
        $this->assertDatabaseCount('order_status_sms', 0);

        $this->order(['number' => 'DBY-100']);
        $this->order(['number' => 'ORD-NO-PHONE', 'customer_phone' => 'Не указан']);
        $order = $this->order();
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $this->artisan('orders:send-status-sms')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('order_status_sms', 2);
        $this->assertDatabaseHas('order_status_sms', ['order_id' => $order->id, 'state' => 'unconfirmed']);
        $this->assertDatabaseHas('order_status_sms', ['state' => 'no_phone']);
        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_old_stage_sms_are_skipped_when_order_advanced_before_the_worker_runs(): void
    {
        Http::fake([self::SMS_URL => Http::response(['sms_id' => 'latest-only'])]);
        $order = $this->order();
        $order->transitionTo('processing');
        $order->transitionTo('shipped');
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        $this->artisan('orders:send-status-sms')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('order_status_sms', ['order_id' => $order->id, 'order_status' => 'processing', 'state' => 'outdated']);
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create($overrides + [
            'number' => 'ORD-2026-0010',
            'status' => 'new',
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '8 (029) 111-00-00',
            'delivery_type' => 'evropochta',
            'payment_type' => 'evropochta_cod',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}
