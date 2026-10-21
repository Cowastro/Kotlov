<?php

namespace Tests\Feature;

use App\Mail\OrderAvailabilityConfirmedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Orders\OrderAvailabilityConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            ->assertSeeText('Онлайн-оплата станет доступна здесь после подключения WEBPAY')
            ->assertDontSeeText('Секретный поставщик')
            ->assertDontSeeText('supplier@example.test')
            ->assertDontSeeText('Служебная заметка только для CRM')
            ->assertDontSeeText('75,00 BYN');

        $this->actingAs($otherCustomer)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
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
            ->assertSeeText('Ссылка появится после проверки наличия')
            ->assertDontSee(route('account.orders.show', $order), false);
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
