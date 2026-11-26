<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\IntegrationSource;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OneCOrderJourney;
use App\Services\Integrations\OneCBridge\OneCSupplierOrderExport;
use App\Services\Orders\OrderDeliveryPreparation;
use App\Services\Orders\OrderWorkflow;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDeliveryPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'processing', string $method = 'pickup'): Order
    {
        Mail::fake();
        Http::preventStrayRequests();
        $order = Order::create(['number' => 'STEP-'.uniqid(), 'status' => $status, 'customer_name' => 'Клиент',
            'customer_phone' => '+375291110000', 'delivery_type' => $method, 'payment_type' => 'cash',
            'payment_status' => 'pending', 'subtotal' => 10, 'total' => 10]);
        $order->items()->create(['product_name' => 'Ожидаемый товар', 'quantity' => 1, 'price' => 10, 'total' => 10,
            'fulfillment_confirmed_at' => now(), 'fulfillment_availability_status' => 'awaiting_supply']);

        return $order;
    }

    private function plan(Order $order): void
    {
        app(OrderDeliveryPreparation::class)->confirm($order, User::factory()->create(['role' => 'admin']), [
            'method' => $order->delivery_type, 'from' => 'Склад поставщика, адрес 1', 'to' => 'Пункт передачи, адрес 2',
            'date' => now()->addDay()->toDateString(), 'note' => 'Забрать после поступления',
        ]);
        $order->refresh();
    }

    public function test_early_export_is_rejected_even_when_called_directly(): void
    {
        foreach (['new', 'checking', 'confirmed'] as $status) {
            $order = $this->order($status);
            try {
                app(OneCOrderExport::class)->enqueue($order, [], null);
                $this->fail('Early export must be rejected');
            } catch (DomainException $e) {
                $this->assertStringContainsString('Передать в исполнение', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('onec_agent_tasks', 0);
    }

    public function test_execution_is_separate_from_one_c_even_with_queue_enabled(): void
    {
        config()->set('onec.order_queue_enabled', true);
        $order = $this->order('confirmed');
        app(OrderWorkflow::class)->advance($order, null);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseCount('onec_agent_tasks', 0);
        $this->assertDatabaseCount('order_integration_deliveries', 0);
    }

    public function test_pickup_preparation_preserves_waiting_goods_and_does_not_dispatch(): void
    {
        $order = $this->order();
        $this->plan($order);
        $this->assertNull(app(OrderDeliveryPreparation::class)->blocker($order));
        $this->assertSame('processing', $order->status);
        $this->assertSame('awaiting_supply', $order->items()->sole()->fulfillment_availability_status);
        $summary = app(OrderDeliveryPreparation::class)->summary($order);
        $this->assertStringContainsString('Забрать: Склад поставщика', $summary);
        $this->assertStringContainsString('Передать: Пункт передачи', $summary);
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }

    public function test_europochta_requires_a_real_track_not_a_quote(): void
    {
        $order = $this->order('processing', 'evropochta');
        $order->update(['delivery_city' => 'Минск', 'delivery_address' => 'Отделение 1',
            'evropochta_quote' => ['store_id_finish' => 123, 'parcel' => ['weight' => 1]]]);
        $this->assertStringContainsString('трек-номер', app(OrderDeliveryPreparation::class)->blocker($order));
        $this->expectException(DomainException::class);
        $this->plan($order);
    }

    public function test_missing_evropochta_destination_blocks_preparation_and_one_c_even_with_a_track(): void
    {
        $order = $this->order('processing', 'evropochta');
        $order->forceFill(['evropochta_tracking_number' => 'TEST-TRACK'])->save();
        $this->assertStringContainsString('Не выбрано отделение', app(OrderDeliveryPreparation::class)->blocker($order));
        $next = app(OneCOrderJourney::class)->next($order);
        $this->assertStringContainsString('Оформить Европочту', $next);
        $this->assertStringNotContainsString('Создать заказ в 1С', $next);
        foreach (['prepare', 'export'] as $action) {
            try {
                if ($action === 'prepare') {
                    $this->plan($order);
                } else {
                    app(OneCOrderExport::class)->enqueue($order, [], null);
                }
                $this->fail('Incomplete delivery must be blocked.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('Не выбрано отделение', $exception->getMessage());
            }
        }
        $this->assertNull($order->fresh()->delivery_preparation);
        $this->assertDatabaseCount('onec_agent_tasks', 0);
        Http::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_vam_requires_one_linked_id_and_does_not_treat_it_as_dispatch(): void
    {
        $order = $this->order('processing', 'vam_express');
        $this->assertStringContainsString('ID заявки', app(OrderDeliveryPreparation::class)->blocker($order));
        $shipment = VamExpressShipment::create(['remote_id' => 'abcdef1', 'display_id' => 'abcdef1', 'carrier_status' => 'Не обработана']);
        $shipment->forceFill(['order_id' => $order->id])->save();
        $this->plan($order);
        $this->assertNull(app(OrderDeliveryPreparation::class)->blocker($order));
        $this->assertStringContainsString('Трек / ID: abcdef1', app(OrderDeliveryPreparation::class)->summary($order));
        $this->assertSame('processing', $order->status);
    }

    public function test_recipient_or_items_change_requires_reconfirmation(): void
    {
        $order = $this->order();
        $this->plan($order);
        $order->update(['customer_phone' => '+375291119999']);
        $this->assertStringContainsString('изменились', app(OrderDeliveryPreparation::class)->blocker($order));
    }

    public function test_prepared_route_and_id_survive_the_simplest_one_c_payload(): void
    {
        $order = $this->order('processing', 'vam_express');
        $shipment = VamExpressShipment::create(['remote_id' => 'abcdef2', 'display_id' => 'abcdef2']);
        $shipment->forceFill(['order_id' => $order->id])->save();
        $this->plan($order);
        $profile = new OneCOrderProfile(['header' => [], 'row_defaults' => [], 'meta' => []]);
        $body = app(OneCOrderExport::class)->body($order, $profile, ['comment' => 'Заказ'], 4);
        foreach (['abcdef2', 'Склад поставщика', 'Пункт передачи', 'Забрать после поступления'] as $text) {
            $this->assertStringContainsString($text, $body['Комментарий']);
        }
        $this->assertArrayNotHasKey('delivery_preparation', $order->toArray());
    }

    public function test_admin_only_sees_one_c_after_delivery_preparation(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $early = $this->order('confirmed');
        Livewire::test(ViewOrder::class, ['record' => $early->id])
            ->assertActionHidden('onecOrder')->assertActionHidden('prepareOrderDelivery');
        $order = $this->order();
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHidden('onecOrder')->assertActionVisible('prepareOrderDelivery');
        $this->plan($order);
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionVisible('onecOrder')->assertSee('Согласованная передача');
    }

    public function test_supplier_one_c_export_cannot_bypass_preparation(): void
    {
        $order = $this->order('confirmed');
        $this->expectException(DomainException::class);
        app(OneCSupplierOrderExport::class)->enqueue($order, 1, [], null);
    }

    public function test_existing_europochta_label_shows_tracking_without_a_second_dispatch_step(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $order = $this->order('processing', 'evropochta');
        $order->forceFill(['evropochta_tracking_number' => 'TEST-TRACK', 'carrier_stage' => 'created'])->save();
        IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1C', 'is_active' => true]);
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertSee('Европочта: ярлык и статус')->assertActionHidden('createEvropochtaShipment')
            ->assertActionHidden('advanceWorkflow')->assertActionVisible('prepareOrderDelivery');
        $this->assertSame('processing', $order->fresh()->status);
    }
}
