<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileChatWidgetOffsetTest extends TestCase
{
    public function test_native_chat_launcher_is_replaced_by_unified_help_control(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/amerce.blade.php'));

        $this->assertStringContainsString('kotlov-umnico-unified-help', $layout);
        $this->assertStringContainsString(
            '.widget__preview-block{display:none!important}',
            $layout,
        );
        $this->assertStringNotContainsString('.widget__chat-preview{display:none!important}', $layout);
        $this->assertStringContainsString("hasProductPurchaseBar ? '148px' : '78px'", $layout);
    }

    public function test_mobile_catalog_menu_hides_floating_assistants_until_it_closes(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/amerce.blade.php'));
        $styles = file_get_contents(public_path('assets/css/kotlov.css'));

        $this->assertStringContainsString("e.target.id === 'mobileMenu'", $layout);
        $this->assertStringContainsString("classList.add('mobile-catalog-menu-is-open')", $layout);
        $this->assertStringContainsString("classList.remove('mobile-catalog-menu-is-open')", $layout);
        $this->assertStringContainsString(
            'body.mobile-catalog-menu-is-open #umnico-widget-wrapper',
            $styles,
        );
        $this->assertStringContainsString(
            'body.mobile-catalog-menu-is-open .ai-selector__launcher',
            $styles,
        );
        $this->assertStringContainsString(
            'body.mobile-catalog-menu-is-open .ai-selector__assistance-menu',
            $styles,
        );
    }
}
