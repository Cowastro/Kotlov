<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PartnerAcquisitionEntryPointsTest extends TestCase
{
    public function test_homepage_contains_distinct_installer_and_company_paths(): void
    {
        $template = file_get_contents($this->projectPath('resources/views/pages/home-new.blade.php'));

        $this->assertStringContainsString('Для монтажников и компаний', $template);
        $this->assertStringContainsString('Стать партнёром-монтажником', $template);
        $this->assertStringContainsString('Подключить компанию', $template);
        $this->assertStringContainsString("route('become-installer')", $template);
        $this->assertStringContainsString("route('partners')", $template);
        $this->assertStringContainsString('d-flex flex-column flex-sm-row', $template);
    }

    public function test_header_and_footer_expose_the_b2b_entry_point(): void
    {
        $header = file_get_contents($this->projectPath('resources/views/partials/amerce-header.blade.php'));
        $footer = file_get_contents($this->projectPath('resources/views/partials/footer.blade.php'));

        $this->assertStringContainsString('Партнёрам / ', $header);
        $this->assertStringContainsString('site-topbar__b2b', $header);
        $this->assertStringContainsString('Для бизнеса / B2B', $footer);
        $this->assertStringContainsString('href="/partners"', $header);
        $this->assertStringContainsString('href="/partners"', $footer);
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public function test_company_button_points_to_the_company_connection_section(): void
    {
        $home = file_get_contents($this->projectPath('resources/views/pages/home-new.blade.php'));
        $partners = file_get_contents($this->projectPath('resources/views/pages/partners.blade.php'));

        $this->assertMatchesRegularExpression(
            '~href="\{\{ route\(\x27partners\x27\) \}\}#company-connect"[^>]*>\s*Подключить компанию~u',
            $home,
        );
        $this->assertMatchesRegularExpression('~<section[^>]* id="company-connect"[^>]*>~', $partners);
        $this->assertSame(1, substr_count($partners, 'id="company-connect"'));
    }
}
