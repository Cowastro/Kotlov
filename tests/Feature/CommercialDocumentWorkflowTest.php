<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_keeps_product_snapshot_and_converts_to_separate_invoice(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Котлы для теста',
            'slug' => 'test-boilers-commercial-documents',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Газовый котёл KOTLOV TEST',
            'slug' => 'gas-boiler-kotlov-test',
            'sku' => 'TEST-001',
            'price' => 2500,
            'currency' => 'BYN',
            'unit' => 'шт.',
        ]);
        $quote = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_company' => 'ООО «Тест»',
            'client_email' => 'client@example.com',
            'payment_terms' => '50% предоплата, 50% перед отгрузкой',
            'delivery_terms' => '5 рабочих дней после предоплаты',
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 2500,
            'discount_percent' => 10,
            'vat_rate' => 20,
        ]);
        $quote->refresh();

        $product->update(['name' => 'Новое название', 'price' => 3000]);
        $invoice = app(CommercialDocumentWorkflow::class)->convertToInvoice($quote, $user);

        $this->assertSame('quote', $quote->type);
        $this->assertSame('invoice', $invoice->type);
        $this->assertSame($quote->id, $invoice->source_document_id);
        $this->assertSame('Газовый котёл KOTLOV TEST', $invoice->items->first()->name);
        $this->assertSame('4500.00', $invoice->total);
        $this->assertSame('50% предоплата, 50% перед отгрузкой', $invoice->payment_terms);
        $this->assertSame('5 рабочих дней после предоплаты', $invoice->delivery_terms);
        $this->assertNotSame($quote->number, $invoice->number);
    }

    public function test_pdf_has_clean_wordmark_and_cyrillic_document_template(): void
    {
        $template = file_get_contents(resource_path('views/pdf/commercial-document.blade.php'));

        $this->assertStringContainsString('<img src="{{ $logo }}" alt="KOTLOV.BY">', $template);
        $this->assertStringNotContainsString('background: #171717', $template);
        $this->assertFileExists(public_path('img/logo-black.png'));

        $document = CommercialDocument::query()->create([
            'type' => 'invoice',
            'client_name' => 'Иван Иванов',
            'client_company' => 'ООО «Покупатель»',
            'client_email' => 'buyer@example.com',
        ]);
        $document->items()->create([
            'name' => 'Тепловой насос KOTLOV GE',
            'sku' => 'HP-001',
            'quantity' => 1,
            'unit_price' => 12345.67,
            'vat_rate' => 20,
        ]);
        $document->refresh();

        $pdf = app(CommercialDocumentPdf::class)->render($document);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
    }

    public function test_pdf_uses_current_website_contacts_even_for_an_older_document_snapshot(): void
    {
        config()->set('legal.commercial_documents.phones', [
            '+375 (29) 354-40-41',
            '+375 (33) 377-70-93',
        ]);

        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'seller_snapshot' => array_replace(config('legal.commercial_documents'), [
                'phones' => ['+375 29 680-84-14', '+375 17 231-58-14'],
            ]),
        ]);

        $seller = app(CommercialDocumentPdf::class)->sellerDetails($document);

        $this->assertSame([
            '+375 (29) 354-40-41',
            '+375 (33) 377-70-93',
        ], $seller['phones']);
        $this->assertSame('ООО «СанБизнесГруп»', $seller['short_name']);
    }

    public function test_mail_draft_contains_generated_pdf_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'documents-test@kotlov.by',
            'username' => 'documents-test@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_enabled' => true,
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_name' => 'Анна',
            'client_email' => 'anna@example.com',
        ]);
        $document->items()->create([
            'name' => 'Котёл',
            'quantity' => 1,
            'unit_price' => 1000,
            'vat_rate' => 20,
        ]);

        $thread = app(CommercialDocumentWorkflow::class)->createMailDraft($document, $user);
        $message = $thread->messages()->firstOrFail();
        $attachment = $message->metadata['attachments'][0];

        $this->assertSame('draft', $thread->status);
        $this->assertSame('anna@example.com', $thread->participant_email);
        $this->assertStringContainsString($document->number, $thread->subject);
        $this->assertSame(1, $message->attachments_count);
        Storage::disk('local')->assertExists($attachment['path']);
        $this->assertSame($thread->id, $document->fresh()->crm_mail_thread_id);
    }

    public function test_manager_can_create_and_update_commercial_documents(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($user);

        $this->assertTrue(CommercialDocumentResource::canViewAny());
        $this->assertTrue(CommercialDocumentResource::canCreate());
        Livewire::actingAs($user)->test(ListCommercialDocuments::class)->assertSuccessful();
        Livewire::actingAs($user)
            ->test(CreateCommercialDocument::class)
            ->assertSuccessful()
            ->assertSeeText('ИИ-помощник подбора')
            ->assertSeeText('Подобрать товары')
            ->assertSeeText('Добавить выбранные в КП')
            ->assertSeeText('Сравнить рынок')
            ->assertSeeText('Условия оплаты')
            ->assertSeeText('Срок поставки');
    }

    public function test_manager_can_match_and_add_products_from_copilot_text(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Помощник КП', 'slug' => 'commercial-document-copilot-ui', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос Grundfos Comfort 15-14 BA PM',
            'slug' => 'commercial-document-copilot-ui-product',
            'sku' => 'PS-008.538',
            'price' => 578.28,
            'currency' => 'BYN',
            'unit' => 'шт.',
            'is_active' => true,
            'is_archived' => false,
        ]);

        Livewire::actingAs($user)
            ->test(CreateCommercialDocument::class)
            ->fillForm(['copilot_request' => 'Насос Grundfos Comfort PS-008.538 — 2 шт.'])
            ->callFormComponentAction('copilot_actions', 'analyze_copilot_request')
            ->assertFormSet(function (array $state) use ($product): bool {
                return in_array($product->id, $state['copilot_product_ids'] ?? [], true)
                    && (float) ($state['copilot_quantities'][$product->id] ?? 0) === 2.0;
            })
            ->callFormComponentAction('copilot_actions', 'add_copilot_products')
            ->assertFormSet(function (array $state) use ($product): bool {
                return collect($state['items'] ?? [])->contains(
                    fn (array $item): bool => (int) ($item['product_id'] ?? 0) === $product->id
                        && (float) ($item['quantity'] ?? 0) === 2.0
                        && (float) ($item['unit_price'] ?? 0) === 578.28,
                );
            });
    }
}
