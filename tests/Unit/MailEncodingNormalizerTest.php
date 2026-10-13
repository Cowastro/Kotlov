<?php

namespace Tests\Unit;

use App\Services\CrmMail\MailEncodingNormalizer;
use PHPUnit\Framework\TestCase;

class MailEncodingNormalizerTest extends TestCase
{
    public function test_it_decodes_lowercase_utf_8_base64_subject(): void
    {
        $subject = 'Re: ООО "БК Милк" (Колпино)';
        $encoded = '=?utf-8?B?'.base64_encode($subject).'?=';

        $this->assertSame($subject, (new MailEncodingNormalizer())->decodeHeader($encoded));
    }

    public function test_it_decodes_folded_adjacent_encoded_words(): void
    {
        $encoded = '=?UTF-8?B?'.base64_encode('Запрос ').'?='.
            "\r\n\t".'=?UTF-8?Q?=D0=BA=D0=BB=D0=B8=D0=B5=D0=BD=D1=82=D0=B0?=';

        $this->assertSame('Запрос клиента', (new MailEncodingNormalizer())->decodeHeader($encoded));
    }

    public function test_it_keeps_plain_utf_8_subject_unchanged(): void
    {
        $this->assertSame('Обычная тема', (new MailEncodingNormalizer())->decodeHeader('Обычная тема'));
    }
}
