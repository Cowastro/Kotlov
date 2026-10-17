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

    public function test_closing_mobile_chat_restores_page_position_after_keyboard_and_modal_cleanup(): void
    {
        $javascript = file_get_contents(__DIR__.'/../../public/assets/js/ai-product-selector.js');

        $this->assertStringContainsString(
            "supportChatElement.addEventListener('show.bs.modal', rememberSupportChatPagePosition)",
            $javascript
        );
        $this->assertStringContainsString(
            "supportChatElement.addEventListener('hide.bs.modal'",
            $javascript
        );
        $this->assertStringContainsString(
            'document.activeElement.blur()',
            $javascript
        );
        $this->assertStringContainsString(
            "supportChatElement.addEventListener('hidden.bs.modal', restoreSupportChatPagePosition)",
            $javascript
        );
        $this->assertStringContainsString(
            'window.scrollTo(position.x, position.y)',
            $javascript
        );
    }
}
