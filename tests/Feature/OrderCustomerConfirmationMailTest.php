<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Listeners\SendOrderConfirmationToCustomer;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCustomerConfirmationMailTest extends TestCase
{
    #[Test]
    public function it_sends_confirmation_only_to_the_customer_email(): void
    {
        Mail::fake();

        $order = new Order([
            'number' => 'ORD-2026-0180',
            'customer_name' => 'Покупатель',
            'customer_email' => 'buyer@example.test',
            'delivery_price' => 10,
            'total' => 66,
        ]);
        $order->setRelation('items', collect([
            new OrderItem([
                'product_name' => 'Датчик загазованности дым + CO GKB CO777',
                'product_sku' => 'KOTLOV-004901',
                'quantity' => 1,
                'total' => 56,
            ]),
        ]));

        app(SendOrderConfirmationToCustomer::class)->handle(new NewOrderCreated($order));

        Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail): bool =>
            $mail->hasTo('buyer@example.test')
            && ! $mail->hasTo('supplier@example.test')
        );
    }

    #[Test]
    public function it_does_not_send_when_customer_email_is_missing(): void
    {
        Mail::fake();

        $order = new Order([
            'number' => 'ORD-2026-0181',
            'customer_name' => 'Покупатель без email',
            'customer_email' => null,
        ]);
        $order->setRelation('items', collect());

        app(SendOrderConfirmationToCustomer::class)->handle(new NewOrderCreated($order));

        Mail::assertNothingSent();
    }
}
