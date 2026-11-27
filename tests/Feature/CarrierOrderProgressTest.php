<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use App\Services\Shipping\CarrierOrderProgress;
use App\Support\Shipping\VamExpressStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CarrierOrderProgressTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = 'BY123456789012';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
    }

    private function order(string $delivery = 'evropochta'): Order
    {
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'number' => 'CARRIER-'.$user->id, 'status' => 'processing',
            'customer_name' => 'Клиент', 'customer_email' => $user->email, 'customer_phone' => '+375291234567',
            'delivery_type' => $delivery, 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 30, 'total' => 30]);
        $order->forceFill(['evropochta_tracking_number' => self::TRACK])->save();
        $order->items()->create(['product_name' => 'Товар', 'quantity' => 3, 'price' => 10]);

        return $order;
    }

    private function euro(Order $order, string $name, int $code = 10, $at = null, ?string $track = null): void
    {
        app(CarrierOrderProgress::class)->euro($order, ['code' => $code, 'status' => $name], $at ?? now(), $track ?? self::TRACK);
        $order->refresh();
    }

    private function vam(Order $order, string $name): VamExpressShipment
    {
        $shipment = VamExpressShipment::create(['remote_id' => str_pad((string) $order->id, 32, 'a'), 'display_id' => 'vam-'.$order->id,
            'city' => 'Минск', 'cargo' => 'Товар', 'carrier_status' => $name]);
        $shipment->forceFill(['order_id' => $order->id])->save();
        app(CarrierOrderProgress::class)->vam($shipment, now());

        return $shipment;
    }

    public function test_label_is_preparation_and_each_confirmed_carrier_stage_notifies_once(): void
    {
        $order = $this->order();
        $this->euro($order, 'Заявка создана', 0);
        $this->assertSame('processing', $order->status);
        Mail::assertNothingQueued();
        foreach (['Принято в отделении' => 'Передан перевозчику', 'В пути' => 'В пути', 'Готово к выдаче' => 'Ожидает получения', 'Выдано' => 'Получен'] as $name => $label) {
            $this->euro($order, $name);
            $this->euro($order, $name);
            $this->assertSame($label, $order->customerStatusLabel());
            $this->actingAs($order->user)->get(route('account.orders.show', $order))->assertOk()->assertSeeText($label)->assertSeeText(self::TRACK);
        }
        $this->assertSame('delivered', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('30.00', $order->total);
        $this->assertEquals(3, $order->items()->sum('quantity'));
        Mail::assertQueuedCount(4);
    }

    public function test_vam_processed_means_received_as_confirmed_by_the_owner(): void
    {
        $order = $this->order('transport');
        $shipment = $this->vam($order, 'Не обработана');
        $this->assertSame('processing', $order->fresh()->status);
        $shipment->update(['carrier_status' => 'В пути']);
        app(CarrierOrderProgress::class)->vam($shipment, now());
        $this->assertSame('В пути', $order->fresh()->customerStatusLabel());
        $shipment->update(['carrier_status' => 'Обработана']);
        app(CarrierOrderProgress::class)->vam($shipment, now());
        app(CarrierOrderProgress::class)->vam($shipment, now());
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('delivered', VamExpressStatus::group('Обработана'));
        Mail::assertQueuedCount(2);
    }

    public function test_old_and_regressive_responses_do_not_roll_back_delivery(): void
    {
        $order = $this->order();
        $this->euro($order, 'В пути');
        $this->euro($order, 'Принято в отделении');
        $this->euro($order, 'Вручено получателю', 20, now()->subHour());
        $this->assertSame('transit', $order->carrier_stage);
        $this->assertSame('shipped', $order->status);
        Mail::assertQueuedCount(1);
    }

    public function test_cancelled_unknown_and_paid_are_not_receipt_and_no_order_is_cancelled(): void
    {
        $order = $this->order();
        $this->euro($order, 'Новый непонятный статус');
        $this->assertSame('processing', $order->status);
        Mail::assertNothingQueued();
        $this->euro($order, 'Отменена', 99);
        $this->assertSame('processing', $order->status);
        $this->assertSame('Уточняем доставку', $order->customerStatusLabel());
        $other = $this->order('transport');
        $this->vam($other, 'Оплачено');
        $this->assertSame('processing', $other->fresh()->status);
        Mail::assertQueuedCount(1);
    }

    public function test_wrong_track_and_unlinked_vam_do_not_change_an_order(): void
    {
        $order = $this->order();
        $this->euro($order, 'Вручено получателю', 20, null, 'BY000000000000');
        $shipment = VamExpressShipment::create(['remote_id' => str_repeat('b', 32), 'display_id' => 'test', 'carrier_status' => 'Обработана']);
        app(CarrierOrderProgress::class)->vam($shipment, now());
        $this->assertSame('processing', $order->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_stale_letter_about_acceptance_is_not_sent_after_movement(): void
    {
        $order = $this->order();
        $this->euro($order, 'Принято в отделении');
        $mail = new OrderStatusUpdatedMail($order);
        $this->euro($order, 'В пути');
        $called = false;
        $mail->middleware()[0]->handle(new \stdClass, function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }

    public function test_scheduled_poll_is_bounded_and_does_not_repeat_the_same_shipment(): void
    {
        config(['services.evropochta.token' => 'test']);
        for ($i = 1; $i <= 7; $i++) {
            $this->order()->forceFill(['evropochta_tracking_number' => 'BY'.str_pad((string) $i, 12, '0', STR_PAD_LEFT)])->save();
        }
        Http::fake(['api-kassa.evropochta.by/*' => fn ($request) => Http::response(['number' => basename($request->url()), 'status' => ['value' => 0, 'name' => 'Заявка создана']])]);
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        Http::assertSentCount(5);
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        Http::assertSentCount(7);
        Mail::assertNothingQueued();
    }

    public function test_replacing_the_track_invalidates_old_letters_and_old_responses(): void
    {
        $order = $this->order();
        $this->euro($order, 'Принято в отделении');
        $oldMail = new OrderStatusUpdatedMail($order);
        $newNumber = 'BY999999999999';
        $order->forceFill(['evropochta_tracking_number' => $newNumber])->save();
        $this->euro($order, 'Принято в отделении', 10, null, $newNumber);
        $this->euro($order, 'Вручено получателю');
        $called = false;
        $oldMail->middleware()[0]->handle(new \stdClass, function () use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
        $this->assertSame('accepted', $order->carrier_stage);
        $this->assertSame($newNumber, $order->carrier_reference);
    }

    public function test_multiple_linked_vam_shipments_cannot_finish_the_order_from_one_shipment(): void
    {
        $order = $this->order('transport');
        foreach (['a', 'b'] as $prefix) {
            $shipment = VamExpressShipment::create(['remote_id' => str_repeat($prefix, 32), 'display_id' => $prefix, 'carrier_status' => 'Обработана']);
            $shipment->forceFill(['order_id' => $order->id])->save();
        }
        app(CarrierOrderProgress::class)->vam($shipment, now());
        $this->assertSame('processing', $order->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_failed_carrier_poll_preserves_progress_and_has_a_cooldown(): void
    {
        config(['services.evropochta.token' => 'test']);
        $order = $this->order();
        $this->euro($order, 'В пути');
        Http::fake(['api-kassa.evropochta.by/*' => Http::sequence()
            ->push([], 503)
            ->push(['number' => self::TRACK, 'status' => ['value' => 90, 'name' => 'Выдано']])]);
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame('В пути', $order->fresh()->customerStatusLabel());
        $this->assertNotNull($order->fresh()->carrier_check_failed_at);
        Mail::assertQueuedCount(1);

        $this->travel(31)->minutes();
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        $this->assertNull($order->fresh()->carrier_check_failed_at);
        $this->assertSame('delivered', $order->fresh()->status);
    }
}
