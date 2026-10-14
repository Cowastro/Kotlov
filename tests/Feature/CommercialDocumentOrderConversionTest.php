<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use App\Services\Integrations\OneCOrderQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CommercialDocumentOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_quote_creates_one_linked_b2b_order_with_snapshot_values(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Тест Клиент',
            'legal_name' => 'ООО «Тест Клиент»',
            'unp' => '190000001',
            'source' => 'manual',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Иван Петров',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'crm_counterparty_id' => $counterparty->id,
            'crm_contact_id' => $contact->id,
            'client_name' => 'Иван Петров',
            'client_company' => 'Тест Клиент',
            'client_legal_name' => 'ООО «Тест Клиент»',
            'client_email' => 'buyer@example.com',
            'client_phone' => '+375 29 111-22-33',
            'client_unp' => '190000001',
            'client_address' => 'г. Минск, ул. Тестовая, 1',
            'discount_total' => 100,
            'delivery_price' => 50,
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '5 рабочих дней',
            'notes' => 'Позвонить перед отгрузкой',
        ]);
        $quote->items()->create([
            'name' => 'Насос Wilo',
            'sku' => 'WILO-001',
            'quantity' => 2,
            'unit_price' => 1000,
            'discount_percent' => 10,
            'vat_rate' => 20,
        ]);
        $quote->refresh();

        $workflow = app(CommercialDocumentWorkflow::class);
        $order = $workflow->convertToOrder($quote, $manager);
        $sameOrder = $workflow->convertToOrder($quote->fresh(), $manager);

        $this->assertSame($order->id, $sameOrder->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame($quote->id, $order->commercial_document_id);
        $this->assertSame($counterparty->id, $order->crm_counterparty_id);
        $this->assertSame($contact->id, $order->crm_contact_id);
        $this->assertSame('new', $order->status);
        $this->assertSame('ООО «Тест Клиент»', $order->company_name);
        $this->assertSame('190000001', $order->company_unp);
        $this->assertSame('invoice', $order->payment_type);
        $this->assertSame('transport', $order->delivery_type);
        $this->assertSame('1800.00', $order->subtotal);
        $this->assertSame('100.00', $order->discount);
        $this->assertSame('50.00', $order->delivery_price);
        $this->assertSame('1750.00', $order->total);
        $this->assertSame('900.00', $order->items->first()->price);
        $this->assertSame('b2b', $order->items->first()->pricing_type);
        $this->assertDatabaseHas('order_economic_snapshots', ['order_id' => $order->id, 'kind' => 'placed']);
        Event::assertDispatchedTimes(NewOrderCreated::class, 1);
    }

    public function test_draft_quote_cannot_become_an_order(): void
    {
        $manager = User::factory()->create();
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'draft',
            'client_name' => 'Клиент',
            'client_phone' => '+375291112233',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('согласованного коммерческого предложения');

        app(CommercialDocumentWorkflow::class)->convertToOrder($quote, $manager);
    }

    public function test_manager_can_follow_the_order_from_quote_and_counterparty_cards(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Связанный клиент',
            'source' => 'manual',
        ]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'crm_counterparty_id' => $counterparty->id,
            'client_company' => 'Связанный клиент',
            'client_phone' => '+375291234567',
        ]);
        $quote->items()->create(['name' => 'Котёл', 'quantity' => 1, 'unit_price' => 1000]);
        $order = app(CommercialDocumentWorkflow::class)->convertToOrder($quote->fresh(), $manager);

        Livewire::actingAs($manager)
            ->test(ViewCommercialDocument::class, ['record' => $quote->id])
            ->assertSuccessful()
            ->assertSee($order->number);

        Livewire::actingAs($manager)
            ->test(ViewCrmCounterparty::class, ['record' => $counterparty->id])
            ->assertSuccessful()
            ->assertSee('Заказы клиента')
            ->assertSee($order->number);
    }

    public function test_order_from_accepted_quote_keeps_onec_product_link_and_can_enter_exchange_queue(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Товары КП в 1С', 'slug' => 'commercial-order-onec', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл для обмена',
            'slug' => 'commercial-order-onec-boiler',
            'sku' => 'ONEC-KP-1',
            'price' => 2000,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'Центральная 1С',
            'is_active' => true,
            'settings' => [
                'order_pricing_types' => ['b2b'],
                'require_linked_order_products' => true,
            ],
        ]);
        $integrationProduct = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-commercial-1',
            'external_sku' => 'ONEC-KP-1',
            'name' => $product->name,
            'price' => 1500,
            'price_byn' => 1500,
            'stock_quantity' => 3,
            'match_status' => 'matched',
            'matched_at' => now(),
        ]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'client_company' => 'ООО «Клиент 1С»',
            'client_phone' => '+375291234567',
            'client_unp' => '190000002',
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 2000,
        ]);

        $order = app(CommercialDocumentWorkflow::class)->convertToOrder($quote->fresh(), $manager);
        $orderItem = $order->items()->firstOrFail();

        $this->assertSame($integrationProduct->id, $orderItem->integration_product_id);
        $this->assertSame('b2b', $orderItem->pricing_type);

        $orderItem->forceFill([
            'fulfillment_route' => 'own_stock',
            'fulfillment_confirmed_by' => $manager->id,
            'fulfillment_confirmed_at' => now(),
        ])->save();
        $order->forceFill(['status' => 'confirmed'])->save();
        $delivery = app(OneCOrderQueue::class)->queue($order->fresh(), $manager);

        $this->assertSame(OrderIntegrationDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNotNull($delivery->queued_at);
    }

    public function test_paid_invoice_updates_its_quote_and_existing_order(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'accepted',
            'client_company' => 'ООО «Оплаченный клиент»',
            'client_phone' => '+375291234567',
        ]);
        $quote->items()->create(['name' => 'Печь', 'quantity' => 1, 'unit_price' => 3000]);

        $workflow = app(CommercialDocumentWorkflow::class);
        $order = $workflow->convertToOrder($quote->fresh(), $manager);
        $invoice = $workflow->convertToInvoice($quote->fresh(), $manager);
        $workflow->markPaid($invoice);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_order_created_after_invoice_payment_is_immediately_paid(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'client_company' => 'ООО «Клиент с предоплатой»',
            'client_phone' => '+375291234567',
        ]);
        $quote->items()->create(['name' => 'Печь', 'quantity' => 1, 'unit_price' => 3200]);

        $workflow = app(CommercialDocumentWorkflow::class);
        $invoice = $workflow->convertToInvoice($quote->fresh(), $manager);
        $workflow->markPaid($invoice);
        $order = $workflow->convertToOrder($quote->fresh(), $manager);

        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('paid', $order->payment_status);
    }
}
