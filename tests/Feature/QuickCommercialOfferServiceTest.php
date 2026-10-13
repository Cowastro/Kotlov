<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Services\AiContentEnricher;
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
            'contact_name' => 'Иван Петров',
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

        $this->assertSame('ООО «Молочный завод»', $result['prefill']['client_company']);
        $this->assertSame($product->id, $result['default_product_ids'][0]);
        $this->assertSame(4.0, $result['product_quantities'][$product->id]);
        $this->assertStringContainsString('DeepSeek', $result['analysis_summary']);
        $this->assertSame('777.00', $product->fresh()->price);
    }
}
