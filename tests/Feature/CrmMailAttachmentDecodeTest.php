<?php

namespace Tests\Feature;

use App\Services\CrmMail\ImapMailboxSynchronizer;
use Tests\TestCase;

class CrmMailAttachmentDecodeTest extends TestCase
{
    public function test_a_base64_attachment_the_strict_decode_rejects_is_still_read(): void
    {
        defined('ENCBASE64') || define('ENCBASE64', 3);
        defined('ENCQUOTEDPRINTABLE') || define('ENCQUOTEDPRINTABLE', 4);
        $file = random_bytes(3000);
        $wrapped = implode("\r\n", str_split(base64_encode($file), 76));

        $this->assertSame($file, ImapMailboxSynchronizer::decodePartBody($wrapped, \ENCBASE64));
        // A stray character and chunks glued with their padding used to come out as 0 bytes.
        $this->assertSame($file, ImapMailboxSynchronizer::decodePartBody(substr($wrapped, 0, 200).'*'.substr($wrapped, 200), \ENCBASE64));
        $glued = base64_encode('abcd')."\r\n".base64_encode('xyz12')."\r\n".base64_encode('tail!');
        $this->assertSame('abcdxyz12tail!', ImapMailboxSynchronizer::decodePartBody($glued, \ENCBASE64));
    }
}
