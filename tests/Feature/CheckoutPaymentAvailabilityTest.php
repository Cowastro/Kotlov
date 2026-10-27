<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->assertSee('менеджер подтвердит доступность рассрочки и поможет с оформлением')
            ->assertSee('Смотреть видеоинструкцию')
            ->assertSee('https://www.youtube.com/watch?v=nw2v3z5BZms', false)
            ->assertSee('Как оформить рассрочку')
            ->assertSee(route('installment.info'), false)
            ->assertDontSee('sbg.by', false);
    }

    public function test_checkout_offers_own_transport_and_shows_its_route_schedule(): void
    {
        $product = $this->orderableProduct();

        $this->withSession(['cart' => $this->cartFor($product)])
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('value="own_transport"', false)
            ->assertSeeText('Нашим транспортом в пределах МКАД-2')
            ->assertSeeText('Нашим транспортом в города по графику')
            ->assertSeeText('Минск, населённые пункты в пределах МКАД-2 и города по маршрутам')
            ->assertSeeText('Минск и населённые пункты в пределах МКАД-2: 10 BYN, от 400 BYN — бесплатно.')
            ->assertSeeText('Города по графику: менее 500 BYN — 30 BYN; от 500 до 999 BYN — 20 BYN; от 1 000 BYN — бесплатно.')
            ->assertSeeText('Новополоцк')
            ->assertSeeText('Барановичи')
            ->assertDontSeeText('ТК КИТ');

        $this->assertSame(10.0, CheckoutController::calcDelivery('courier', 399));
        $this->assertSame(0.0, CheckoutController::calcDelivery('courier', 400));
        $this->assertSame(30.0, CheckoutController::calcDelivery('own_transport', 499.99));
        $this->assertSame(20.0, CheckoutController::calcDelivery('own_transport', 500));
        $this->assertSame(20.0, CheckoutController::calcDelivery('own_transport', 999.99));
        $this->assertSame(0.0, CheckoutController::calcDelivery('own_transport', 1000));
    }

    public function test_installment_button_target_preselects_six_month_installment(): void
    {
        $product = $this->orderableProduct();

        $response = $this->withSession(['cart' => $this->cartFor($product)])
            ->get(route('checkout', ['payment_type' => 'installment_6']))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="pay-installment_6"[\s\S]*?value="installment_6"[\s\S]*?checked/',
            $response->getContent(),
        );
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

    public function test_company_lookup_returns_official_tax_registry_requisites(): void
    {
        $this->fakeTaxpayerRegistry();

        $this->postJson(route('checkout.company-lookup'), ['unp' => '193000777'])
            ->assertOk()
            ->assertJsonPath('data.unp', '193000777')
            ->assertJsonPath('data.legal_name', 'Общество с ограниченной ответственностью «Тест Тепло»')
            ->assertJsonPath('data.address', 'г. Минск, ул. Тестовая, 7')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone');
    }

    public function test_invoice_checkout_creates_a_draft_from_registry_and_requires_manager_stock_confirmation(): void
    {
        Notification::fake();
        $this->fakeTaxpayerRegistry();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->orderableProduct();

        $payload = $this->checkoutData('invoice', 'invoice-request') + [
            'company_unp' => '193000777',
            'company_name' => 'Подменённое название',
            'company_address' => 'Подменённый адрес',
            'company_email' => 'buh@example.by',
        ];

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession(['cart' => $this->cartFor($product)])
            ->post(route('checkout.store'), $payload)
            ->assertRedirect();

        $order = Order::query()->latest('id')->with('items', 'commercialDocument.items')->firstOrFail();
        $invoice = $order->commercialDocument;

        $this->assertNotNull($invoice);
        $this->assertSame('invoice', $invoice->type);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('Общество с ограниченной ответственностью «Тест Тепло»', $invoice->client_legal_name);
        $this->assertSame('г. Минск, ул. Тестовая, 7', $invoice->client_address);
        $this->assertSame('buh@example.by', $invoice->client_email);
        $this->assertSame($product->id, $invoice->items->first()->product_id);
        $this->assertSame($invoice->crm_counterparty_id, $order->crm_counterparty_id);

        $readiness = app(CommercialDocumentReadiness::class);
        $this->assertContains(
            'менеджер не подтвердил наличие и способ исполнения всех позиций заказа',
            $readiness->issues($invoice),
        );

        $order->items()->update(['fulfillment_confirmed_at' => now()]);
        $this->assertNotContains(
            'менеджер не подтвердил наличие и способ исполнения всех позиций заказа',
            $readiness->issues($invoice->fresh()),
        );

        Notification::assertSentTo(
            $manager,
            DatabaseNotification::class,
            fn (DatabaseNotification $notification): bool => str_contains(
                (string) $notification->data['title'],
                'Запрос счёта',
            ) && str_contains(
                (string) $notification->data['body'],
                'Черновик счёта',
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

    private function fakeTaxpayerRegistry(): void
    {
        Http::fake([
            'grp.nalog.gov.by/*' => Http::response([
                'row' => [
                    'vunp' => '193000777',
                    'vnaimp' => 'Общество с ограниченной ответственностью «Тест Тепло»',
                    'vnaimk' => 'ООО «Тест Тепло»',
                    'vpadres' => 'г. Минск, ул. Тестовая, 7',
                    'vkods' => 'Действующий',
                    'dreg' => '2020-01-01',
                ],
            ]),
        ]);
    }
}
