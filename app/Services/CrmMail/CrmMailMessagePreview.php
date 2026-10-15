<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class CrmMailMessagePreview
{
    public function originalHtml(CrmMailMessage $message): ?string
    {
        if (blank($message->html_body)) {
            return null;
        }

        $html = (string) $message->html_body;
        $logo = public_path('img/logo-black.png');

        if (is_file($logo)) {
            $logoContents = file_get_contents($logo);
            if ($logoContents !== false) {
                $html = str_ireplace('cid:kotlov-logo', 'data:image/png;base64,'.base64_encode($logoContents), $html);
            }
        }

        // Display-only adaptation inside the sandboxed iframe. Never rewrite
        // stored/sent HTML or enable scripts/same-origin permissions.
        $mobileHead = <<<'HTML'
<meta name="viewport" content="width=device-width, initial-scale=1">
<style id="crm-mail-mobile-preview">
@media (max-width: 700px) {
    body { margin: 0 !important; overflow-wrap: anywhere; }
    img { max-width: 100%; }
    .email-shell { padding: 0 !important; }
    .email-card { max-width: 100% !important; border-radius: 0 !important; }
    .email-pad { padding-left: 14px !important; padding-right: 14px !important; }
    .email-logo { width: 160px !important; max-width: 100% !important; height: auto !important; }
    .signature-column { display: block !important; width: auto !important; text-align: left !important; padding-left: 14px !important; padding-right: 14px !important; }
    .signature-company { padding-top: 0 !important; }
}
</style>
HTML;
        if (preg_match('/<\/head\s*>/i', $html)) {
            return preg_replace('/<\/head\s*>/i', $mobileHead.'</head>', $html, 1);
        }
        if (preg_match('/<html\b[^>]*>/i', $html)) {
            return preg_replace('/(<html\b[^>]*>)/i', '$1<head>'.$mobileHead.'</head>', $html, 1);
        }

        return '<!doctype html><html><head>'.$mobileHead.'</head><body>'.$html.'</body></html>';
    }

    public function hasVisibleContent(CrmMailMessage $message): bool
    {
        $text = $this->plainText($message);

        return preg_match('/[^\s\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', $text) === 1;
    }

    public function compactHtml(CrmMailMessage $message, int $maxLength = 12000): string
    {
        $text = $this->plainText($message);

        if ($text === '') {
            return '<p class="crm-mail-empty">Текст письма отсутствует.</p>';
        }

        if (mb_strlen($text) > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength))."\n\n… Письмо сокращено для быстрого просмотра. Полная HTML-версия доступна ниже.";
        }

        return nl2br(e($text));
    }

    public function plainText(CrmMailMessage $message, ?int $maxLength = null): string
    {
        if (filled($message->html_body)) {
            $html = preg_replace(
                '/<(style|script|head|noscript|svg)\b[^>]*>.*?<\/\1\s*>/isu',
                ' ',
                (string) $message->html_body,
            );
            $html = preg_replace(
                '/<(?:br\s*\/?|\/p|\/div|\/li|\/tr|\/td|\/th|\/h[1-6])\s*>/iu',
                "\n",
                (string) $html,
            );
            $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            $text = trim((string) $message->text_body);
        }

        // Some mail generators put CSS into the text/plain MIME part. It is
        // not HTML anymore, so strip_tags() cannot remove it. Drop ordinary
        // selector blocks before this text is shown in CRM or Telegram.
        $text = (string) preg_replace(
            '~(?:^|\s)(?:[.#]?[a-z_][a-z0-9_.:#-]*(?:\s+[.#]?[a-z_][a-z0-9_.:#-]*)*(?:\s*,\s*[.#]?[a-z_][a-z0-9_.:#-]*)*)\s*\{[^{}]*[a-z-]+\s*:[^{}]*\}~iu',
            ' ',
            $text,
        );
        $text = trim((string) preg_replace("/[\t ]+\n/u", "\n", $text));
        $text = (string) preg_replace("/\n{4,}/u", "\n\n\n", $text);

        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            return rtrim(mb_substr($text, 0, $maxLength));
        }

        return $text;
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
