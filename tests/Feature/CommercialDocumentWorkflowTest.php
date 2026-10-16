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
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_printed_only_in_terms_without_an_invented_pending_charge(): void
    {
        $document = CommercialDocument::query()->create([
            'type' => 'quote', 'client_name' => 'Проверка поставки', 'currency' => 'BYN',
            'delivery_to_client' => true, 'delivery_terms' => 'Доставка до склада покупателя.',
            'delivery_price' => 0, 'subtotal' => 25650, 'total' => 25650,
        ]);

        foreach ([0, 150] as $charge) {
            $document->fill(['delivery_price' => $charge, 'total' => 25650 + $charge]);
            $pdf = view('pdf.commercial-document', [
                'document' => $document, 'seller' => config('legal.commercial_documents'),
                'logo' => null, 'amountWords' => 'Тестовая сумма', 'portalUrl' => null,
            ])->render();
            $portal = view('commercial-portal.show', [
                'document' => $document, 'preview' => true, 'errors' => new ViewErrorBag,
            ])->render();
            foreach ([$pdf, $portal] as $html) {
                $this->assertSame(1, substr_count($html, 'Доставка до склада покупателя.'));
                $this->assertStringNotContainsString('Доставка до клиента.', $html);
                $this->assertStringNotContainsString('по согласованию', $html);
                $this->assertSame($charge > 0 ? 1 : 0, substr_count($html, '150,00 BYN (включена в итог)'));
            }
            preg_match('/<table class="summary">(.*?)<\/table>/s', $pdf, $summary);
            $this->assertStringNotContainsString('Доставка', $summary[1]);
        }

        $document->fill(['delivery_terms' => '5 рабочих дней', 'delivery_price' => 0]);
        $terms = view('commercial-documents.delivery-terms', ['document' => $document])->render();
        $this->assertStringContainsString('Доставка до клиента. 5 рабочих дней', $terms);
        $document->delivery_to_client = false;
        $this->assertStringNotContainsString('Доставка до клиента.', view('commercial-documents.delivery-terms', ['document' => $document])->render());
    }

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
        $this->assertStringContainsString('Открыть предложение онлайн:', $message->text_body);
        $this->assertSame(1, $document->portalLinks()->count());
        $this->assertSame(1, $message->attachments_count);
        Storage::disk('local')->assertExists($attachment['path']);
        $this->assertSame($thread->id, $document->fresh()->crm_mail_thread_id);
    }

    public function test_existing_mail_draft_is_refreshed_with_recalculated_total(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'documents-refresh@kotlov.by',
            'username' => 'documents-refresh@kotlov.by',
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
            'client_name' => 'Pavel',
            'client_email' => 'pavel@example.com',
            'manager_id' => $user->id,
        ]);

        $thread = app(CommercialDocumentWorkflow::class)->createMailDraft($document, $user);
        $this->assertStringContainsString('Сумма: 0,00 BYN.', $thread->messages->last()->text_body);

        $document->items()->create([
            'name' => 'Гидравлический разделитель',
            'quantity' => 1,
            'unit_price' => 379.18,
            'vat_rate' => 20,
        ]);

        $thread->forceFill(['status' => 'in_progress'])->save();
        $this->artisan('commercial-documents:refresh-draft', ['document' => $document->id])
            ->expectsOutputToContain('draft_refreshed=1')
            ->assertSuccessful();
        $refreshed = $thread->fresh('messages');

        $this->assertSame($thread->id, $refreshed->id);
        $this->assertSame(1, $refreshed->messages()->where('direction', 'draft')->count());
        $this->assertStringContainsString('Сумма: 379,18 BYN.', $refreshed->messages->last()->text_body);
        $this->assertSame('379.18', $document->fresh()->total);
    }

    public function test_mail_draft_for_an_existing_deal_stays_in_the_original_thread(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'deal-thread@kotlov.by',
            'username' => 'deal-thread@kotlov.by',
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
        $thread = $mailbox->threads()->create([
            'thread_key' => hash('sha256', 'original-deal-thread'),
            'subject' => 'Запрос на поставку',
            'participant_name' => 'Анна',
            'participant_email' => 'anna@example.com',
            'status' => 'in_progress',
            'sales_stage' => 'qualification',
            'priority' => 'normal',
            'messages_count' => 1,
            'unread_count' => 0,
            'last_message_at' => now()->subHour(),
        ]);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'request@example.com',
            'direction' => 'incoming',
            'from_name' => 'Анна',
            'from_email' => 'anna@example.com',
            'to' => [['name' => 'KOTLOV', 'email' => $mailbox->email]],
            'subject' => 'Запрос на поставку',
            'text_body' => 'Просим подготовить предложение.',
            'is_read' => true,
            'received_at' => now()->subHour(),
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'crm_mail_thread_id' => $thread->id,
            'client_name' => 'Анна',
            'client_email' => 'anna@example.com',
        ]);
        $document->items()->create([
            'name' => 'Котёл',
            'quantity' => 1,
            'unit_price' => 1000,
            'vat_rate' => 20,
        ]);

        $draftThread = app(CommercialDocumentWorkflow::class)->createMailDraft($document, $user);

        $this->assertSame($thread->id, $draftThread->id);
        $this->assertSame($thread->id, $document->fresh()->crm_mail_thread_id);
        $this->assertSame(2, $draftThread->messages_count);
        $this->assertSame(['incoming', 'draft'], $draftThread->messages->pluck('direction')->all());
        $this->assertSame('qualification', $draftThread->sales_stage);
        $this->assertStringContainsString($document->number, $draftThread->subject);
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
            ->assertSeeText('Подбор товаров из базы')
            ->assertSeeText('Найти в базе')
            ->assertSeeText('Добавить выбранные в КП')
            ->assertSeeText('Рынок / исследование')
            ->assertSeeText('Типовая оплата')
            ->assertSeeText('Типовая поставка')
            ->assertSeeText('Условия оплаты')
            ->assertSeeText('Срок поставки');
    }

    public function test_commercial_document_has_popular_payment_and_delivery_presets(): void
    {
        $this->assertArrayHasKey(
            '100% предоплата.',
            CommercialDocumentResource::paymentTermsPresets(),
        );
        $this->assertArrayHasKey(
            '50% предоплата, 50% перед отгрузкой.',
            CommercialDocumentResource::paymentTermsPresets(),
        );
        $this->assertArrayHasKey(
            '3–5 рабочих дней после оплаты.',
            CommercialDocumentResource::deliveryTermsPresets(),
        );
        $this->assertArrayHasKey(
            'Самовывоз со склада.',
            CommercialDocumentResource::deliveryTermsPresets(),
        );
        $this->assertArrayHasKey(
            'Доставка до склада покупателя.',
            CommercialDocumentResource::deliveryTermsPresets(),
        );
    }

    public function test_commercial_document_requires_payment_and_delivery_terms(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        Livewire::actingAs($user)
            ->test(CreateCommercialDocument::class)
            ->fillForm([
                'payment_terms' => '',
                'delivery_terms' => '',
            ])
            ->call('create')
            ->assertHasErrors([
                'data.payment_terms' => 'required',
                'data.delivery_terms' => 'required',
            ]);
    }

    public function test_manager_can_find_and_add_product_from_database(): void
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
            ->fillForm(['catalog_search' => 'PS-008.538'])
            ->callFormComponentAction('catalog_actions', 'search_catalog')
            ->assertFormSet(function (array $state) use ($product): bool {
                return array_key_exists($product->id, $state['catalog_result_labels'] ?? [])
                    && ($state['catalog_product_ids'] ?? []) === [];
            })
            ->fillForm(['catalog_product_ids' => [$product->id]])
            ->callFormComponentAction('catalog_actions', 'add_catalog_products')
            ->assertFormSet(function (array $state) use ($product): bool {
                return collect($state['items'] ?? [])->contains(
                    fn (array $item): bool => (int) ($item['product_id'] ?? 0) === $product->id
                        && (float) ($item['quantity'] ?? 0) === 1.0
                        && (float) ($item['unit_price'] ?? 0) === 578.28,
                );
            });
    }
}
