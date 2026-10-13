<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Services\AiContentEnricher;
use App\Services\CommercialDocuments\QuickCommercialOfferSaver;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
