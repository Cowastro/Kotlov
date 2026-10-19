<?php

namespace Tests\Feature;

use App\Filament\Pages\SupplierB2bInquiry;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Market\MarketResearchSupplierInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    public function test_manager_can_create_a_standalone_b2b_supplier_inquiry_from_the_menu_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->updateOrCreate(['email' => 'info@kotlov.by'], [
            'name' => 'KOTLOV',
            'username' => 'info@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'smtp_host' => 'smtp.example.com',
            'is_enabled' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Насосы',
            'slug' => 'standalone-b2b-inquiry',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос для отдельной заявки',
            'slug' => 'standalone-b2b-inquiry-pump',
            'sku' => 'B2B-001',
            'price' => 100,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);

        Livewire::actingAs($manager)
            ->test(SupplierB2bInquiry::class)
            ->assertSee('Последние B2B-заявки')
            ->assertActionExists('create_b2b_inquiry')
            ->callAction('create_b2b_inquiry', [
                'product_id' => $product->id,
                'supplier_id' => null,
                'mailbox_id' => $mailbox->id,
                'recipient' => 'new-supplier@example.ru',
                'participant_name' => 'Новый поставщик РФ',
                'cc' => ['copy@example.ru'],
                'attach_russian_representative' => false,
                'subject' => 'Запрос цены и наличия — '.$product->name,
                'body' => app(MarketResearchSupplierInquiry::class)->bodyForProduct($product),
                'send_now' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $draft = $mailbox->messages()->where('direction', 'draft')->firstOrFail();
        $this->assertSame('supplier_b2b_inquiry', $draft->metadata['source']);
        $this->assertSame($product->id, $draft->metadata['product_id']);
        $this->assertSame('new-supplier@example.ru', $draft->thread->participant_email);
        $this->assertStringContainsString($product->name, $draft->text_body);
        $this->assertStringNotContainsString($product->sku, $draft->text_body);
    }

    public function test_manager_can_request_a_product_that_is_not_in_the_catalog(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->updateOrCreate(['email' => 'info@kotlov.by'], [
            'name' => 'KOTLOV',
            'username' => 'info@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'smtp_host' => 'smtp.example.com',
            'is_enabled' => true,
        ]);
        $productName = 'Редкий контроллер XYZ-500 вне каталога';
        $service = app(MarketResearchSupplierInquiry::class);

        Livewire::actingAs($manager)
            ->test(SupplierB2bInquiry::class)
            ->callAction('create_b2b_inquiry', [
                'product_not_in_catalog' => true,
                'product_id' => null,
                'custom_product_name' => $productName,
                'supplier_id' => null,
                'mailbox_id' => $mailbox->id,
                'recipient' => 'external-supplier@example.test',
                'participant_name' => 'Внешний поставщик',
                'cc' => [],
                'attach_russian_representative' => false,
                'subject' => $service->subjectForName($productName),
                'body' => $service->bodyForName($productName),
                'send_now' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $draft = $mailbox->messages()->where('direction', 'draft')->firstOrFail();
        $this->assertNull($draft->metadata['product_id']);
        $this->assertSame($productName, $draft->metadata['product_name']);
        $this->assertStringContainsString($productName, $draft->subject);
        $this->assertStringContainsString($productName, $draft->text_body);
        $this->assertStringContainsString($productName, $draft->thread->manager_note);
    }

    public function test_uncatalogued_product_name_can_be_empty_for_a_free_form_letter(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->updateOrCreate(['email' => 'info@kotlov.by'], [
            'name' => 'KOTLOV',
            'username' => 'info@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'smtp_host' => 'smtp.example.com',
            'is_enabled' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(SupplierB2bInquiry::class)
            ->callAction('create_b2b_inquiry', [
                'product_not_in_catalog' => true,
                'product_id' => null,
                'custom_product_name' => '',
                'supplier_id' => null,
                'mailbox_id' => $mailbox->id,
                'recipient' => 'free-form@example.test',
                'participant_name' => 'Произвольный получатель',
                'cc' => [],
                'attach_russian_representative' => false,
                'subject' => 'Произвольная тема запроса',
                'body' => 'Полностью произвольный текст письма без товара из базы.',
                'send_now' => false,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $draft = $mailbox->messages()->where('direction', 'draft')->firstOrFail();
        $this->assertNull($draft->metadata['product_id']);
        $this->assertSame('Произвольная тема запроса', $draft->subject);
        $this->assertStringContainsString('Полностью произвольный текст', $draft->text_body);
    }

    public function test_client_cannot_open_the_supplier_b2b_inquiry_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));

        $this->get('/admin/supplier-b2b-inquiries')->assertForbidden();
    }
}
