<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutPaymentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_hides_online_card_payment_and_explains_installment_follow_up(): void
    {
        $product = $this->orderableProduct();

        $this->withSession(['cart' => $this->cartFor($product)])
            ->get(route('checkout'))
            ->assertOk()
            ->assertDontSee('Онлайн-оплата картой')
            ->assertDontSee('Оплата через WEBPAY')
            ->assertSee('Рассрочка на 6 месяцев')
            ->assertSee('менеджер свяжется с вами и поможет с оформлением');
    }

    public function test_disabled_online_card_payment_cannot_be_submitted_directly(): void
    {
        $product = $this->orderableProduct();

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession(['cart' => $this->cartFor($product)])
            ->from(route('checkout'))
            ->post(route('checkout.store'), $this->checkoutData('online_card', 'disabled-online-card'))
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['payment_type']);

        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_installment_request_is_created_and_highlighted_for_managers(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->orderableProduct();

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession(['cart' => $this->cartFor($product)])
            ->post(route('checkout.store'), $this->checkoutData('installment_6', 'installment-request'))
            ->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame('installment_6', $order->payment_type);
        $this->assertSame('+375291112233', $order->customer_phone);
        Notification::assertSentTo(
            $manager,
            DatabaseNotification::class,
            fn (DatabaseNotification $notification): bool => str_contains(
                (string) $notification->data['title'],
                'Заявка на рассрочку',
            ) && str_contains(
                (string) $notification->data['body'],
                'Связаться с клиентом',
            ),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function cartFor(Product $product): array
    {
        return [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category_slug' => $product->category->slug,
                'sku' => $product->sku,
                'price' => 200,
                'quantity' => 1,
            ],
        ];
    }

    /** @return array<string, string> */
    private function checkoutData(string $paymentType, string $token): array
    {
        return [
            'order_token' => $token,
            'customer_name' => 'Покупатель в рассрочку',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => $paymentType,
        ];
    }

    private function orderableProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Оплата checkout',
            'slug' => 'checkout-payment-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для проверки оплаты',
            'slug' => 'checkout-payment-product',
            'sku' => 'CHECKOUT-PAYMENT',
            'price' => 200,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'checkout-payment-stock',
            'name' => 'Поставщик checkout',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'CHECKOUT-PAYMENT',
            'in_stock' => true,
            'stock_quantity' => 5,
        ]);

        return $product;
    }
}
