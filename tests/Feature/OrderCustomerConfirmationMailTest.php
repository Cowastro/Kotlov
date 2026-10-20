<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Listeners\SendOrderConfirmationToCustomer;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCustomerConfirmationMailTest extends TestCase
{
    #[Test]
    public function it_sends_confirmation_only_to_the_registered_accounts_email(): void
    {
        Mail::fake();

        $user = new User(['email' => 'account@example.test']);
        $user->id = 77;

        $order = new Order([
            'number' => 'ORD-2026-0180',
            'customer_name' => 'Покупатель',
            'customer_email' => 'contact@example.test',
            'delivery_price' => 10,
            'total' => 66,
        ]);
        $order->user_id = 77;
        $order->setRelation('user', $user);
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
            $mail->hasTo('account@example.test')
            && ! $mail->hasTo('contact@example.test')
            && ! $mail->hasTo('supplier@example.test')
        );
    }

    #[Test]
    public function it_does_not_send_for_a_guest_even_when_contact_email_is_present(): void
    {
        Mail::fake();

        $order = new Order([
            'number' => 'ORD-2026-0181',
            'customer_name' => 'Гостевой покупатель',
            'customer_email' => 'guest@example.test',
        ]);
        $order->setRelation('items', collect());
        $order->setRelation('user', null);

        app(SendOrderConfirmationToCustomer::class)->handle(new NewOrderCreated($order));

        Mail::assertNothingSent();
    }
}
