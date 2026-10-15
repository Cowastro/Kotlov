<?php

namespace Tests\Unit;

use App\Services\CrmMail\ImapMailboxSynchronizer;
use ReflectionMethod;
use Tests\TestCase;

class ImapAttachmentOnlyBodyTest extends TestCase
{
    public function test_attachment_only_mail_does_not_read_raw_mime_as_text(): void
    {
        $service = $this->parser();
        $structure = (object) ['parts' => [(object) ['type' => 3, 'subtype' => 'DOCX', 'disposition' => 'ATTACHMENT']]];
        $this->assertSame(['text' => null, 'html' => null], $this->extract($service, $structure));
        $this->assertSame([], $service->readParts);
    }

    public function test_real_plain_and_html_bodies_are_kept_and_named_text_files_are_skipped(): void
    {
        $service = $this->parser();
        $structure = (object) ['parts' => [
            (object) ['type' => 0, 'subtype' => 'PLAIN', 'content' => 'Письмо клиента'],
            (object) ['parts' => [(object) ['type' => 0, 'subtype' => 'HTML', 'content' => '<p>Письмо клиента</p>']]],
            (object) ['type' => 0, 'subtype' => 'PLAIN', 'parameters' => [(object) ['attribute' => 'name', 'value' => 'notes.txt']]],
            (object) ['type' => 0, 'subtype' => 'CALENDAR'],
        ]];
        $this->assertSame(['text' => 'Письмо клиента', 'html' => '<p>Письмо клиента</p>'], $this->extract($service, $structure));
        $this->assertSame(['1', '2.1'], $service->readParts);
    }

    private function parser(): ImapMailboxSynchronizer
    {
        return new class extends ImapMailboxSynchronizer
        {
            public array $readParts = [];

            public function __construct() {}

            protected function readTextPart($imap, int $messageNumber, object $part, string $prefix): string
            {
                $this->readParts[] = $prefix;

                return $part->content;
            }
        };
    }

    private function extract(ImapMailboxSynchronizer $service, object $structure): array
    {
        return (new ReflectionMethod($service, 'extractBodies'))->invoke($service, null, 1, $structure);
    }
}
