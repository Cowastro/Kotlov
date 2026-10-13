<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class CrmMailMessagePreview
{
    public function compactHtml(CrmMailMessage $message, int $maxLength = 12000): string
    {
        $text = trim((string) $message->text_body);

        if ($text === '' && filled($message->html_body)) {
            $html = preg_replace(
                '/<(?:br\s*\/?|\/p|\/div|\/li|\/tr|\/h[1-6])\s*>/iu',
                "\n",
                (string) $message->html_body,
            );
            $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = trim((string) preg_replace("/[\t ]+\n/u", "\n", $text));
        $text = (string) preg_replace("/\n{4,}/u", "\n\n\n", $text);

        if ($text === '') {
            return '<p class="crm-mail-empty">Текст письма отсутствует.</p>';
        }

        if (mb_strlen($text) > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength))."\n\n… Письмо сокращено для быстрого просмотра. Полная HTML-версия доступна ниже.";
        }

        return nl2br(e($text));
    }

    public function html(CrmMailMessage $message): string
    {
        if (filled($message->html_body)) {
            $config = (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->dropElement('img')
                ->dropElement('script')
                ->dropElement('style')
                ->dropElement('iframe')
                ->withMaxInputLength(500_000);

            return (new HtmlSanitizer($config))->sanitize((string) $message->html_body);
        }

        $text = trim((string) $message->text_body);
        if ($text === '') {
            return '<p class="crm-mail-empty">Текст письма отсутствует.</p>';
        }

        return nl2br(e($text));
    }
}
