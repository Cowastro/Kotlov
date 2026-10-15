<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogBrandFilterVisibilityTest extends TestCase
{
    public function test_selected_brand_remains_visible_and_can_be_cleared(): void
    {
        request()->merge([
            'brand' => 342,
            'sort' => 'price_asc',
            'page' => 3,
        ]);
        $brands = collect([
            (object) ['id' => 342, 'name' => 'Tatra', 'products_count' => 22],
            (object) ['id' => 100, 'name' => 'Другой производитель', 'products_count' => 15],
        ]);

        $html = view('partials.catalog-brand-filter', compact('brands'))->render();

        $this->assertStringContainsString('Производитель', $html);
        $this->assertStringContainsString('Все производители', $html);
        $this->assertStringContainsString('id="brand-342"', $html);
        $this->assertMatchesRegularExpression('/id="brand-342"[\s\S]*?checked/', $html);
        $this->assertStringContainsString("applyFilter('brand', '')", $html);
    }

    public function test_all_manufacturers_is_selected_without_brand_filter(): void
    {
        request()->query->remove('brand');
        $brands = collect([
            (object) ['id' => 342, 'name' => 'Tatra', 'products_count' => 22],
        ]);

        $html = view('partials.catalog-brand-filter', compact('brands'))->render();

        $this->assertMatchesRegularExpression('/id="brand-all"[\s\S]*?checked/', $html);
    }
}
