<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use Tests\TestCase;

class AdminCompactDensityTest extends TestCase
{
    public function test_desktop_admin_uses_compact_scale_without_shrinking_mobile_controls(): void
    {
        $styles = file_get_contents(resource_path('views/filament/admin/layout-fixes.blade.php'));

        $this->assertStringContainsString('@media (min-width: 1024px)', $styles);
        $this->assertStringContainsString('--text-sm: 0.8125rem', $styles);
        $this->assertStringContainsString('.fi-sidebar-item-btn', $styles);
        $this->assertStringContainsString('padding-block: 0.375rem', $styles);
    }

    public function test_mail_list_has_its_own_compact_table_scope(): void
    {
        $page = new \ReflectionClass(ListCrmMailThreads::class);
        $property = $page->getProperty('extraBodyAttributes');
        $property->setAccessible(true);

        $this->assertSame(
            ['class' => 'crm-mail-list-page'],
            $property->getValue($page->newInstanceWithoutConstructor()),
        );

        $styles = file_get_contents(resource_path('views/filament/admin/layout-fixes.blade.php'));

        $this->assertStringContainsString('.crm-mail-list-page .fi-ta-header-cell', $styles);
        $this->assertStringContainsString('.crm-mail-list-page .fi-ta-actions .fi-icon-btn', $styles);
    }
}
