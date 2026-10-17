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
}
