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

    public function test_it_selects_new_uids_locally_after_the_initial_import(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'candidateUids');

        $this->assertSame(
            [3, 4, 5, 6, 7, 8, 9, 10, 11],
            $method->invoke($service, range(1, 11), 2),
        );
    }

    public function test_it_rechecks_recent_uids_when_there_are_no_new_messages(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'candidateUids');

        $this->assertSame([9, 10, 11], $method->invoke($service, range(1, 11), 11, 3));
    }

    public function test_it_reads_and_sanitizes_an_encoded_attachment_filename(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $filenameMethod = new \ReflectionMethod($service, 'attachmentFilename');
        $safeNameMethod = new \ReflectionMethod($service, 'safeAttachmentName');
        $encoded = '=?UTF-8?B?'.base64_encode('Заказ поставщику.pdf').'?=';
        $part = (object) [
            'dparameters' => [(object) ['attribute' => 'filename', 'value' => $encoded]],
            'parameters' => [],
        ];

        $filename = $filenameMethod->invoke($service, $part);

        $this->assertSame('Заказ поставщику.pdf', $filename);
        $this->assertSame('Заказ-поставщику.pdf', $safeNameMethod->invoke($service, '../Заказ:поставщику.pdf', 1));
    }

    public function test_it_decodes_an_rfc_2231_attachment_filename(): void
    {
        $service = (new ReflectionClass(ImapMailboxSynchronizer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($service, 'attachmentFilename');
        $part = (object) [
            'dparameters' => [(object) [
                'attribute' => 'filename*',
                'value' => "UTF-8''%D0%A1%D1%87%D0%B5%D1%82%20%D0%BD%D0%B0%20%D0%BE%D0%BF%D0%BB%D0%B0%D1%82%D1%83.pdf",
            ]],
        ];

        $this->assertSame('Счет на оплату.pdf', $method->invoke($service, $part));
    }
}
