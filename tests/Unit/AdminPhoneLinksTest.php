<?php

namespace Tests\Unit;

use App\Support\AdminPhoneLinks;
use PHPUnit\Framework\TestCase;

class AdminPhoneLinksTest extends TestCase
{
    public function test_it_builds_compact_call_and_messenger_links(): void
    {
        $html = AdminPhoneLinks::render('+375 (29) 515-53-77')?->toHtml();

        $this->assertNotNull($html);
        $this->assertStringContainsString('tel:+375295155377', $html);
        $this->assertStringContainsString('https://wa.me/375295155377', $html);
        $this->assertStringContainsString('viber://chat?number=%2B375295155377', $html);
        $this->assertStringContainsString('https://t.me/+375295155377?profile', $html);
    }

    public function test_it_converts_belarus_eight_prefix_to_international_format(): void
    {
        $this->assertSame('375295155377', AdminPhoneLinks::digits('8 (029) 515-53-77'));
    }

    public function test_it_renders_multiple_phone_numbers(): void
    {
        $html = AdminPhoneLinks::renderList('+375 29 515-53-77, +375 44 592-09-20')?->toHtml();

        $this->assertStringContainsString('375295155377', $html);
        $this->assertStringContainsString('375445920920', $html);
    }
}
