<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileChatWidgetOffsetTest extends TestCase
{
    public function test_external_chat_widget_is_not_loaded_beside_unified_help_control(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/amerce.blade.php'));

        $this->assertStringNotContainsString('umnicoWidgetHash', $layout);
        $this->assertStringNotContainsString('widget-loader.js', $layout);
    }

    public function test_mobile_catalog_menu_hides_floating_assistants_until_it_closes(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/amerce.blade.php'));
        $styles = file_get_contents(public_path('assets/css/kotlov.css'));

        $this->assertStringContainsString("e.target.id === 'mobileMenu'", $layout);
        $this->assertStringContainsString("classList.add('mobile-catalog-menu-is-open')", $layout);
        $this->assertStringContainsString("classList.remove('mobile-catalog-menu-is-open')", $layout);
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
