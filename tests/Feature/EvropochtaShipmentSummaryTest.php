<?php

namespace Tests\Feature;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\EvropochtaShipmentSummary as Summary;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EvropochtaShipmentSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = 'BY123456789012';

    private function order(?array $snapshot = null): Order
    {
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $order = Order::create(['number' => 'EURO-SUMMARY', 'status' => 'processing', 'customer_name' => 'Получатель',
            'customer_phone' => '+375291234567', 'delivery_type' => 'evropochta', 'payment_type' => 'evropochta_cod',
            'payment_status' => 'pending', 'subtotal' => 70, 'total' => 77.9,
            'evropochta_quote' => $snapshot ? ['shipment' => ['number' => self::TRACK] + $snapshot] : []]);
        $order->forceFill(['evropochta_tracking_number' => self::TRACK])->save();
        return $order;
    }

    public function test_creation_amount_is_not_recomputed_after_the_order_is_edited_or_paid(): void
    {
        $order = $this->order(['payment_amount' => 77.9, 'shipment_payer' => 0, 'cash_on_delivery_payer' => 0]);
        $order->update(['total' => 99, 'payment_status' => 'paid']);
        $this->assertSame(77.9, Summary::amount($order->fresh()));
        $this->assertSame('При оформлении отправления', Summary::source($order));
        $this->assertSame('Отправитель', Summary::payer($order, 'shipment_payer'));
        $this->assertSame('Отправитель', Summary::payer($order, 'cash_on_delivery_payer'));
    }

    public function test_missing_snapshot_is_explicitly_an_order_estimate_and_never_assumed_zero(): void
    {
        $order = $this->order();
        $this->assertSame(77.9, Summary::amount($order));
        $this->assertStringContainsString('По заказу', Summary::source($order));
        $this->assertSame('Не сохранён', Summary::payer($order, 'shipment_payer'));
        $order->update(['payment_type' => 'online_card']);
        $this->assertNull(Summary::amount($order));
    }

    public function test_a_replaced_track_cannot_show_old_status_or_financial_snapshot(): void
    {
        $order = $this->order(['payment_amount' => 77.9, 'status' => 'Заявка создана', 'shipment_payer' => 0]);
        $order->forceFill(['carrier_provider' => 'evropochta', 'carrier_reference' => self::TRACK,
            'carrier_status_text' => 'Вручено', 'carrier_stage' => 'delivered'])->save();
        $this->assertSame('Вручено', Summary::status($order));
        $this->assertSame('success', Summary::color($order));
        $order->forceFill(['evropochta_tracking_number' => 'BY999999999999'])->save();
        $this->assertNull(Summary::snapshot($order));
        $this->assertSame('Статус ещё не проверен', Summary::status($order));
        $this->assertSame('gray', Summary::color($order));
    }

    public function test_listing_displays_status_amount_and_payer_without_calling_the_carrier(): void
    {
        $order = $this->order(['payment_amount' => 77.9, 'status' => 'Заявка создана', 'shipment_payer' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListShipments::class)->assertSuccessful()->assertCanSeeTableRecords([$order])
            ->assertSee('Статус Европочты')->assertSee('Заявка создана')->assertSee('Наложенный платёж')
            ->assertSee('При оформлении отправления')->assertSee('Отправитель');
        Http::assertNothingSent();
    }

    public function test_zero_is_only_shown_when_it_was_saved_at_creation(): void
    {
        $order = $this->order(['payment_amount' => 0, 'status' => 'Заявка создана', 'shipment_payer' => 1]);
        $this->assertSame(0.0, Summary::amount($order));
        $this->assertSame('Без наложенного платежа', Summary::source($order));
        $this->assertSame('Получатель', Summary::payer($order, 'shipment_payer'));
    }
}
