<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class CrmMailMessagePreview
{
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
