<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileChatWidgetOffsetTest extends TestCase
{
    public function test_product_purchase_bar_gets_a_larger_mobile_chat_offset(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/amerce.blade.php'));

        $this->assertStringContainsString(
            "document.querySelector('.product-detail-page .tf-sticky-btn-atc')",
            $layout,
        );
        $this->assertStringContainsString("hasProductPurchaseBar ? '158px' : '88px'", $layout);
        $this->assertStringContainsString('env(safe-area-inset-bottom, 0px)', $layout);
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
    }
}
