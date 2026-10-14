<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailPdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CrmMailAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_a_private_incoming_mail_attachment(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $message = $this->messageWithAttachment('crm-mail-attachments/incoming/1/100/01-order.pdf');
        Storage::disk('local')->put($message->metadata['attachments'][0]['path'], 'PDF-CONTENT');

        $response = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.download', [
            'message' => $message,
            'attachment' => 0,
        ]));

        $response->assertOk();
        $this->assertSame('PDF-CONTENT', $response->streamedContent());
    }

    public function test_customer_cannot_download_crm_mail_attachments(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $message = $this->messageWithAttachment('crm-mail-attachments/incoming/1/100/01-order.pdf');
        Storage::disk('local')->put($message->metadata['attachments'][0]['path'], 'PDF-CONTENT');

        $this->actingAs($customer)->get(route('admin.crm-mail-messages.attachments.download', [
            'message' => $message,
            'attachment' => 0,
        ]))->assertForbidden();
    }

    public function test_customer_cannot_preview_crm_mail_attachments(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $path = 'crm-mail-attachments/incoming/1/101/01-invoice.docx';
        $message = $this->messageWithAttachment($path, 'invoice.docx');
        Storage::disk('local')->put($path, $this->docx('Invoice', []));

        $this->actingAs($customer)->get(route('admin.crm-mail-messages.attachments.preview', [
            'message' => $message,
            'attachment' => 0,
        ]))->assertForbidden();
    }

    public function test_manager_can_preview_docx_inside_crm(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $path = 'crm-mail-attachments/incoming/1/101/01-invoice.docx';
        $message = $this->messageWithAttachment($path, 'счетчик.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        Storage::disk('local')->put($path, $this->docx('Счёт № 125', [['Товар', 'Количество'], ['Счётчик', '3 шт.']]));

        $response = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.preview', [
            'message' => $message,
            'attachment' => 0,
        ]));

        $response->assertOk()
            ->assertSee('Просмотр: счетчик.docx')
            ->assertSee('Счёт № 125')
            ->assertSee('Счётчик')
            ->assertSee('Скачать оригинал');
    }

    public function test_manager_can_preview_pdf_inline_and_still_download_it(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $path = 'crm-mail-attachments/incoming/1/102/01-offer.pdf';
        $message = $this->messageWithAttachment($path, 'предложение.pdf');
        Storage::disk('local')->put($path, 'PDF-CONTENT');

        $preview = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.preview', [
            'message' => $message,
            'attachment' => 0,
        ]));
        $preview->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', (string) $preview->headers->get('Content-Disposition'));

        $download = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.download', [
            'message' => $message,
            'attachment' => 0,
        ]));
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertSame('PDF-CONTENT', $download->streamedContent());
    }

    public function test_manager_can_use_ai_to_read_and_copy_a_scanned_pdf(): void
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
                    'message' => ['content' => 'Счётчик газа СГД-3Т G6, количество 2 штуки. Просим предоставить коммерческое предложение.'],
                ]],
            ]),
        ]);
        $this->bindPdfRendererStub();

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $path = 'crm-mail-attachments/incoming/1/103/01-scan.pdf';
        $message = $this->messageWithAttachment($path, 'SCAN0102.PDF');
        Storage::disk('local')->put($path, "%PDF-1.4\nscanned-image-only");

        $response = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.read-pdf', [
            'message' => $message,
            'attachment' => 0,
        ]));

        $response->assertOk()
            ->assertSee('Скан распознан с помощью DeepSeek')
            ->assertSee('Счётчик газа СГД-3Т G6')
            ->assertSee('Копировать текст');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.deepseek.test/chat/completions'
            && data_get($request->data(), 'model') === 'deepseek-flash'
            && data_get($request->data(), 'messages.0.content.1.type') === 'image_url'
            && str_starts_with((string) data_get($request->data(), 'messages.0.content.1.image_url.url'), 'data:image/jpeg;base64,'));
    }

    public function test_customer_cannot_read_pdf_with_ai(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $path = 'crm-mail-attachments/incoming/1/104/01-scan.pdf';
        $message = $this->messageWithAttachment($path, 'SCAN0102.PDF');
        Storage::disk('local')->put($path, "%PDF-1.4\nscanned-image-only");

        $this->actingAs($customer)->get(route('admin.crm-mail-messages.attachments.read-pdf', [
            'message' => $message,
            'attachment' => 0,
        ]))->assertForbidden();
    }

    private function messageWithAttachment(
        string $path,
        string $name = 'order.pdf',
        string $mimeType = 'application/pdf',
    ): CrmMailMessage {
        $mailbox = CrmMailbox::create([
            'name' => 'Attachment test',
            'email' => 'attachments-test@kotlov.by',
            'username' => 'attachments-test@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', uniqid('attachment-test-', true)),
            'participant_email' => 'sender@example.com',
            'status' => 'new',
        ]);

        return CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'sender@example.com',
            'attachments_count' => 1,
            'metadata' => [
                'attachments' => [[
                    'name' => $name,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size' => 11,
                    'stored' => true,
                    'error' => null,
                ]],
            ],
        ]);
    }

    private function bindPdfRendererStub(): void
    {
        $this->app->bind(CrmMailPdfTextExtractor::class, fn (): CrmMailPdfTextExtractor => new class extends CrmMailPdfTextExtractor
        {
            protected function renderPdfPages(string $path): array
            {
                return [['mime' => 'image/jpeg', 'data' => 'fake-jpeg-page']];
            }
        });
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function docx(string $paragraph, array $rows): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'crm-docx-');
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $table = collect($rows)->map(fn (array $row): string => '<w:tr>'.collect($row)
            ->map(fn (string $cell): string => '<w:tc><w:p><w:r><w:t>'.e($cell).'</w:t></w:r></w:p></w:tc>')
            ->implode('').'</w:tr>')->implode('');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:r><w:t>'.e($paragraph).'</w:t></w:r></w:p><w:tbl>'.$table.'</w:tbl></w:body></w:document>');
        $zip->close();
        $content = file_get_contents($temporary);
        unlink($temporary);

        return (string) $content;
    }
}
