<?php

namespace Tests\Feature;

use App\Models\CrmMailMessage;
use Tests\TestCase;

class CrmMailMessagePreviewViewTest extends TestCase
{
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
    }
}
