<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogRecruitmentPlacementTest extends TestCase
{
    public function test_installer_recruitment_cta_is_rendered_once_at_the_end_of_category_content(): void
    {
        $template = file_get_contents(resource_path('views/pages/catalog.blade.php'));
        $include = "@include('partials.installer-recruitment-note', \$installerRecruitment)";
        $lastContentBlock = "@include('partials.gas-meter-category-content'";

        $this->assertSame(1, substr_count($template, $include));
        $this->assertGreaterThan(strpos($template, $lastContentBlock), strpos($template, $include));
        $this->assertGreaterThan(strpos($template, 'id="catalog-products"'), strpos($template, $include));
    }
}
