<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
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
}
