<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Market\MarketResearchSupplierInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketResearchSupplierInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_supplier_mail_draft_focused_on_partner_b2b_terms(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->firstOrCreate(['email' => 'info@kotlov.by'], [
            'name' => 'KOTLOV.BY',
            'username' => 'info@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'smtp_host' => 'smtp.example.com',
            'is_enabled' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'b2b-test',
            'name' => 'Поставщик B2B',
            'contact' => 'sales@supplier.test',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Котлы',
            'slug' => 'b2b-inquiry-boilers',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл Practic 72',
            'slug' => 'b2b-inquiry-practic-72',
            'sku' => 'PS-010.366',
            'price' => 8487,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        $report = MarketResearchReport::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'b2b-inquiry-test',
            'review_reason' => 'Запрос партнёрской цены.',
            'decision' => 'price_unchanged',
            'confidence' => 'medium',
            'price_before' => 8487,
            'price_after' => 8487,
            'observations_snapshot' => [[
                'seller' => 'Розничный рынок',
                'price_byn' => 6710,
                'price' => 6710,
                'currency' => 'BYN',
                'url' => 'https://example.test/product',
            ]],
            'applied_at' => now(),
        ]);

        $service = app(MarketResearchSupplierInquiry::class);
        $body = $service->body($report);
        $thread = $service->createDraft(
            $report,
            $supplier,
            $mailbox,
            $manager,
            'sales@supplier.test',
            ['copy@supplier.test'],
            'Произвольная компания',
            $service->subject($report),
            $body,
            true,
        );

        $draft = $thread->messages()->where('direction', 'draft')->firstOrFail();

        $this->assertStringContainsString('актуальную партнёрскую B2B-цену и наличие на складе', $draft->text_body);
        $this->assertStringContainsString('Котёл Practic 72', $draft->text_body);
        $this->assertStringNotContainsString('PS-010.366', $draft->text_body);
        $this->assertStringNotContainsString('BYN', $draft->text_body);
        $this->assertStringNotContainsString('минимальн', mb_strtolower($draft->text_body));
        $this->assertSame('market_research_b2b_inquiry', $draft->metadata['source']);
        $this->assertSame($report->id, $draft->metadata['market_research_report_id']);
        $this->assertSame('sales@supplier.test', $thread->participant_email);
        $this->assertSame('Произвольная компания', $thread->participant_name);
        $this->assertSame('copy@supplier.test', $draft->cc[0]['email']);
        $this->assertSame(1, $draft->attachments_count);
        $this->assertSame('crm-mail-attachments/system/mpg-requisites.pdf', $draft->metadata['attachments'][0]['path']);
        $this->assertSame('Реквизиты ООО МПГ.pdf', $draft->metadata['attachments'][0]['name']);
        $this->assertStringContainsString('ИНН 6732146305', $draft->text_body);
        Storage::disk('local')->assertExists('crm-mail-attachments/system/mpg-requisites.pdf');

        $manualThread = $service->createDraft(
            $report,
            null,
            $mailbox,
            $manager,
            'other@arbitrary.test',
            [],
            'Компания вне справочника',
            $service->subject($report),
            $body,
        );

        $this->assertSame('other@arbitrary.test', $manualThread->participant_email);
        $this->assertSame('Компания вне справочника', $manualThread->participant_name);
        $this->assertNull($manualThread->messages()->latest('id')->firstOrFail()->metadata['supplier_id']);
    }
}
