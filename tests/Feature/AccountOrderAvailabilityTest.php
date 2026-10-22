<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderAvailabilityConfirmedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Orders\OrderAvailabilityConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AccountOrderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_publishes_order_and_notifies_customer_only_once(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['email' => 'customer@example.test']);
        $manager = User::factory()->create(['role' => 'admin']);
        $order = $this->createOrder($customer, [
            'status' => 'checking',
            'customer_email' => 'checkout@example.test',
        ]);
        $this->createConfirmedItem($order);

        $first = app(OrderAvailabilityConfirmation::class)->confirmAndNotify($order, $manager);
        $second = app(OrderAvailabilityConfirmation::class)->confirmAndNotify($order->fresh(), $manager);

        $order->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->availability_confirmed_at);
        $this->assertSame($manager->id, $order->availability_confirmed_by);
        $this->assertNotNull($order->customer_notified_at);
        $this->assertTrue($first['notified']);
        $this->assertFalse($second['notified']);

        Mail::assertQueued(OrderAvailabilityConfirmedMail::class, function (OrderAvailabilityConfirmedMail $mail) use ($order): bool {
            return $mail->order->is($order)
                && $mail->hasTo('customer@example.test');
        });
        Mail::assertQueuedCount(1);
    }

    public function test_customer_can_open_only_their_confirmed_order_without_internal_supply_data(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $order = $this->createOrder($customer, [
            'status' => 'confirmed',
            'availability_confirmed_at' => now(),
            'payment_type' => 'webpay',
            'admin_comment' => 'Служебная заметка только для CRM',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Котёл тестовый',
            'product_sku' => 'TEST-100',
            'price' => 120,
            'quantity' => 2,
            'total' => 240,
            'fulfillment_supplier_name' => 'Секретный поставщик',
            'fulfillment_supplier_contact' => 'supplier@example.test',
            'fulfillment_purchase_price' => 75,
            'fulfillment_confirmed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Котёл тестовый')
            ->assertSeeText('240,00 BYN')
            ->assertSeeText('Онлайн-оплата временно недоступна')
            ->assertDontSeeText('Секретный поставщик')
            ->assertDontSeeText('supplier@example.test')
            ->assertDontSeeText('Служебная заметка только для CRM')
            ->assertDontSeeText('75,00 BYN');

        $this->actingAs($otherCustomer)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_customer_order_page_shows_the_current_delivery_status_in_the_main_banner(): void
    {
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, [
            'status' => 'shipped',
            'availability_confirmed_at' => now(),
        ]);
        $this->createConfirmedItem($order);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Передан в доставку')
            ->assertSeeText('Заказ собран и передан в доставку или подготовлен к выдаче клиенту.');
    }

    public function test_order_link_is_hidden_until_availability_is_confirmed(): void
    {
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, ['status' => 'checking']);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSeeText('После проверки наличия здесь появится кнопка заказа')
            ->assertDontSee(route('account.orders.show', $order), false);
    }

    public function test_confirmed_sandbox_order_has_a_clear_payment_action_in_account(): void
    {
        config()->set('services.webpay.enabled', true);
        config()->set('services.webpay.test_mode', true);
        config()->set('services.webpay.store_id', '833122335');
        config()->set('services.webpay.secret_key', 'test-secret');
        config()->set('services.webpay.username', 'test-user');
        config()->set('services.webpay.password', 'test-password');
        config()->set('services.webpay.sandbox_emails', 'test@kotlov.by');

        $customer = User::factory()->create(['email' => 'test@kotlov.by']);
        $order = $this->createOrder($customer, [
            'number' => 'ORD-2026-0187',
            'status' => 'confirmed',
            'availability_confirmed_at' => now(),
        ]);
        $this->createConfirmedItem($order);

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSeeText('ORD-2026-0187')
            ->assertSeeText('Открыть и оплатить')
            ->assertSeeText('Оплата картой через WEBPAY')
            ->assertSee(route('account.orders.show', $order), false)
            ->assertDontSee('class="overflow-auto"', false);
    }

    public function test_manager_can_publish_confirmed_own_stock_order_without_external_supplier(): void
    {
        Mail::fake();

        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $order = $this->createOrder($manager, ['status' => 'checking']);
        $this->createConfirmedItem($order);

        Livewire::actingAs($manager)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('publishOrderOnline', [
                'availability_status' => 'available',
                'availability_confirmation' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($order->portalLinks()->exists());
    }

    private function createOrder(User $customer, array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'number' => 'ORD-ACCOUNT-'.fake()->unique()->numerify('####'),
            'status' => 'new',
            'customer_name' => $customer->name,
            'customer_phone' => '+375291234567',
            'customer_email' => $customer->email,
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 240,
            'total' => 240,
        ], $attributes));
    }

    private function createConfirmedItem(Order $order): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Тестовый товар',
            'price' => 240,
            'quantity' => 1,
            'total' => 240,
            'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'available',
            'fulfillment_confirmed_at' => now(),
        ]);
    }
}
