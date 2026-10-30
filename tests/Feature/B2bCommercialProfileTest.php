<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bPriceLevel;
use App\Models\Category;
use App\Models\CrmCounterparty;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\B2bCatalogOfferResolver;
use App\Services\B2bCheckoutTerms;
use App\Services\B2bClientWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class B2bCommercialProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_client_receives_default_partner_price_level(): void
    {
        $partnerLevel = B2bPriceLevel::query()->where('code', 'partner')->firstOrFail();
        $user = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Новый партнёр»',
            'contact_email' => $user->email,
        ]);

        $approved = app(B2bClientWorkflow::class)->approve($client);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($partnerLevel->id, $approved->b2b_price_level_id);
    }

    public function test_price_level_adjusts_only_the_ready_sale_price_and_is_saved_in_cart(): void
    {
        [$user, $client, $offer] = $this->fixture();
        $level = B2bPriceLevel::query()->where('code', 'professional')->firstOrFail();
        $level->update(['sale_price_discount_percent' => 5]);
        $client->update(['b2b_price_level_id' => $level->id]);

        $quote = app(B2bCatalogOfferResolver::class)->priceQuote($offer->fresh(['product', 'source']), $user->fresh());

        $this->assertSame(100.0, $quote['base_price']);
        $this->assertSame(95.0, $quote['price']);
        $this->assertSame('professional', $quote['price_level_code']);
        $this->assertSame(5.0, $quote['discount_percent']);

        $this->actingAs($user)
            ->postJson(route('cart.add'), [
                'product_id' => $offer->product_id,
                'quantity' => 2,
                'pricing_mode' => 'b2b',
            ])
            ->assertOk()
            ->assertJsonPath('subtotal', 190);

        $item = session('cart')[$offer->product_id];
        $this->assertSame(100.0, $item['b2b_base_price']);
        $this->assertSame(95.0, $item['price']);
        $this->assertSame('professional', $item['b2b_price_level_code']);
    }

    public function test_cabinet_shows_structured_commercial_conditions(): void
    {
        [$user, $client] = $this->fixture();
        $level = B2bPriceLevel::query()->where('code', 'partner')->firstOrFail();
        $client->update([
            'b2b_price_level_id' => $level->id,
            'service_mode' => 'online_warehouse',
            'payment_terms' => 'Предоплата по счёту',
            'delivery_terms' => 'Доставка 1–3 рабочих дня',
            'minimum_order_amount' => 500,
            'payment_deferment_days' => 0,
            'commercial_terms_valid_until' => '2026-12-31',
        ]);

        $this->actingAs($user)
            ->get(route('b2b.cabinet'))
            ->assertOk()
            ->assertSeeText('Ваши условия')
            ->assertSeeText('Опт-1 · Партнёр')
            ->assertSeeText('Онлайн-склад')
            ->assertSeeText('Предоплата по счёту')
            ->assertSeeText('Доставка 1–3 рабочих дня')
            ->assertSeeText('500,00 BYN')
            ->assertSeeText('31.12.2026');
    }

    public function test_b2b_checkout_uses_business_payments_and_prefills_company_details(): void
    {
        config()->set('shop.checkout_payment_methods', ['cash', 'installment_6']);
        [$user, $client, $offer] = $this->fixture();
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'ООО «Партнёр»',
            'legal_name' => 'Общество с ограниченной ответственностью «Партнёр»',
            'unp' => '190000001',
            'legal_address' => '220000, г. Минск, ул. Деловая, 1',
            'source' => 'manual',
        ]);
        $client->update([
            'counterparty_id' => $counterparty->id,
            'contact_email' => 'buh@partner.by',
            'payment_terms' => 'Предоплата по счёту',
        ]);

        $this->actingAs($user)->postJson(route('cart.add'), [
            'product_id' => $offer->product_id,
            'quantity' => 2,
            'pricing_mode' => 'b2b',
        ])->assertOk();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('value="invoice"', false)
            ->assertDontSee('value="installment_6"', false)
            ->assertSee('value="190000001"', false)
            ->assertSee('value="Общество с ограниченной ответственностью «Партнёр»"', false)
            ->assertSee('value="buh@partner.by"', false)
            ->assertSee('value="220000, г. Минск, ул. Деловая, 1"', false)
            ->assertSeeText('Партнёрская цена действует для самостоятельного онлайн-заказа');
    }

    public function test_deferred_invoice_uses_and_updates_the_company_credit_limit(): void
    {
        Mail::fake();
        Http::fake([
            'grp.nalog.gov.by/*' => Http::response([
                'row' => [
                    'vunp' => '190000001',
                    'vnaimp' => 'Общество с ограниченной ответственностью «Партнёр»',
                    'vnaimk' => 'ООО «Партнёр»',
                    'vpadres' => 'г. Минск, ул. Деловая, 1',
                    'vkods' => 'Действующий',
                ],
            ]),
        ]);
        [$user, $client, $offer] = $this->fixture();
        $client->update([
            'payment_deferment_days' => 14,
            'credit_limit' => 1000,
            'payment_terms' => 'Отсрочка 14 календарных дней',
        ]);

        $this->actingAs($user)->postJson(route('cart.add'), [
            'product_id' => $offer->product_id,
            'quantity' => 2,
            'pricing_mode' => 'b2b',
        ])->assertOk();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('value="invoice_deferred"', false)
            ->assertSeeText('По счёту с отсрочкой 14 дней')
            ->assertSeeText('Доступный кредитный лимит: 1 000,00 BYN');

        $this->withHeader('User-Agent', 'Mozilla/5.0')->post(route('checkout.store'), [
            'order_token' => 'deferred-b2b-order',
            'customer_name' => 'ООО Партнёр',
            'customer_phone' => '+375291112233',
            'customer_email' => 'buh@partner.by',
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice_deferred',
            'company_name' => 'ООО «Партнёр»',
            'company_unp' => '190000001',
            'company_address' => 'г. Минск, ул. Деловая, 1',
            'company_email' => 'buh@partner.by',
        ])->assertRedirect();

        $order = Order::query()->latest('id')->with('commercialDocument')->firstOrFail();
        $this->assertSame('invoice_deferred', $order->payment_type);
        $this->assertSame(200.0, (float) $order->total);
        $this->assertStringContainsString('14 календарных дней', $order->commercialDocument->payment_terms);

        $credit = app(B2bCheckoutTerms::class)->creditSummary($client->fresh());
        $this->assertSame(200.0, $credit['exposure']);
        $this->assertSame(800.0, $credit['available']);
    }

    public function test_deferred_invoice_is_not_offered_when_the_order_exceeds_available_credit(): void
    {
        [$user, $client, $offer] = $this->fixture();
        $client->update([
            'payment_deferment_days' => 14,
            'credit_limit' => 1000,
        ]);
        Order::query()->create([
            'user_id' => $user->id,
            'number' => 'B2B-CREDIT-USED',
            'status' => 'new',
            'customer_name' => $user->name,
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice_deferred',
            'payment_status' => 'pending',
            'subtotal' => 900,
            'total' => 900,
        ]);

        $this->actingAs($user)->postJson(route('cart.add'), [
            'product_id' => $offer->product_id,
            'quantity' => 2,
            'pricing_mode' => 'b2b',
        ])->assertOk();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertDontSee('value="invoice_deferred"', false)
            ->assertSeeText('доступно 100,00 BYN из 1 000,00 BYN');

        $this->post(route('checkout.store'), [
            'order_token' => 'over-credit-limit',
            'customer_name' => 'ООО Партнёр',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice_deferred',
        ])->assertSessionHasErrors('payment_type');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_minimum_b2b_order_is_enforced_before_checkout_and_on_submission(): void
    {
        [$user, $client, $offer] = $this->fixture();
        $client->update(['minimum_order_amount' => 500]);

        $this->actingAs($user)->postJson(route('cart.add'), [
            'product_id' => $offer->product_id,
            'quantity' => 2,
            'pricing_mode' => 'b2b',
        ])->assertOk();

        $this->get(route('checkout'))
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'До минимальной суммы B2B-заказа не хватает 300,00 BYN.');

        $this->post(route('checkout.store'), [
            'order_token' => 'minimum-b2b-order',
            'customer_name' => 'ООО Партнёр',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice',
        ])->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'До минимальной суммы B2B-заказа не хватает 300,00 BYN.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_commercial_terms_block_b2b_checkout(): void
    {
        [$user, $client, $offer] = $this->fixture();
        $client->update(['commercial_terms_valid_until' => now()->subDay()->toDateString()]);

        $this->actingAs($user)->postJson(route('cart.add'), [
            'product_id' => $offer->product_id,
            'quantity' => 1,
            'pricing_mode' => 'b2b',
        ])->assertOk();

        $this->get(route('checkout'))
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'Срок ваших коммерческих условий истёк. Менеджер должен подтвердить новые условия перед заказом.');
    }

    public function test_cabinet_lists_explicitly_allowed_partner_warehouses(): void
    {
        [$user, $client] = $this->fixture();
        $supplier = Supplier::query()->create([
            'code' => 'partner-warehouse-test',
            'name' => 'Поставщик для клиента',
            'is_active' => true,
            'b2b_partner_enabled' => true,
            'b2b_client_warehouse_label' => 'Склад партнёра · Гомель',
        ]);
        $client->update(['allowed_supplier_ids' => [$supplier->id]]);

        $this->actingAs($user)
            ->get(route('b2b.cabinet'))
            ->assertOk()
            ->assertSeeText('Партнёрские склады')
            ->assertSeeText('Склад партнёра · Гомель');
    }

    public function test_order_item_keeps_price_level_snapshot(): void
    {
        [$user, , $offer] = $this->fixture();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'B2B-SNAPSHOT-1',
            'status' => 'new',
            'customer_name' => $user->name,
            'customer_phone' => '+375290000000',
            'customer_email' => $user->email,
            'delivery_type' => 'pickup',
            'payment_type' => 'invoice',
            'payment_status' => 'pending',
            'subtotal' => 95,
            'total' => 95,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $offer->product_id,
            'product_name' => $offer->name,
            'price' => 95,
            'quantity' => 1,
            'pricing_type' => 'b2b',
            'b2b_base_price' => 100,
            'b2b_price_level_id' => 2,
            'b2b_price_level_code' => 'professional',
            'b2b_price_level_name' => 'Опт-2 · Профессионал',
            'b2b_discount_percent' => 5,
            'b2b_priced_at' => now(),
        ]);

        $this->assertSame('professional', $item->fresh()->b2b_price_level_code);
        $this->assertSame('100.00', $item->fresh()->b2b_base_price);
        $this->assertNotNull($item->fresh()->b2b_priced_at);
    }

    /** @return array{User, B2bClient, IntegrationProduct} */
    private function fixture(): array
    {
        $category = Category::query()->create([
            'name' => 'B2B тест',
            'slug' => 'b2b-commercial-profile-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар собственного склада',
            'slug' => 'own-warehouse-product',
            'sku' => 'OWN-1',
            'price' => 150,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'own-warehouse-test',
            'name' => 'СанБизнесГруп',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'own-warehouse-test',
            'name' => 'Наш оптовый склад',
            'is_active' => true,
            'settings' => [
                'b2b_enabled' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'b2b_category_ids' => [$category->id],
                'price_tax_mode' => 'inclusive',
                'warehouse_label' => 'Наш склад',
            ],
        ]);
        $offer = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'OWN-1',
            'name' => $product->name,
            'price' => 100,
            'price_byn' => 100,
            'stock_quantity' => 10,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Партнёр»',
            'unp' => '190000001',
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'allowed_integration_source_ids' => [$source->id],
        ]);

        return [$user->fresh(), $client, $offer];
    }
}
