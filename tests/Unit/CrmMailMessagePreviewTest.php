<?php

namespace Tests\Unit;

use App\Models\CrmMailMessage;
use App\Services\CrmMail\CrmMailMessagePreview;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmMailMessagePreviewTest extends TestCase
{
    #[Test]
    public function it_keeps_readable_email_markup_and_removes_unsafe_or_tracking_content(): void
    {
        $message = new CrmMailMessage([
            'html_body' => '<p>Добрый день!</p><script>alert(1)</script><img src="https://tracker.example/pixel"><a href="https://example.com">Документ</a>',
        ]);

        $html = app(CrmMailMessagePreview::class)->html($message);

        $this->assertStringContainsString('<p>Добрый день!</p>', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    #[Test]
    public function it_preserves_plain_text_line_breaks(): void
    {
        $message = new CrmMailMessage(['text_body' => "Строка 1\nСтрока 2"]);

        $html = app(CrmMailMessagePreview::class)->html($message);

        $this->assertStringContainsString('Строка 1<br', $html);
        $this->assertStringContainsString('Строка 2', $html);
    }
}
