<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Category;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_mail_product_is_prefilled_in_new_commercial_offer(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Котлы для быстрого КП',
            'slug' => 'quick-commercial-offer-prefill',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Твердотопливный котёл Татра КТ30-НЛ1',
            'slug' => 'tatra-kt30-prefill',
            'sku' => 'PS-011.367',
            'price' => 3154,
            'currency' => 'BYN',
            'unit' => 'шт.',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->actingAs($user);
        session()->put('commercial_document_prefill', [
            'type' => 'quote',
            'client_company' => 'ОАО «Колпень-Агро»',
            'client_default_discount_percent' => 10,
        ]);
        session()->put('commercial_document_product_ids', [$product->id]);
        session()->put('commercial_document_product_quantities', [$product->id => 1]);

        $component = Livewire::test(CreateCommercialDocument::class)->assertSuccessful();
        $items = collect($component->get('data.items'));

        $this->assertCount(1, $items);
        $this->assertSame($product->id, (int) $items->first()['product_id']);
        $this->assertSame($product->name, $items->first()['name']);
        $this->assertSame(3154.0, (float) $items->first()['unit_price']);
        $this->assertSame(10.0, (float) $items->first()['discount_percent']);
        $this->assertSame(2838.6, (float) $items->first()['line_total']);

        $component->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('commercial_document_items', [
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3154,
            'discount_percent' => 10,
            'line_total' => 2838.6,
        ]);
        $this->assertDatabaseHas('crm_counterparties', [
            'name' => 'ОАО «Колпень-Агро»',
            'classification' => 'b2b_prospect',
            'status' => 'lead',
        ]);
        $counterparty = CrmCounterparty::query()->where('name', 'ОАО «Колпень-Агро»')->firstOrFail();
        $this->assertDatabaseHas('commercial_documents', [
            'client_company' => 'ОАО «Колпень-Агро»',
            'crm_counterparty_id' => $counterparty->id,
        ]);
    }

    public function test_manager_can_start_a_linked_commercial_offer_from_product_preview(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Газовые котлы из карточки товара',
            'slug' => 'product-preview-commercial-offer',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Buderus Logamax Plus GB162-100',
            'slug' => 'buderus-logamax-plus-gb162-100-test',
            'sku' => 'PS-008.082',
            'price' => 13496,
            'currency' => 'BYN',
            'unit' => 'шт.',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_prospect',
            'business_unit' => 'wholesale',
            'status' => 'lead',
            'name' => 'ОАО «ТестСтрой»',
            'public_name' => 'ТестСтрой',
            'legal_name' => 'ОАО «ТестСтрой»',
            'unp' => '100000001',
            'source' => 'email',
            'commercial_discount_percent' => 5,
        ]);
        $contact = $counterparty->contacts()->create([
            'full_name' => 'Иван Петров',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $contact->contactPoints()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'phone',
            'value' => '+375 29 111-22-33',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $thread = CrmMailbox::query()->firstOrFail()->threads()->create([
            'crm_counterparty_id' => $counterparty->id,
            'crm_contact_id' => $contact->id,
            'thread_key' => hash('sha256', 'product-preview-commercial-offer'),
            'subject' => 'Запрос коммерческого предложения на котёл',
            'participant_name' => 'Иван Петров',
            'participant_email' => 'ivan@example.com',
            'status' => 'in_progress',
            'sales_stage' => 'qualification',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionVisible('create_commercial_document')
            ->callAction('create_commercial_document', [
                'quantity' => 2,
                'crm_mail_thread_id' => $thread->id,
                'create_mail_draft' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame([$product->id], session('commercial_document_product_ids'));
        $this->assertSame(2.0, session('commercial_document_product_quantities')[$product->id]);
        $this->assertTrue(session('commercial_document_create_mail_draft'));
        $this->assertSame($thread->id, session('commercial_document_prefill.crm_mail_thread_id'));
        $this->assertSame($counterparty->id, session('commercial_document_prefill.crm_counterparty_id'));
        $this->assertSame('ivan@example.com', session('commercial_document_prefill.client_email'));

        $component = Livewire::test(CreateCommercialDocument::class)->assertSuccessful();

        $this->assertTrue($component->get('createMailDraft'));
        $this->assertSame($thread->id, (int) $component->get('data.crm_mail_thread_id'));
        $this->assertSame('ТестСтрой', $component->get('data.client_company'));
        $this->assertSame('Иван Петров', $component->get('data.client_name'));
        $this->assertSame('ivan@example.com', $component->get('data.client_email'));
        $this->assertSame(2.0, (float) collect($component->get('data.items'))->first()['quantity']);
    }
}
