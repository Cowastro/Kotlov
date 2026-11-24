<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Jobs\SendEvropochtaTrackingSms;
use App\Livewire\EvropochtaOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\EvropochtaOrderShipment;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
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

    public function test_manual_order_preparation_saves_the_quote_and_enables_shipment_creation(): void
    {
        $this->signIn();
        $this->fakePreparationTariff();
        $order = $this->manualOrder();
        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('prepareEvropochtaShipment')
            ->assertActionDisabled('createEvropochtaShipment');
        Http::assertNothingSent();

        $page->mountAction('prepareEvropochtaShipment')->assertActionMounted('prepareEvropochtaShipment');
        $component = $page->instance();
        $form = $component->getSchema($component->getMountedActionSchemaName());
        $this->assertSame(70130010, (int) $form->getComponent('store_id_start')->getState());
        $this->assertStringContainsString('Солигорск', $form->getComponent('store_id_finish')->getOptions()[40930011]);

        $page->setActionData($this->parcelInput())->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertActionEnabled('createEvropochtaShipment')
            ->assertSee('Тариф перевозчика')
            ->assertSee('Солигорск');

        $saved = $order->fresh();
        $this->assertSame('200.00', $saved->subtotal);
        $this->assertSame('8.00', $saved->delivery_price);
        $this->assertSame('198.00', $saved->total);
        $this->assertSame('Солигорск', $saved->delivery_city);
        $this->assertSame('Минская область', $saved->delivery_region);
        $this->assertStringContainsString('Ленинского Комсомола, 43, №567', $saved->delivery_address);
        $this->assertSame(6.34, $saved->evropochta_quote['carrier_price']);
        $this->assertSame(70130010, $saved->evropochta_quote['parcel']['store_id_start']);
        $this->assertSame(['id' => 70130010, 'address' => 'г. Минск, Монтажников, 2, №1'], $saved->evropochta_quote['origin']);
        $this->assertSame(40930011, $saved->evropochta_quote['store_id_finish']);
        $this->assertEquals(['weight' => 1.25, 'length' => 24, 'width' => 24, 'height' => 20],
            array_intersect_key($saved->evropochta_quote['parcel'], array_flip(['weight', 'length', 'width', 'height'])));
        $this->assertNull($saved->evropochta_tracking_number);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === self::API.'postal/payment/calculate'
            && (float) collect($request->data())->pluck('contents', 'name')->get('weight') === 1.25);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));

        $saved->items()->firstOrFail()->update(['quantity' => 3]);
        $page->call('$refresh')->assertActionDisabled('createEvropochtaShipment');
        $this->assertStringContainsString('Состав заказа изменился', app(EvropochtaOrderShipment::class)->unavailableReason($saved));
    }

    public function test_temporary_carrier_failure_preserves_the_previous_order_amount(): void
    {
        $this->signIn();
        $this->fakePreparationTariff(Http::response([], 503));
        $order = $this->manualOrder();
        $service = app(EvropochtaOrderShipment::class);

        $this->expectException(EvropochtaShipmentException::class);
        try {
            $service->prepare($order, $this->parcelInput());
        } finally {
            $saved = $order->fresh();
            $this->assertNull($saved->evropochta_quote);
            $this->assertSame('0.00', $saved->delivery_price);
            $this->assertSame('0.00', $saved->total);
            Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));
        }
    }

    public function test_preparation_cannot_overwrite_a_paid_total(): void
    {
        $this->signIn();
        $this->fakePreparationTariff();
        $order = $this->manualOrder(['payment_status' => 'paid', 'subtotal' => 200, 'total' => 190]);

        $this->expectException(EvropochtaShipmentException::class);
        $this->expectExceptionMessage('Новый тариф меняет итог заказа');
        try {
            app(EvropochtaOrderShipment::class)->prepare($order, $this->parcelInput());
        } finally {
            $this->assertSame('190.00', $order->fresh()->total);
            $this->assertNull($order->fresh()->evropochta_quote);
        }
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
        $this->assertTrue($preview->getComponent('notify_receiver')->getState());
        $this->assertSame('100,00 BYN', $preview->getComponent('ep_goods')->getState());
        $this->assertSame('17,50 BYN', $preview->getComponent('ep_delivery')->getState());
        $this->assertSame('117,50 BYN', $preview->getComponent('ep_total')->getState());
        $this->assertSame('0,00 BYN', $preview->getComponent('ep_cod')->getState());
        $this->assertStringContainsString('наш магазин', $preview->getComponent('ep_payers')->getState());


        $page->setActionData($this->recipient() + ['label_format' => 'thermal'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertRedirect(route('admin.orders.evropochta-label', ['order' => $order, 'format' => 'thermal']));

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('createEvropochtaShipment')
            ->assertActionVisible('trackEvropochtaShipment')
            ->assertActionVisible('printEvropochtaLabel_thermal')
            ->assertActionVisible('printEvropochtaLabel_address')
            ->assertSeeInOrder(['Ход заказа', 'Следующий шаг', 'Товары заказа', 'Трек-номер'])
            ->assertSee(self::TRACK)
            ->assertSee('Статус Европочты');

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
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(self::TRACK, app(EvropochtaOrderShipment::class)->create($order, $this->recipient()));
        Http::assertSentCount(1);

        $page->mountAction('trackEvropochtaShipment')->assertActionMounted('trackEvropochtaShipment');
        $history = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertMatchesRegularExpression('/В пути.*Заявка создана/su', $history);
        $this->assertStringContainsString('Отправлено в Лиду', $history);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === self::API.'postal/history?number='.self::TRACK);
    }

    public function test_labels_are_private_and_can_be_reopened_after_the_carrier_indexes_the_shipment(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $pdf = "%PDF-1.7\n% Test label";
        Http::fake([
            self::API.'label/thermal/'.self::TRACK => Http::sequence()
                ->pushStatus(404)
                ->push($pdf, 200, ['Content-Type' => 'application/pdf']),
            self::API.'label/address/'.self::TRACK => Http::response($pdf, 200, ['Content-Type' => 'application/pdf']),
        ]);
        $thermal = route('admin.orders.evropochta-label', ['order' => $order, 'format' => 'thermal']);
        $a5 = route('admin.orders.evropochta-label', ['order' => $order, 'format' => 'address']);

        $this->get($thermal)->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));
        $this->get($thermal)->assertForbidden();
        Http::assertNothingSent();

        $this->signIn();
        $this->get($thermal)->assertStatus(503)->assertSee('Открыть ярлык ещё раз')->assertSee(self::TRACK);
        $this->get($thermal)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertContent($pdf);
        $this->get($a5)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=evropochta-'.self::TRACK.'-address.pdf')
            ->assertContent($pdf);
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_manager_cancels_a_created_shipment_and_keeps_the_order_and_track(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $this->signIn();
        Http::fake([
            self::API.'postal/'.self::TRACK => Http::response($this->carrierStatus(0, 'Заявка создана')),
            self::API.'postal/'.self::TRACK.'/cancel' => Http::response($this->carrierStatus(90, 'Отменено')),
        ]);

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
        $page->assertSee('Статус Европочты');
        $page->assertDontSee('Отменить отправление Европочты');
        $component = Livewire::test(EvropochtaOrderStatus::class, ['orderId' => $order->id])
            ->call('refreshStatus')
            ->assertSee('Отменить отправление Европочты')
            ->assertSee('wire:confirm=', false)
            ->call('cancelShipment')
            ->assertSee('Отменено')
            ->assertDontSee('Отменить отправление Европочты');
        $component->call('refreshStatus')->assertSee('Отменено');

        $requestLog = Http::recorded()->map(fn (array $exchange): array => [$exchange[0]->method(), $exchange[0]->url()])->all();
        $this->assertSame([
            ['GET', self::API.'postal/'.self::TRACK],
            ['GET', self::API.'postal/'.self::TRACK],
            ['POST', self::API.'postal/'.self::TRACK.'/cancel'],
        ], $requestLog);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Token', 'evropochta-test'));
        $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_cancellation_checks_the_current_status_even_if_the_button_was_visible(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $this->signIn();
        Http::fake([
            self::API.'postal/'.self::TRACK => Http::sequence()
                ->push($this->carrierStatus(0, 'Заявка создана'))
                ->push($this->carrierStatus(10, 'Принято в отделении')),
        ]);

        Livewire::test(EvropochtaOrderStatus::class, ['orderId' => $order->id])
            ->call('refreshStatus')
            ->assertSee('Отменить отправление Европочты')
            ->call('cancelShipment')
            ->assertSee('Принято в отделении')
            ->assertDontSee('Отменить отправление Европочты');

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_carrier_rejection_hides_cancellation_until_the_status_is_checked_again(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $this->signIn();
        Http::fake([
            self::API.'postal/'.self::TRACK => Http::sequence()
                ->push($this->carrierStatus(0, 'Заявка создана'))
                ->push($this->carrierStatus(0, 'Заявка создана'))
                ->push($this->carrierStatus(10, 'Принято в отделении')),
            self::API.'postal/'.self::TRACK.'/cancel' => Http::response(['message' => 'Недопустимый статус'], 422),
        ]);

        Livewire::test(EvropochtaOrderStatus::class, ['orderId' => $order->id])
            ->call('refreshStatus')
            ->call('cancelShipment')
            ->assertSee('Обновление временно недоступно')
            ->assertDontSee('Отменить отправление Европочты')
            ->call('refreshStatus')
            ->assertSee('Принято в отделении')
            ->assertDontSee('Отменить отправление Европочты');
        Http::assertSentCount(4);
        $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
    }

    public function test_customer_cannot_request_shipment_cancellation(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));
        Http::fake([]);

        Livewire::test(EvropochtaOrderStatus::class, ['orderId' => $order->id])
            ->call('cancelShipment')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_status_refreshes_and_preserves_the_last_status_if_the_carrier_is_unavailable(): void
    {
        $order = $this->order(['evropochta_tracking_number' => self::TRACK]);
        $this->signIn();
        Http::fake([
            self::API.'postal/'.self::TRACK => Http::sequence()
                ->push($this->carrierStatus(0, 'Заявка создана'))
                ->push($this->carrierStatus(10, 'В пути'))
                ->pushStatus(503),
        ]);

        $component = Livewire::test(EvropochtaOrderStatus::class, ['orderId' => $order->id])
            ->assertSee('wire:poll.60s.visible="refreshStatus"', false)
            ->call('refreshStatus')->assertSee('Заявка создана');
        $component->call('refreshStatus')->assertSee('Заявка создана');
        Http::assertSentCount(1);

        $this->travel(4)->minutes();
        $component->call('refreshStatus')->assertSee('В пути');
        $this->travel(4)->minutes();
        $component->call('refreshStatus')->assertSee('В пути')->assertSee('Обновление временно недоступно');
        Http::assertSentCount(3);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('В пути', $order->fresh()->customerStatusLabel());
    }

    public function test_tracking_sms_is_sent_only_once_to_the_shipment_recipient_after_the_track_is_saved(): void
    {
        $this->enableTrackingSms();
        Bus::fake([SendEvropochtaTrackingSms::class]);
        $order = $this->order(['customer_phone' => '+375 (29) 000-00-00']);
        $phone = '375291234567';
        Http::fake([
            self::API.'postal/create' => Http::response(['number' => self::TRACK]),
            'https://app.sms.by/api/v1/sendQuickSMS' => function (Request $request) use ($order, $phone) {
                $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
                $this->assertSame($phone, $request['phone']);
                $this->assertSame('42', $request['alphaname_id']);
                $this->assertSame('KOTLOV: трек Европочты '.self::TRACK, $request['message']);

                return Http::response(['sms_id' => 'sms-test-101']);
            },
        ]);
        $shipment = app(EvropochtaOrderShipment::class);
        $shipment->create($order, $this->recipient());
        $shipment->create($order, $this->recipient());

        Http::assertSentCount(1);
        $this->assertSame('pending', $order->fresh()->evropochta_sms_status);
        Bus::assertDispatchedAfterResponse(SendEvropochtaTrackingSms::class, 1);
        $job = Bus::dispatchedAfterResponse(SendEvropochtaTrackingSms::class)->sole();
        $this->assertSame($phone, $job->phone);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        Http::assertSentCount(2);
        $saved = $order->fresh();
        $this->assertSame('accepted', $saved->evropochta_sms_status);
        $this->assertSame('sms-test-101', $saved->evropochta_sms_id);
        $this->assertNotNull($saved->evropochta_sms_sent_at);
    }

    public function test_sms_failure_keeps_the_shipment_and_does_not_retry_an_unconfirmed_send(): void
    {
        $this->enableTrackingSms();
        Bus::fake([SendEvropochtaTrackingSms::class]);
        $order = $this->order();
        Http::fake([
            self::API.'postal/create' => Http::response(['number' => self::TRACK]),
            'https://app.sms.by/api/v1/sendQuickSMS' => Http::failedConnection(),
        ]);

        $this->assertSame(self::TRACK, app(EvropochtaOrderShipment::class)->create($order, $this->recipient()));
        $job = Bus::dispatchedAfterResponse(SendEvropochtaTrackingSms::class)->sole();
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        Http::assertSentCount(2);
        $this->assertSame(self::TRACK, $order->fresh()->evropochta_tracking_number);
        $this->assertSame('unconfirmed', $order->fresh()->evropochta_sms_status);
        $this->assertNull($order->fresh()->evropochta_sms_id);
    }

    public function test_shipment_can_be_created_with_the_tracking_sms_switched_off(): void
    {
        $this->enableTrackingSms();
        Bus::fake([SendEvropochtaTrackingSms::class]);
        $order = $this->order();
        Http::fake([self::API.'postal/create' => Http::response(['number' => self::TRACK])]);

        app(EvropochtaOrderShipment::class)->create($order, $this->recipient() + ['notify_receiver' => false]);

        $this->assertSame('skipped', $order->fresh()->evropochta_sms_status);
        Bus::assertNotDispatchedAfterResponse(SendEvropochtaTrackingSms::class);
        Http::assertSentCount(1);
    }

    public function test_cash_on_delivery_uses_the_order_total_and_the_store_pays_the_fee(): void
    {
        $order = $this->order(['payment_type' => 'evropochta_cod', 'payment_status' => 'pending']);
        Http::fake([self::API.'postal/create' => Http::response(['number' => self::TRACK])]);

        $this->assertSame(self::TRACK, app(EvropochtaOrderShipment::class)->create($order, $this->recipient()));

        [$request] = Http::recorded()->sole();
        $this->assertSame(117.5, $request['payment_amount']);
        $this->assertSame(117.5, $request['declared_amount']);
        $this->assertSame(0, $request['cash_on_delivery_payer']);
        $this->assertSame(0, $request['shipment_payer']);
        $snapshot = $order->fresh()->evropochta_quote['shipment'];
        $this->assertSame(self::TRACK, $snapshot['number']);
        $this->assertEquals(117.5, $snapshot['payment_amount']);
        $this->assertSame(0, $snapshot['shipment_payer']);
        $this->assertSame(0, $snapshot['cash_on_delivery_payer']);
        $this->assertSame('pending', $order->fresh()->payment_status);
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


    public function test_unpaid_manual_order_collects_one_total_and_cannot_move_carrier_fees_to_the_customer(): void
    {
        $order = $this->order(['payment_type' => 'cash', 'payment_status' => 'pending', 'discount' => 10, 'total' => 107.5]);
        Http::fake([self::API.'postal/create' => Http::response(['number' => self::TRACK])]);
        app(EvropochtaOrderShipment::class)->create($order, $this->recipient() + [
            'payment_amount' => 125, 'shipment_payer' => 1, 'cash_on_delivery_payer' => 1,
        ]);
        [$request] = Http::recorded()->sole();
        $this->assertSame(107.5, $request['payment_amount']);
        $this->assertSame(0, $request['shipment_payer']);
        $this->assertSame(0, $request['cash_on_delivery_payer']);
        $this->assertSame('evropochta_cod', $order->fresh()->payment_type);
        $this->assertEquals(107.5, $order->fresh()->evropochta_quote['shipment']['payment_amount']);
    }

    public function test_already_paid_cod_order_does_not_collect_money_again(): void
    {
        $order = $this->order(['payment_type' => 'evropochta_cod', 'payment_status' => 'paid']);
        Http::fake([self::API.'postal/create' => Http::response(['number' => self::TRACK])]);
        app(EvropochtaOrderShipment::class)->create($order, $this->recipient());
        [$request] = Http::recorded()->sole();
        $this->assertArrayNotHasKey('payment_amount', $request->data());
        $this->assertSame(0, $request['shipment_payer']);
        $this->assertSame(0, $order->fresh()->evropochta_quote['shipment']['payment_amount']);
    }

    public function test_changed_payment_review_stops_creation_before_any_carrier_request(): void
    {
        $order = $this->order(['payment_type' => 'cash', 'payment_status' => 'pending']);
        $review = \App\Services\Shipping\EvropochtaPayment::signature($order);
        $order->update(['delivery_price' => 20, 'total' => 120]);
        Http::preventStrayRequests();
        try {
            app(EvropochtaOrderShipment::class)->create($order, $this->recipient() + ['payment_review_signature' => $review]);
            $this->fail('Changed total must be reviewed again.');
        } catch (\App\Services\Shipping\EvropochtaShipmentException $e) {
            $this->assertStringContainsString('изменились', $e->getMessage());
        }
        Http::assertNothingSent();
        $this->assertNull($order->fresh()->evropochta_tracking_number);
    }

    public function test_inconsistent_total_cannot_be_sent_as_cash_on_delivery(): void
    {
        $order = $this->order(['payment_type' => 'evropochta_cod', 'payment_status' => 'pending', 'total' => 135]);
        $this->assertStringContainsString('не совпадают', app(EvropochtaOrderShipment::class)->unavailableReason($order));
        Http::assertNothingSent();
    }

    private function manualOrder(array $attributes = []): Order
    {
        $order = $this->order($attributes + [
            'evropochta_quote' => null,
            'payment_type' => 'evropochta_cod',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'discount' => 10,
            'total' => 0,
            'delivery_price' => 0,
        ]);
        $order->items()->create(['product_name' => 'Элехант СГБ-1,8', 'product_sku' => 'PS-007.059', 'quantity' => 2, 'price' => 100]);

        return $order;
    }

    private function parcelInput(): array
    {
        return ['store_id_start' => 70130010, 'store_id_finish' => 40930011, 'weight' => 1.25,
            'length' => 24, 'width' => 24, 'height' => 20];
    }

    private function fakePreparationTariff(mixed $tariff = null): void
    {
        config(['services.evropochta.checkout_origin' => 70130010]);
        Http::fake([
            self::API.'stores' => Http::response([
                ['id' => 70130010, 'type' => 1, 'ops_number' => 1, 'ops_name' => 'Отделение №1',
                    'city' => 'г. Минск', 'street' => 'Монтажников', 'house' => '2'],
                ['id' => 40930011, 'type' => 1, 'ops_number' => 567, 'ops_name' => 'Отделение №567',
                    'city' => 'г. Солигорск', 'street' => 'Ленинского Комсомола', 'house' => '43', 'region' => 'Минская область'],
            ]),
            self::API.'postal/payment/calculate' => $tariff ?? Http::response(['sender_pays' => 6.34, 'receiver_pays' => 0]),
        ]);
    }

    private function carrierStatus(int $code, string $name): array
    {
        return ['number' => self::TRACK, 'status' => ['value' => $code, 'name' => $name]];
    }

    private function enableTrackingSms(): void
    {
        config([
            'services.sms_by.enabled' => true,
            'services.sms_by.base_url' => 'https://app.sms.by',
            'services.sms_by.token' => 'sms-test-token',
            'services.sms_by.alphaname_id' => '42',
        ]);
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::query()->create([
            'number' => 'ORD-EVROPOCHTA-TEST', 'status' => 'processing',
            'customer_name' => 'Иван Петров', 'customer_phone' => '+375 (29) 123-45-67',
            'delivery_type' => 'evropochta', 'payment_type' => 'bank_transfer', 'payment_status' => 'paid',
            'subtotal' => 100, 'total' => 117.5, 'delivery_price' => 17.5,
            'evropochta_quote' => [
                'store_id_finish' => 40930010, 'address' => 'г. Лида, Красноармейская, 63, №23',
                'parcel' => ['store_id_start' => 70130010, 'weight' => 12.5, 'length' => 40, 'width' => 18, 'height' => 25],
            ],
        ]);

        $order->forceFill($attributes)->save();

        return $order;
    }

    private function recipient(): array
    {
        return [
            'receiver_name' => 'Иван', 'receiver_surname' => 'Петров',
            'receiver_phone_number' => '+375 (29) 123-45-67',
        ];
    }
}
