<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\QuickOrderService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContactRequestCreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): ContactRequest
    {
        Event::fake([NewOrderCreated::class]);
        Http::preventStrayRequests();
        $category = Category::create(['name' => 'Счётчики', 'slug' => 'meters']);
        $product = Product::create(['name' => 'Счётчик', 'slug' => 'meter', 'sku' => 'TEST-METER',
            'category_id' => $category->id, 'is_active' => true, 'price' => 100]);

        return ContactRequest::create(['name' => 'Клиент', 'phone' => '+375291112233', 'email' => 'client@example.test',
            'message' => 'Нужны два счётчика', 'city' => 'Лида', 'product_id' => $product->id,
            'requested_quantity' => 2, 'status' => 'new', 'needs_attention' => true]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'is_active' => true]);
    }

    private function data(ContactRequest $lead): array
    {
        return ['customer_name' => $lead->name, 'customer_phone' => $lead->phone, 'customer_email' => $lead->email,
            'items' => [['product_id' => $lead->product_id, 'quantity' => 2, 'price' => 100]],
            'delivery_type' => 'pickup', 'payment_type' => 'cash', 'status' => 'new'];
    }

    public function test_request_prefills_client_product_quantity_and_requires_delivery_selection(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->manager());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewContactRequest::class, ['record' => $lead->id])->mountAction('createOrder');
        $page->assertActionDataSet(['customer_name' => $lead->name, 'customer_phone' => $lead->phone,
            'customer_email' => $lead->email, 'delivery_city' => 'Лида', 'status' => 'new',
            'delivery_type' => null, 'payment_type' => null]);
        $line = collect($page->get('mountedActions.0.data.items'))->sole();
        $this->assertEquals($lead->product_id, $line['product_id']);
        $this->assertEquals(2, $line['quantity']);
        $this->assertEquals(100, $line['price']);
        $page->callMountedAction()->assertHasActionErrors(['delivery_type' => 'required', 'payment_type' => 'required']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_request_can_quote_manual_packaging_and_create_a_linked_evropochta_order(): void
    {
        $lead = $this->lead();
        config(['services.evropochta.token' => 'test-token', 'services.evropochta.checkout_enabled' => true,
            'services.evropochta.checkout_origin' => 70130010]);
        $api = 'https://api-kassa.evropochta.by/api/external/';
        Http::fake([
            $api.'stores' => Http::response([
                ['id' => 70130010, 'type' => 1, 'ops_name' => 'Отделение №1', 'city' => 'г. Минск'],
                ['id' => 40930010, 'type' => 1, 'ops_number' => 23, 'ops_name' => 'Отделение №23',
                    'city' => 'г. Лида', 'street' => 'Красноармейская', 'house' => '63'],
            ]),
            $api.'postal/payment/calculate' => Http::response(['sender_pays' => 6.34, 'receiver_pays' => 0]),
        ]);
        $this->actingAs($this->manager());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ViewContactRequest::class, ['record' => $lead->id])->mountAction('createOrder');
        $base = 'mountedActions.0.data';
        $page->set($base.'.delivery_type', 'evropochta')
            ->set($base.'.different_receiver', true)
            ->assertSet($base.'.recipient.receiver_phone_number', $lead->phone)
            ->set($base.'.recipient.receiver_surname', 'Тестовая')
            ->set($base.'.recipient.receiver_name', 'Надежда')
            ->set($base.'.recipient.receiver_patronymic_name', 'Ивановна')
            ->set($base.'.evropochta_branch', '40930010')->assertSet($base.'.evropochta_price', null)
            ->set($base.'.parcel.weight', 2)->set($base.'.parcel.length', 30)
            ->set($base.'.parcel.width', 20)->set($base.'.parcel.height', 15)
            ->assertSet($base.'.evropochta_price', 8)
            ->callMountedAction()->assertHasNoActionErrors();
        $order = Order::sole();
        $this->assertSame($order->id, $lead->fresh()->order_id);
        $this->assertSame('done', $lead->fresh()->status);
        $this->assertFalse($lead->fresh()->needs_attention);
        $this->assertSame('Лида', $order->delivery_city);
        $this->assertSame(40930010, $order->evropochta_quote['store_id_finish']);
        $this->assertSame('Надежда', $order->evropochta_quote['recipient']['receiver_name']);
        $this->assertSame($lead->name, $order->customer_name);
        $this->assertSame('208.00', $order->total);
        $this->assertNull($order->evropochta_tracking_number);
        $this->assertNull($lead->product->shipping_parcel);
        Event::assertDispatchedTimes(NewOrderCreated::class, 1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/postal/add'));
        Livewire::test(ViewContactRequest::class, ['record' => $lead->id])
            ->assertActionHidden('createOrder')->assertActionVisible('openOrder')->assertSee($order->number);
    }

    public function test_repeated_create_returns_original_order_without_repeating_events(): void
    {
        $lead = $this->lead();
        $manager = $this->manager();
        $service = app(QuickOrderService::class);
        $order = $service->create($this->data($lead), $manager, $lead);
        // A stale open form may now have an expired quote or no submitted data at all.
        $again = $service->create([], $manager, $lead);
        $this->assertSame($order->id, $again->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        Event::assertDispatchedTimes(NewOrderCreated::class, 1);
    }

    public function test_incomplete_evropochta_keeps_request_open_and_does_not_create_order(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->manager());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewContactRequest::class, ['record' => $lead->id])
            ->callAction('createOrder', array_replace($this->data($lead), ['delivery_type' => 'evropochta', 'payment_type' => 'evropochta_cod']))
            ->assertHasActionErrors(['evropochta_branch' => 'required', 'evropochta_token' => 'required']);
        $this->assertNull($lead->fresh()->order_id);
        $this->assertSame('new', $lead->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
        Event::assertNotDispatched(NewOrderCreated::class);
    }

    public function test_manual_parcel_limits_do_not_allow_saving_without_a_real_tariff(): void
    {
        $lead = $this->lead();
        config(['services.evropochta.token' => 'test-token', 'services.evropochta.checkout_enabled' => true]);
        $service = app(QuickOrderService::class);
        $items = $this->data($lead)['items'];
        $this->assertNull($service->assessEvropochta($items, ['weight' => 31, 'length' => 30, 'width' => 20, 'height' => 15])['parcel']);
        $this->assertNull($service->assessEvropochta($items, ['weight' => 2, 'length' => 30, 'width' => 20])['parcel']);
        try {
            $service->create(array_replace($this->data($lead), ['delivery_type' => 'evropochta', 'payment_type' => 'evropochta_cod',
                'evropochta_branch' => 40930010, 'evropochta_token' => 'fabricated-token',
                'parcel' => ['weight' => 2, 'length' => 30, 'width' => 20, 'height' => 15]]), $this->manager(), $lead);
            $this->fail('A manually entered price or fabricated token must not create an order.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('расчёта тарифа', $exception->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull($lead->fresh()->order_id);
        Http::assertNothingSent();
    }

    public function test_archived_product_is_not_silently_ordered_and_client_cannot_convert_request(): void
    {
        $lead = $this->lead();
        $lead->product->update(['is_archived' => true]);
        $this->actingAs($this->manager());
        $page = Livewire::test(ViewContactRequest::class, ['record' => $lead->id])->mountAction('createOrder');
        $this->assertNull(collect($page->get('mountedActions.0.data.items'))->sole()['product_id']);
        $this->expectException(HttpException::class);
        app(QuickOrderService::class)->create($this->data($lead), User::factory()->create(['role' => 'client']), $lead);
    }
}
