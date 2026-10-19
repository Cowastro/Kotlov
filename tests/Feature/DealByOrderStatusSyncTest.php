<?php

namespace Tests\Feature;

use App\Models\DealByOrderInbox;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DealByOrderStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.deal_by.base_url', 'https://my.deal.test/api/v1');
        config()->set('services.deal_by.token', 'secret-test-token');
        config()->set('services.deal_by.connect_timeout', 1);
        config()->set('services.deal_by.timeout', 1);
    }

    public function test_accepting_a_deal_by_order_updates_the_remote_status(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/set_status' => Http::response(['processed_ids' => [8181194]]),
        ]);
        [$order, $inbox] = $this->dealByOrder();

        $this->assertTrue($order->transitionTo('confirmed', 'Заказ принят менеджером.'));

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('received', $inbox->fresh()->remote_status);
        $this->assertSame('received', $inbox->fresh()->last_pushed_status);
        $this->assertNotNull($inbox->fresh()->last_pushed_at);
        $this->assertNull($inbox->fresh()->last_push_error);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://my.deal.test/api/v1/orders/set_status'
            && $request->hasHeader('Authorization', 'Bearer secret-test-token')
            && $request['status'] === 'received'
            && $request['ids'] === ['8181194']);
    }

    public function test_remote_failure_does_not_roll_back_the_manager_action(): void
    {
        Http::fake([
            'https://my.deal.test/api/v1/orders/set_status' => Http::response(['error' => 'temporary failure'], 503),
        ]);
        [$order, $inbox] = $this->dealByOrder();

        $this->assertTrue($order->transitionTo('confirmed', 'Заказ принят менеджером.'));

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('pending', $inbox->fresh()->remote_status);
        $this->assertNull($inbox->fresh()->last_pushed_at);
        $this->assertNotNull($inbox->fresh()->last_push_error);
    }

    public function test_regular_order_status_change_does_not_call_deal_by(): void
    {
        Http::preventStrayRequests();
        $order = $this->order();

        $this->assertTrue($order->transitionTo('confirmed', 'Обычный заказ принят.'));

        Http::assertNothingSent();
    }

    /** @return array{Order, DealByOrderInbox} */
    private function dealByOrder(): array
    {
        $order = $this->order();
        $inbox = DealByOrderInbox::query()->create([
            'remote_order_id' => '8181194',
            'remote_status' => 'pending',
            'remote_status_name' => 'Новый',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'payload_hash' => hash('sha256', '8181194'),
            'payload' => ['id' => 8181194],
            'processing_status' => DealByOrderInbox::STATUS_PROCESSED,
            'order_id' => $order->id,
            'processed_at' => now(),
        ]);

        return [$order, $inbox];
    }

    private function order(): Order
    {
        return Order::query()->create([
            'number' => Order::generateNumber(),
            'status' => 'new',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}
