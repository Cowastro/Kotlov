<?php

namespace Tests\Unit;

use App\Models\CrmMailMessage;
use App\Services\CrmMail\CrmMailMessagePreview;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmMailMessagePreviewTest extends TestCase
{
    public function test_mobile_adaptation_only_changes_the_preview_not_the_saved_email(): void
    {
        $original = '<!doctype html><html><head><style>.email-pad{padding:32px}</style></head><body><table class="email-card"><tr><td class="email-pad">В резерве</td></tr></table></body></html>';
        $message = new CrmMailMessage(['html_body' => $original]);

        $html = app(CrmMailMessagePreview::class)->originalHtml($message);

        $this->assertSame($original, $message->html_body);
        $this->assertStringContainsString('<meta name="viewport"', $html);
        $this->assertStringContainsString('id="crm-mail-mobile-preview"', $html);
        $this->assertStringContainsString('@media (max-width: 700px)', $html);
        $this->assertStringContainsString('padding-left: 14px !important', $html);
        $this->assertStringContainsString('<td class="email-pad">В резерве</td>', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'id="crm-mail-mobile-preview"'));
    }

    public function test_html_fragments_also_receive_a_responsive_preview_document(): void
    {
        $message = new CrmMailMessage(['html_body' => '<p>Короткий ответ</p>']);
        $html = app(CrmMailMessagePreview::class)->originalHtml($message);

        $this->assertStringStartsWith('<!doctype html><html><head>', $html);
        $this->assertStringContainsString('<body><p>Короткий ответ</p></body>', $html);
        $this->assertStringContainsString('.signature-column', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_it_resolves_the_embedded_brand_logo_for_the_crm_preview(): void
    {
        $message = new CrmMailMessage([
            'html_body' => '<header><img src="cid:kotlov-logo" alt="KOTLOV.BY"></header>',
        ]);

        $html = app(CrmMailMessagePreview::class)->originalHtml($message);

        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('cid:kotlov-logo', $html);
        $this->assertStringContainsString('alt="KOTLOV.BY"', $html);
    }

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

    #[Test]
    public function it_builds_a_lightweight_preview_without_rendering_the_full_html_tree(): void
    {
        $message = new CrmMailMessage([
            'html_body' => '<style>table{width:100%}</style><table><tr><td>Заказ поставщику</td></tr></table><img src="https://example.com/logo.png">',
        ]);

        $html = app(CrmMailMessagePreview::class)->compactHtml($message);

        $this->assertStringContainsString('Заказ поставщику', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('table{width:100%}', $html);
    }

    #[Test]
    public function it_limits_the_fast_preview_of_a_large_message(): void
    {
        $message = new CrmMailMessage(['text_body' => str_repeat('А', 200)]);

        $html = app(CrmMailMessagePreview::class)->compactHtml($message, 50);

        $this->assertStringContainsString('Письмо сокращено', $html);
        $this->assertLessThan(400, mb_strlen($html));
    }

    #[Test]
    public function it_recognizes_html_without_visible_text_as_empty(): void
    {
        $preview = app(CrmMailMessagePreview::class);
        $message = new CrmMailMessage([
            'html_body' => '<html><head><style>body { margin: 0; }</style></head><body>&nbsp;&#8203;</body></html>',
        ]);

        $this->assertFalse($preview->hasVisibleContent($message));

        $message->html_body = '<p>Во вложении документы</p>';

        $this->assertTrue($preview->hasVisibleContent($message));
    }
}
