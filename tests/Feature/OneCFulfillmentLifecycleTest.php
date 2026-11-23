<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\OneCAgentTask;
use App\Models\Order;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderStages;
use App\Services\Integrations\OneCBridge\OneCOrderStates;
use App\Services\Orders\OrderStatusCustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Testing\Fakes\MailFake;
use Tests\TestCase;

class OneCFulfillmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        Mail::fake();
        $client = User::factory()->create();
        $order = Order::create($attributes + ['number' => 'SYNC-TEST', 'user_id' => $client->id, 'status' => 'confirmed',
            'customer_name' => 'Клиент', 'customer_phone' => '+375291234567', 'customer_email' => $client->email,
            'delivery_type' => 'courier', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 200, 'total' => 200,
            'availability_confirmed_at' => now(), 'onec_order_guid' => 'aaaaaaaa-0000-0000-0000-000000000001',
            'onec_order_number' => 'TEST-1C', 'onec_posted' => true, 'onec_deleted' => false, 'onec_doc_status' => 'КОбеспечению']);
        $order->forceFill(['onec_posted' => true, 'onec_deleted' => false, 'onec_doc_status' => $attributes['onec_doc_status'] ?? 'КОбеспечению'])->save();
        $order->items()->create(['product_name' => 'Ожидаемый товар', 'quantity' => 2, 'price' => 100,
            'fulfillment_availability_status' => 'awaiting_supply', 'fulfillment_confirmed_at' => now()]);

        return $order;
    }

    private function state(Order $order, string $state, $at = null): void
    {
        app(OneCOrderStates::class)->apply([['Заказ' => $order->onec_order_guid, 'Состояние' => $state]], $at);
        $order->refresh();
    }

    public function test_waiting_ready_shipping_and_receipt_update_the_account_without_duplicate_mail(): void
    {
        $order = $this->order();
        $this->state($order, 'ОжидаетсяОбеспечение');
        $this->assertSame('processing', $order->status);
        $this->assertStringContainsString('Ожидаем поступление', $order->customerItemFulfillment($order->items()->first()));
        $this->state($order, 'ГотовКОтгрузке');
        $this->assertSame('Готов к отгрузке', $order->customerStatusLabel());
        $this->assertStringContainsString('Подготовлено к отгрузке: 2', $order->customerItemFulfillment($order->items()->first()));
        $this->actingAs($order->user)->get(route('account.orders.show', $order))->assertOk()->assertSeeText('Готов к отгрузке');
        $this->state($order, 'ВПроцессеОтгрузки');
        $this->state($order, 'ВПроцессеОтгрузки');
        $this->assertSame('Передан в доставку', $order->customerStatusLabel());
        $this->state($order, 'Закрыт');
        $this->state($order, 'Закрыт');
        $this->assertSame('Получен', $order->customerStatusLabel());
        Mail::assertQueuedCount(3);
        $this->assertDatabaseCount('order_stage_email_claims', 3);
        $this->assertSame(3, $order->statusHistory()->count());
    }

    public function test_ready_pickup_does_not_require_site_line_confirmation_again(): void
    {
        $order = $this->order(['delivery_type' => 'pickup']);
        $this->state($order, 'ГотовКОтгрузке');
        $this->assertSame('shipped', $order->status);
        $this->assertSame('Готов к самовывозу', $order->customerStatusLabel());
        $this->assertSame(2, $order->items()->sum('quantity'));
        $html = (new OrderStatusUpdatedMail($order))->render();
        $this->assertStringContainsString('Готов к самовывозу', $html);
        $this->assertStringNotContainsString('передан в доставку', $html);
        $this->assertStringNotContainsString('Ожидаем поступление', $html);
    }

    public function test_late_register_response_cannot_restore_old_waiting_or_queue_mail_again(): void
    {
        $order = $this->order();
        $this->state($order, 'ГотовКОтгрузке', now());
        $this->state($order, 'ОжидаетсяОбеспечение', now()->subHour());
        $this->assertSame('ГотовКОтгрузке', $order->onec_state);
        $this->assertSame('Готов к отгрузке', $order->customerStatusLabel());
        $this->assertFalse(app(OrderStatusCustomerNotifier::class)->notify($order));
        Mail::assertQueuedCount(1);
    }

    public function test_same_response_uses_new_register_instead_of_old_ready_state(): void
    {
        Storage::fake('local');
        $order = $this->order(['delivery_type' => 'pickup', 'onec_state' => 'ГотовКОтгрузке']);
        $task = OneCAgentTask::create(['type' => 'doc_status', 'title' => 'Test', 'status' => 'sending', 'picked_at' => now(),
            'payload' => ['queries' => ['orders_1' => 'orders', 'states_1' => 'states'], 'parts' => ['orders_1' => ['code' => 200], 'states_1' => ['code' => 200]]]]);
        Storage::disk('local')->put(ODataReadTaskHandler::file($task, 'orders_1'), json_encode(['value' => [['Ref_Key' => $order->onec_order_guid,
            'Posted' => true, 'Статус' => 'КОбеспечению', 'Товары' => [['Отменено' => false]]]]]));
        Storage::disk('local')->put(ODataReadTaskHandler::file($task, 'states_1'), json_encode(['value' => [['Заказ' => $order->onec_order_guid, 'Состояние' => 'ОжидаетсяОбеспечение']]]));
        app(DocumentStatusTaskHandler::class)->finished($task, ['ok' => true]);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('Комплектация', $order->fresh()->customerStatusLabel());
        Mail::assertQueuedCount(1);
    }

    public function test_cancelled_lines_never_become_a_successful_delivery_from_register(): void
    {
        $order = $this->order(['status' => 'processing', 'onec_doc_status' => 'Закрыт']);
        app(OneCOrderStates::class)->apply([['Заказ' => $order->onec_order_guid, 'Состояние' => 'Закрыт']], now(), [$order->onec_order_guid]);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNull(app(OneCOrderStages::class)->apply($order, true));
        Mail::assertNothingQueued();
    }

    public function test_failed_queue_can_be_retried_without_claiming_a_successful_notification(): void
    {
        $order = $this->order(['status' => 'processing']);
        Mail::shouldReceive('mailer')->with('transactional')->andThrow(new \RuntimeException('Test queue failure'));
        $notifier = app(OrderStatusCustomerNotifier::class);
        $this->assertFalse($notifier->notify($order));
        $this->assertDatabaseCount('order_stage_email_claims', 0);
        Mail::swap(new MailFake(new MailManager(app())));
        $this->assertTrue($notifier->notify($order));
        $this->assertFalse($notifier->notify($order));
        Mail::assertQueuedCount(1);
    }
}
