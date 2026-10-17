<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SupportChatMobileLayoutTest extends TestCase
{
    public function test_mobile_chat_is_constrained_by_visible_modal_height_and_safe_area(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/assets/css/kotlov.css');

        $this->assertStringContainsString(
            'height: calc(100% - max(12px, env(safe-area-inset-top)))',
            $css
        );
        $this->assertStringContainsString(
            'height: min(720px, 100%); max-height: 100%',
            $css
        );
        $this->assertStringNotContainsString(
            '.support-chat-modal .modal-content { width: 100%; height: min(720px, calc(100dvh - 18px))',
            $css
        );
    }
}
