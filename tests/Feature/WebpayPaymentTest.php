<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WebpayPaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebpayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-without-ampersand';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.webpay', [
            'enabled' => true,
            'test_mode' => true,
            'store_id' => '123456789',
            'store_name' => 'KOTLOV.BY',
            'secret_key' => self::SECRET,
            'username' => 'sandbox-user',
            'password' => 'sandbox-password',
            'payment_url' => 'https://securesandbox.webpay.by/',
            'api_url' => 'https://sandbox.webpay.by',
            'currency' => 'BYN',
            'timeout' => 5,
            'sandbox_emails' => 'test@kotlov.by',
        ]);
    }

    public function test_only_owner_of_confirmed_order_can_start_signed_payment(): void
    {
        $customer = User::factory()->create(['email' => 'test@kotlov.by']);
        $other = User::factory()->create();
        $order = $this->createConfirmedOrder($customer);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Оплатить через WEBPAY');

        $this->actingAs($other)
            ->post(route('account.orders.webpay.start', $order))
            ->assertNotFound();

        $response = $this->actingAs($customer)
            ->post(route('account.orders.webpay.start', $order));

        $attempt = WebpayPaymentAttempt::query()->sole();
        $expectedSignature = sha1(
            $attempt->seed
            .'123456789'
            .$attempt->merchant_order_num
            .'1'
            .'BYN'
            .'240.00'
            .self::SECRET
        );

        $response->assertOk()
            ->assertSee('https://securesandbox.webpay.by/', false)
            ->assertSee('name="wsb_order_num" value="'.$attempt->merchant_order_num.'"', false)
            ->assertSee('name="wsb_signature" value="'.$expectedSignature.'"', false)
            ->assertSee('name="wsb_total" value="240.00"', false);

        $this->assertSame('webpay', $order->fresh()->payment_type);
        $this->assertSame('pending', $attempt->status);
    }

    public function test_unconfirmed_order_cannot_start_payment(): void
    {
        $customer = User::factory()->create(['email' => 'test@kotlov.by']);
        $order = $this->createConfirmedOrder($customer, [
            'status' => 'checking',
            'availability_confirmed_at' => null,
        ]);

        $this->actingAs($customer)
            ->post(route('account.orders.webpay.start', $order))
            ->assertNotFound();

        $this->assertDatabaseCount('webpay_payment_attempts', 0);
    }

    public function test_sandbox_payment_is_hidden_from_customers_outside_allowlist(): void
    {
        $customer = User::factory()->create(['email' => 'customer@example.test']);
        $order = $this->createConfirmedOrder($customer);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSeeText('Оплатить через WEBPAY');

        $this->actingAs($customer)
            ->post(route('account.orders.webpay.start', $order))
            ->assertRedirect(route('account.orders.show', $order))
            ->assertSessionHas('payment_error');

        $this->assertDatabaseCount('webpay_payment_attempts', 0);
    }

    public function test_signed_notification_is_verified_with_billing_api_and_marks_order_paid_once(): void
    {
        $customer = User::factory()->create();
        $order = $this->createConfirmedOrder($customer);
        $attempt = $this->createAttempt($order);
        $payload = $this->notificationPayload($attempt);

        Http::fake([
            'https://sandbox.webpay.by' => Http::response($this->transactionXml($payload), 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);

        $this->post(route('webpay.notify'), $payload)
            ->assertOk()
            ->assertSeeText('OK');

        $order->refresh();
        $attempt->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('webpay', $order->payment_type);
        $this->assertSame('paid', $attempt->status);
        $this->assertNotNull($attempt->verified_at);
        $this->assertSame($payload['transaction_id'], $attempt->transaction_id);

        $this->post(route('webpay.notify'), $payload)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_notification_with_invalid_signature_is_rejected_without_api_call(): void
    {
        Http::fake();

        $order = $this->createConfirmedOrder(User::factory()->create());
        $attempt = $this->createAttempt($order);
        $payload = $this->notificationPayload($attempt);
        $payload['amount'] = '1.00';

        $this->post(route('webpay.notify'), $payload)->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $attempt->fresh()->status);
        Http::assertNothingSent();
    }

    private function createConfirmedOrder(User $customer, array $attributes = []): Order
    {
        $order = Order::query()->create(array_merge([
            'user_id' => $customer->id,
            'number' => 'ORD-WEBPAY-'.fake()->unique()->numerify('####'),
            'status' => 'confirmed',
            'customer_name' => $customer->name,
            'customer_phone' => '+375291234567',
            'customer_email' => $customer->email,
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'availability_confirmed_at' => now(),
            'subtotal' => 240,
            'discount' => 0,
            'delivery_price' => 0,
            'total' => 240,
        ], $attributes));

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Тестовый котёл',
            'product_sku' => 'TEST-1',
            'price' => 240,
            'quantity' => 1,
            'total' => 240,
            'fulfillment_confirmed_at' => now(),
        ]);

        return $order;
    }

    private function createAttempt(Order $order): WebpayPaymentAttempt
    {
        return WebpayPaymentAttempt::query()->create([
            'order_id' => $order->id,
            'merchant_order_num' => 'KTLV-'.$order->id.'-TEST-123',
            'amount' => '240.00',
            'currency' => 'BYN',
            'environment' => 'sandbox',
            'status' => 'pending',
            'seed' => 'fixed-seed',
        ]);
    }

    /** @return array<string, string> */
    private function notificationPayload(WebpayPaymentAttempt $attempt): array
    {
        $payload = [
            'batch_timestamp' => '1792630000',
            'currency_id' => 'BYN',
            'amount' => '240.00',
            'payment_method' => 'cc',
            'order_id' => '99887766',
            'site_order_id' => $attempt->merchant_order_num,
            'transaction_id' => 'TX-123456',
            'payment_type' => '4',
            'rrn' => 'RRN-123456',
        ];

        $payload['wsb_signature'] = md5(implode('', $payload).self::SECRET);

        return $payload;
    }

    /** @param array<string, string> $payload */
    private function transactionXml(array $payload): string
    {
        $fields = [
            'transaction_id' => $payload['transaction_id'],
            'batch_timestamp' => $payload['batch_timestamp'],
            'currency_id' => $payload['currency_id'],
            'amount' => $payload['amount'],
            'payment_method' => $payload['payment_method'],
            'payment_type' => $payload['payment_type'],
            'order_id' => $payload['order_id'],
            'rrn' => $payload['rrn'],
        ];
        $fields['wsb_signature'] = md5(implode('', $fields).self::SECRET);

        return '<?xml version="1.0" encoding="UTF-8"?><wsb_api_response><fields>'
            .collect($fields)->map(fn (string $value, string $key): string => "<{$key}>{$value}</{$key}>")->implode('')
            .'</fields></wsb_api_response>';
    }
}
