<?php

namespace Tests\Unit;

use App\Services\CrmMail\ImapMailboxSynchronizer;
use ReflectionClass;
use Tests\TestCase;

class ImapMailboxSynchronizerEncodingTest extends TestCase
{
    public function test_it_converts_windows_1251_mail_content_to_utf_8(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'toUtf8');
        $source = mb_convert_encoding('Письмо клиента', 'Windows-1251', 'UTF-8');

        $this->assertSame('Письмо клиента', $method->invoke($service, $source, 'windows-1251'));
    }

    public function test_it_decodes_mime_header_with_windows_1251_charset(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'decodeHeader');
        $encoded = '=?windows-1251?B?'.base64_encode(
            mb_convert_encoding('Запрос клиента', 'Windows-1251', 'UTF-8'),
        ).'?=';

        $this->assertSame('Запрос клиента', $method->invoke($service, $encoded));
    }
}
