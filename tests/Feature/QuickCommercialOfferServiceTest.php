<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\AiContentEnricher;
use App\Services\CommercialDocuments\QuickCommercialOfferSaver;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\CrmMail\CrmMailPdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuickCommercialOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_company_and_ranks_product_from_incoming_mail(): void
    {
        $mailbox = CrmMailbox::query()->first() ?? CrmMailbox::query()->create([
            'name' => 'KOTLOV', 'email' => 'info@kotlov.by', 'username' => 'info@kotlov.by',
            'password' => 'secret', 'imap_host' => 'imap.example.com', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'imap_folder' => 'INBOX', 'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'is_enabled' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Насосы', 'slug' => 'quick-offer-pumps', 'is_active' => true,
        ]);
        $matching = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Насос Grundfos Comfort 15-14 BA PM',
            'slug' => 'quick-offer-grundfos', 'sku' => 'PS-008.538', 'price' => 578.28,
            'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
        ]);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'Другой котёл', 'slug' => 'quick-offer-other',
            'sku' => 'OTHER-1', 'price' => 100, 'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id, 'thread_key' => 'quick-offer-test',
            'subject' => 'Fwd: ООО "БК-Милк" — запрос коммерческого предложения',
            'participant_name' => 'Роман Коржанец', 'participant_email' => 'krv@bkmilk.by',
            'status' => 'new', 'priority' => 'normal', 'messages_count' => 1,
            'unread_count' => 1, 'last_message_at' => now(),
        ]);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'incoming', 'from_name' => 'Роман',
            'from_email' => 'krv@bkmilk.by', 'subject' => 'Нужен насос PS-008.538',
            'text_body' => 'Просим предложить насос Grundfos PS-008.538 в количестве 1 шт.',
            'attachments_count' => 0, 'is_read' => false, 'received_at' => now(),
        ]);

        $result = app(QuickCommercialOfferService::class)->prepare($thread);

        $this->assertSame('ООО "БК-Милк"', $result['prefill']['client_company']);
        $this->assertSame('krv@bkmilk.by', $result['prefill']['client_email']);
        $this->assertSame($matching->id, $result['default_product_ids'][0]);
        $this->assertArrayHasKey($matching->id, $result['suggestions']);
    }

    public function test_deepseek_analysis_adds_company_product_and_quantity_without_choosing_a_price(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('providerName')->andReturn('deepseek-chat (api.deepseek.com)');
        $ai->shouldReceive('complete')->once()->andReturn(json_encode([
            'company' => 'ООО «Молочный завод»',
            'legal_name' => 'Общество с ограниченной ответственностью «Молочный завод»',
            'unp' => '190123456',
            'legal_address' => 'г. Минск, ул. Тестовая, 1',
            'contact_name' => 'Иван Петров',
            'contact_position' => 'Начальник отдела закупок',
            'phones' => ['+375 29 100-20-30'],
            'emails' => ['purchase@example.com'],
            'items' => [[
                'query' => 'Циркуляционный насос Grundfos Comfort',
                'sku' => 'UP-15-14',
                'quantity' => 4,
            ]],
            'note' => null,
        ], JSON_UNESCAPED_UNICODE));

        $mailbox = CrmMailbox::query()->first() ?? CrmMailbox::query()->create([
            'name' => 'KOTLOV AI', 'email' => 'ai@kotlov.by', 'username' => 'ai@kotlov.by',
            'password' => 'secret', 'imap_host' => 'imap.example.com', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'imap_folder' => 'INBOX', 'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'is_enabled' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'AI насосы', 'slug' => 'ai-quick-offer-pumps', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Насос Grundfos Comfort',
            'slug' => 'ai-quick-offer-grundfos', 'sku' => 'UP-15-14', 'price' => 777,
            'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id, 'thread_key' => 'ai-quick-offer-test',
            'subject' => 'Нужны насосы', 'participant_name' => 'Получатель',
            'participant_email' => 'buyer@example.com', 'status' => 'new', 'priority' => 'normal',
            'messages_count' => 1, 'unread_count' => 1, 'last_message_at' => now(),
        ]);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'incoming', 'from_email' => 'buyer@example.com',
            'subject' => 'Нужны насосы', 'text_body' => 'Подготовьте предложение по приложенному запросу.',
            'attachments_count' => 0, 'is_read' => false, 'received_at' => now(),
        ]);

        $result = app(QuickCommercialOfferService::class)->prepare($thread, true);

        $this->assertSame('Общество с ограниченной ответственностью «Молочный завод»', $result['prefill']['client_company']);
        $this->assertSame('190123456', $result['prefill']['client_unp']);
        $this->assertSame('+375 29 100-20-30', $result['prefill']['client_phone']);
        $this->assertSame($product->id, $result['default_product_ids'][0]);
        $this->assertSame(4.0, $result['product_quantities'][$product->id]);
        $this->assertStringContainsString('DeepSeek', $result['analysis_summary']);
        $this->assertSame('777.00', $product->fresh()->price);
    }

    public function test_scanned_pdf_text_is_sent_to_ai_and_used_for_product_selection(): void
    {
        Storage::fake('local');
        config([
            'services.ai.api_key' => 'test-deepseek-key',
            'services.ai.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.ai.deepseek_document_model' => 'deepseek-flash',
            'services.ai.pdftotext_binary' => 'missing-pdftotext-for-test',
        ]);
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Запрос: счётчик газа СГД-3Т G6, количество 2 штуки. Просим предоставить цену и срок поставки.'],
                ]],
            ]),
        ]);
        $this->app->bind(CrmMailPdfTextExtractor::class, fn (): CrmMailPdfTextExtractor => new class extends CrmMailPdfTextExtractor
        {
            protected function renderPdfPages(string $path): array
            {
                return [['mime' => 'image/jpeg', 'data' => 'fake-jpeg-page']];
            }
        });

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->andReturn(true);
        $ai->shouldReceive('providerName')->andReturn('deepseek-chat (api.deepseek.com)');
        $ai->shouldReceive('complete')->once()->withArgs(fn (string $prompt): bool => str_contains($prompt, 'СГД-3Т G6'))
            ->andReturn(json_encode([
                'company' => null,
                'legal_name' => null,
                'unp' => null,
                'legal_address' => null,
                'contact_name' => null,
                'contact_position' => null,
                'phones' => [],
                'emails' => [],
                'items' => [['query' => 'Счётчик газа СГД-3Т G6', 'sku' => 'SGD-3T-G6', 'quantity' => 2]],
                'note' => null,
            ], JSON_UNESCAPED_UNICODE));

        $mailbox = CrmMailbox::query()->create([
            'name' => 'PDF AI', 'email' => 'pdf-ai@kotlov.by', 'username' => 'pdf-ai@kotlov.by',
        ]);
        $category = Category::query()->create([
            'name' => 'Газовые счётчики', 'slug' => 'pdf-ai-gas-meters', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Счётчик газа СГД-3Т G6',
            'slug' => 'pdf-ai-sgd-3t-g6', 'sku' => 'SGD-3T-G6', 'price' => 315,
            'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id, 'thread_key' => 'pdf-ai-quick-offer-test',
            'subject' => 'Запрос во вложении', 'participant_email' => 'buyer@example.com',
            'status' => 'new', 'priority' => 'normal', 'messages_count' => 1,
            'unread_count' => 1, 'last_message_at' => now(),
        ]);
        $path = 'crm-mail-attachments/incoming/1/200/01-SCAN0102.PDF';
        $pdf = "%PDF-1.4\nscanned-image-only-unique";
        Storage::disk('local')->put($path, $pdf);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'incoming', 'from_email' => 'buyer@example.com',
            'subject' => 'Запрос во вложении', 'text_body' => 'Позиции указаны в приложенном PDF.',
            'attachments_count' => 1, 'is_read' => false, 'received_at' => now(),
            'metadata' => ['attachments' => [[
                'name' => 'SCAN0102.PDF', 'path' => $path, 'mime_type' => 'application/pdf',
                'size' => strlen($pdf), 'stored' => true, 'error' => null,
            ]]],
        ]);

        $result = app(QuickCommercialOfferService::class)->prepare($thread, true);

        $this->assertContains($product->id, $result['default_product_ids']);
        $this->assertSame(2.0, $result['product_quantities'][$product->id]);
        $this->assertStringContainsString('SCAN0102.PDF (DeepSeek OCR)', $result['analysis_summary']);
    }

    public function test_manager_text_is_matched_with_quantity_without_ai(): void
    {
        $category = Category::query()->create([
            'name' => 'Подбор из формы КП', 'slug' => 'commercial-copilot-products', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос Grundfos Comfort 15-14 BA PM',
            'slug' => 'commercial-copilot-grundfos-comfort',
            'sku' => 'PS-008.538',
            'price' => 578.28,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $numericSkuProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Радиатор стальной тестовый',
            'slug' => 'commercial-copilot-numeric-sku',
            'sku' => '123456789',
            'price' => 300,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $result = app(QuickCommercialOfferService::class)
            ->prepareText('Насос Grundfos Comfort 15-14 BA PM PS-008.538 — 2 шт.', false);
        $numericResult = app(QuickCommercialOfferService::class)
            ->prepareText('123456789 Радиатор стальной тестовый — 3 шт.', false);

        $this->assertArrayHasKey($product->id, $result['suggestions']);
        $this->assertContains($product->id, $result['default_product_ids']);
        $this->assertSame(2.0, $result['product_quantities'][$product->id]);
        $this->assertStringContainsString('быстрый подбор', $result['analysis_summary']);
        $this->assertSame('578.28', $product->fresh()->price);
        $this->assertArrayHasKey($numericSkuProduct->id, $numericResult['suggestions']);
        $this->assertSame(3.0, $numericResult['product_quantities'][$numericSkuProduct->id]);
    }

    public function test_manual_search_finds_similar_catalog_products_regardless_of_word_order_and_supplier_article(): void
    {
        $category = Category::query()->create([
            'name' => 'Ручной подбор', 'slug' => 'manual-quick-offer-search', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос циркуляционный Grundfos Comfort 15-14',
            'slug' => 'manual-quick-offer-grundfos',
            'sku' => 'CAT-100',
            'price' => 580,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'manual-search-supplier',
            'name' => 'Поставщик насосов',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_name' => $product->name,
            'supplier_article' => 'SUP-7788',
            'price' => 400,
            'price_byn' => 400,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'in_stock' => true,
            'stock_quantity' => 5,
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
        ]);

        $service = app(QuickCommercialOfferService::class);
        $byWords = $service->searchProducts('Comfort насос Grundfos');
        $bySupplierArticle = $service->searchProducts('SUP-7788');

        $this->assertArrayHasKey($product->id, $byWords);
        $this->assertArrayHasKey($product->id, $bySupplierArticle);
        $this->assertStringContainsString('Поставщик насосов', $byWords[$product->id]);
        $this->assertStringContainsString('В наличии', $byWords[$product->id]);
    }

    public function test_stove_selection_requests_missing_parameters_instead_of_showing_random_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Печи для безопасного подбора', 'slug' => 'safe-stove-selection', 'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Твердотопливный котёл другого производителя 20 кВт',
            'slug' => 'unrelated-solid-fuel-boiler',
            'sku' => 'OTHER-20',
            'price' => 2000,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $service = app(QuickCommercialOfferService::class);
        $broad = $service->manualSelection('Нужна печь');
        $missingModel = $service->manualSelection('Tatra KT95-N твердотопливный котёл 6 мм');

        $this->assertFalse($broad['ready']);
        $this->assertSame([], $broad['suggestions']);
        $this->assertStringContainsString('объём помещения', $broad['message']);
        $this->assertTrue($missingModel['ready']);
        $this->assertSame([], $missingModel['suggestions']);
    }

    public function test_manual_market_query_can_become_an_inactive_draft_when_catalog_has_no_match(): void
    {
        $items = CrmMailThreadResource::quickOfferMissingItems([
            'unmatched_items' => [],
        ], [
            'manual_product_query' => 'Промышленный насос неизвестной модели — 2 шт.',
            'manual_suggestion_ids' => [],
            'product_ids' => [],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('Промышленный насос неизвестной модели —', $items[0]['query']);
        $this->assertSame(2.0, $items[0]['quantity']);
    }

    public function test_it_saves_recognized_client_contacts_and_creates_inactive_product_draft(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'CRM', 'email' => 'crm@kotlov.by', 'username' => 'crm@kotlov.by',
            'password' => 'secret', 'imap_host' => 'imap.example.com', 'imap_port' => 993,
            'imap_encryption' => 'ssl', 'imap_folder' => 'INBOX', 'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'is_enabled' => true,
        ]);
        $category = Category::query()->create(['name' => 'Черновики', 'slug' => 'draft-products', 'is_active' => true]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id, 'thread_key' => 'save-recognized-client', 'subject' => 'Запрос',
            'participant_name' => 'Анна Иванова', 'participant_email' => 'anna@example.by',
            'status' => 'new', 'priority' => 'normal', 'messages_count' => 1, 'unread_count' => 1, 'last_message_at' => now(),
        ]);
        $analysis = [
            'company' => 'ООО «Тест»', 'legal_name' => 'ООО «Тест»', 'unp' => '190123456',
            'legal_address' => 'Минск', 'contact_name' => 'Анна Иванова', 'contact_position' => 'Закупки',
            'phones' => ['+375291112233'], 'emails' => ['anna@example.by'],
        ];

        $saver = app(QuickCommercialOfferSaver::class);
        $company = $saver->saveClient($thread, [
            'client_company' => 'ООО «Тест»', 'client_name' => 'Анна Иванова',
            'client_email' => 'anna@example.by', 'client_unp' => '190123456', 'client_address' => 'Минск',
        ], $analysis);
        $products = $saver->createDraftProducts([[
            'query' => 'Неизвестный промышленный насос', 'sku' => 'NEW-001', 'quantity' => 2,
        ]], $category->id);

        $this->assertSame('190123456', $company->unp);
        $this->assertDatabaseHas('crm_contacts', ['counterparty_id' => $company->id, 'full_name' => 'Анна Иванова']);
        $this->assertDatabaseHas('crm_contact_points', ['counterparty_id' => $company->id, 'type' => 'email', 'normalized_value' => 'anna@example.by']);
        $product = Product::query()->findOrFail(array_key_first($products));
        $this->assertFalse($product->is_active);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->availability_status);
        $this->assertSame(2.0, $products[$product->id]);
    }
}
