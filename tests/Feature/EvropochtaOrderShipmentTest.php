<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\EvropochtaOrderShipment;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EvropochtaOrderShipmentTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api-kassa.evropochta.by/api/external/';

    private const TRACK = 'BY080027046773';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.evropochta.token' => 'evropochta-test']);
        Http::preventStrayRequests();
    }

    public function test_order_action_creates_one_shipment_saves_the_track_and_shows_tracking(): void
    {
        $order = $this->order();
        $this->signIn();
        Http::fake([
            self::API.'postal/create' => Http::response(['number' => self::TRACK, 'status' => 0]),
            self::API.'postal/history*' => Http::response([
                ['created_at' => '2026-11-19 14:00:00', 'status_to' => ['name' => 'Заявка создана']],
                ['created_at' => '2026-11-19 14:30:00', 'status_to' => ['name' => 'В пути'], 'info' => 'Отправлено в Лиду'],
            ]),
        ]);

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('createEvropochtaShipment')
            ->assertActionHidden('trackEvropochtaShipment');
        Http::assertNothingSent();

        $page->mountAction('createEvropochtaShipment')->assertActionMounted('createEvropochtaShipment');
        $component = $page->instance();
        $preview = $component->getSchema($component->getMountedActionSchemaName());
        $this->assertSame('12,5 кг · 40 × 18 × 25 см', $preview->getComponent('ep_parcel')->getState());
        $this->assertStringContainsString('Красноармейская, 63', $preview->getComponent('ep_destination')->getState());

        $page->setActionData($this->recipient())
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertActionHidden('createEvropochtaShipment')
            ->assertActionVisible('trackEvropochtaShipment');
        $page->call('$refresh')->assertSee(self::TRACK);

        [$request] = Http::recorded()->sole();
        $this->assertSame(self::API.'postal/create', $request->url());
        $this->assertSame('POST', $request->method());
        $this->assertTrue($request->hasHeader('Token', 'evropochta-test'));
        $this->assertSame([
            'external_id' => 'KOTLOV-ORDER-'.$order->id,
            'is_juristic' => true, 'delivery_type' => 1, 'is_auto_delivery' => false, 'shipment_payer' => 0,
            'store_id_start' => 70130010, 'store_id_finish' => 40930010,
            'weight' => 12.5, 'length' => 40.0, 'width' => 18.0, 'height' => 25.0, 'is_oversize' => false,
            'receiver_name' => 'Иван', 'receiver_surname' => 'Петров', 'receiver_phone_number' => '375291234567',
        ], $request->data());
        $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(self::TRACK, app(EvropochtaOrderShipment::class)->create($order, $this->recipient()));
        Http::assertSentCount(1);

        $page->mountAction('trackEvropochtaShipment')->assertActionMounted('trackEvropochtaShipment');
        $history = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertMatchesRegularExpression('/В пути.*Заявка создана/su', $history);
        $this->assertStringContainsString('Отправлено в Лиду', $history);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === self::API.'postal/history?number='.self::TRACK);
    }

    public function test_retry_after_an_uncertain_result_recovers_the_existing_carrier_number(): void
    {
        $order = $this->order();
        Http::fake([
            self::API.'postal/create' => Http::sequence()
                ->pushStatus(503)
                ->push(['message' => '33 Такой PostalItemExternalId уже существует: '.self::TRACK], 422),
            self::API.'postal/'.self::TRACK => Http::response([
                'external_id' => 'KOTLOV-ORDER-'.$order->id, 'number' => self::TRACK,
            ]),
        ]);
        $service = app(EvropochtaOrderShipment::class);

        try {
            $service->create($order, $this->recipient());
            $this->fail('The carrier did not confirm the first attempt.');
        } catch (EvropochtaShipmentException $failure) {
            $this->assertStringContainsString('не подтвердила трек-номер', $failure->getMessage());
        }
        $this->assertNull($order->fresh()->evropochta_tracking_number);

        $this->assertSame(self::TRACK, $service->create($order, $this->recipient()));
        $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
        $attempts = Http::recorded(fn (Request $request): bool => $request->method() === 'POST');
        $this->assertCount(2, $attempts);
        foreach ($attempts as [$request]) {
            $this->assertSame('KOTLOV-ORDER-'.$order->id, $request['external_id']);
        }
    }

    public function test_orders_without_a_confirmed_parcel_are_not_submitted(): void
    {
        $this->signIn();
        $order = $this->order(['evropochta_quote' => null]);
        Http::fake([]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionDisabled('createEvropochtaShipment');

        $this->expectException(EvropochtaShipmentException::class);
        try {
            app(EvropochtaOrderShipment::class)->create($order, $this->recipient());
        } finally {
            Http::assertNothingSent();
        }
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => 'ORD-EVROPOCHTA-TEST', 'status' => 'confirmed',
            'customer_name' => 'Иван Петров', 'customer_phone' => '+375 (29) 123-45-67',
            'delivery_type' => 'evropochta', 'payment_type' => 'bank_transfer', 'payment_status' => 'paid',
            'subtotal' => 100, 'total' => 117.5, 'delivery_price' => 17.5,
            'evropochta_quote' => [
                'store_id_finish' => 40930010, 'address' => 'г. Лида, Красноармейская, 63, №23',
                'parcel' => ['store_id_start' => 70130010, 'weight' => 12.5, 'length' => 40, 'width' => 18, 'height' => 25],
            ],
            ...$attributes,
        ]);
    }

    private function recipient(): array
    {
        return [
            'receiver_name' => 'Иван', 'receiver_surname' => 'Петров',
            'receiver_phone_number' => '+375 (29) 123-45-67',
        ];
    }
}
