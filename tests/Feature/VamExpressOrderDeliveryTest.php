<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressDraft;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressOrderDelivery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VamExpressOrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Http::preventStrayRequests();
        Mail::fake();
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $order = Order::create(['number' => 'VAM-SELECT', 'status' => 'processing', 'customer_name' => 'Клиент', 'customer_phone' => '+375291234567',
            'delivery_type' => 'transport', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 100, 'total' => 110, 'delivery_price' => 10]);
        $order->items()->create(['product_name' => 'Товар', 'quantity' => 2, 'price' => 50]);
        $shipment = VamExpressShipment::create(['remote_id' => str_repeat('a', 32), 'display_id' => 'abcdef0', 'city' => 'Лида', 'carrier_status' => 'Не обработана']);

        return [$actor, $order, $shipment];
    }

    public function test_choose_vam_and_link_existing_shipment_from_order_without_shipping_or_repricing(): void
    {
        [, $order, $shipment] = $this->fixture();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('selectVamDelivery', ['shipment_id' => $shipment->id])->assertHasNoActionErrors();
        $this->assertSame('vam_express', $order->fresh()->delivery_type);
        $this->assertSame($order->id, $shipment->fresh()->order_id);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('110.00', $order->fresh()->total);
        $this->assertSame(2, (int) $order->items()->sum('quantity'));
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }

    public function test_choose_vam_without_existing_shipment_then_reuse_the_same_order_draft(): void
    {
        [, $order] = $this->fixture();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('selectVamDelivery', ['shipment_id' => null])->assertHasNoActionErrors();
        $this->assertSame('vam_express', $order->fresh()->delivery_type);
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('vamExpressDraft')->assertHasNoActionErrors();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('vamExpressDraft')->assertHasNoActionErrors();
        $this->assertSame(1, VamExpressDraft::where('order_id', $order->id)->count());
        $this->assertSame('processing', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_foreign_link_is_not_stolen_and_active_europochta_cannot_be_silently_replaced(): void
    {
        [$actor, $order, $shipment] = $this->fixture();
        $other = $order->replicate();
        $other->number = 'VAM-OTHER';
        $other->save();
        $shipment->forceFill(['order_id' => $other->id])->save();
        try {
            app(VamExpressOrderDelivery::class)->select($order, $actor, $shipment->id);
            $this->fail('Foreign link accepted');
        } catch (ValidationException) {
            $this->assertSame($other->id, $shipment->fresh()->order_id);
            $this->assertSame('transport', $order->fresh()->delivery_type);
        }
        $order->forceFill(['delivery_type' => 'evropochta', 'evropochta_tracking_number' => 'BY123456789012'])->save();
        $this->expectException(ValidationException::class);
        app(VamExpressOrderDelivery::class)->select($order, $actor);
    }
}
