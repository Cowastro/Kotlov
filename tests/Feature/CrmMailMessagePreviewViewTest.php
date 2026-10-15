<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailMessage;
use App\Services\CrmMail\CrmMailMessagePreview;
use Tests\TestCase;

class CrmMailMessagePreviewViewTest extends TestCase
{
    public function test_mobile_original_preview_uses_small_gutters_and_keeps_the_iframe_sandboxed(): void
    {
        $message = new CrmMailMessage([
            'direction' => 'outgoing', 'subject' => 'В резерве', 'text_body' => 'В резерве',
            'html_body' => '<p>В резерве</p>',
        ]);
        $html = view('filament.crm-mail.message-preview', [
            'message' => $message, 'safeHtml' => 'В резерве', 'hasVisibleBody' => true,
            'originalHtml' => app(CrmMailMessagePreview::class)->originalHtml($message),
        ])->render();

        $this->assertStringContainsString('.crm-mail-original { min-width: 0; padding: 10px 6px; }', $html);
        $this->assertMatchesRegularExpression('/<iframe\b[^>]*\bsandbox\s/s', $html);
        $this->assertStringNotContainsString('allow-scripts', $html);
        $this->assertStringNotContainsString('allow-same-origin', $html);
        $page = new ViewCrmMailThread;
        $this->assertSame('crm-mail-thread-page', $page->getExtraBodyAttributes()['class']);
    }

    public function test_attachment_only_message_does_not_render_an_empty_body_or_original_frame(): void
    {
        $message = new CrmMailMessage([
            'direction' => 'incoming',
            'from_name' => 'Поставщик',
            'from_email' => 'supplier@example.com',
            'to' => [['email' => 'info@kotlov.by']],
            'subject' => 'Документы',
            'html_body' => '<html><body>&nbsp;</body></html>',
            'metadata' => [
                'attachments' => [[
                    'name' => 'прайс.xlsx',
                    'size' => 1024,
                    'error' => 'save_failed',
                ]],
            ],
        ]);

        $html = view('filament.crm-mail.message-preview', [
            'message' => $message,
            'safeHtml' => '<p class="crm-mail-empty">Текст письма отсутствует.</p>',
            'hasVisibleBody' => false,
            'originalHtml' => $message->html_body,
            'isLatestMessage' => false,
        ])->render();

        $this->assertStringContainsString('прайс.xlsx', $html);
        $this->assertStringNotContainsString('Текст письма отсутствует.', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]+crm-mail-original-toggle/s', $html);
        $this->assertStringNotContainsString('<div class="crm-mail-body"', $html);
        $this->assertStringContainsString('wire:click="openReply(', $html);
    }
}
