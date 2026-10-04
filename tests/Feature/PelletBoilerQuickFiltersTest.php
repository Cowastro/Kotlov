<?php

namespace Tests\Feature;

use Tests\TestCase;

class PelletBoilerQuickFiltersTest extends TestCase
{
    public function test_power_ranges_render_as_quick_filters(): void
    {
        $pelletPowerRanges = collect([
            (object) ['key' => 'up-to-25', 'label' => 'до 25 кВт', 'products_count' => 12],
            (object) ['key' => '26-50', 'label' => '26–50 кВт', 'products_count' => 8],
            (object) ['key' => '51-100', 'label' => '51–100 кВт', 'products_count' => 6],
        ]);

        $html = view('partials.pellet-boiler-quick-filters', compact('pelletPowerRanges'))->render();

        $this->assertStringContainsString('Как выбрать пеллетный котёл', $html);
        $this->assertStringContainsString('до 25 кВт', $html);
        $this->assertStringContainsString('power=up-to-25', $html);
        $this->assertStringContainsString('12', $html);
        $this->assertStringContainsString('Пеллетный или газовый котёл', $html);
        $this->assertStringContainsString('/pelletnye-gorelki', $html);
        $this->assertStringContainsString('/tverdotoplivnye', $html);
    }

    public function test_active_power_range_can_be_removed_without_losing_brand(): void
    {
        request()->merge(['power' => '26-50', 'brand' => 15, 'page' => 2]);
        $pelletPowerRanges = collect([
            (object) ['key' => '26-50', 'label' => '26–50 кВт', 'products_count' => 8],
        ]);

        $html = view('partials.pellet-boiler-quick-filters', compact('pelletPowerRanges'))->render();

        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('brand=15', $html);
        $this->assertStringNotContainsString('power=', $html);
        $this->assertStringNotContainsString('page=2', $html);
    }

    public function test_guide_and_internal_links_render_without_power_ranges(): void
    {
        $pelletPowerRanges = collect();

        $html = view('partials.pellet-boiler-quick-filters', compact('pelletPowerRanges'))->render();

        $this->assertStringContainsString('Как выбрать пеллетный котёл', $html);
        $this->assertStringContainsString('объём бункера', $html);
        $this->assertStringContainsString('/blog/pelletnyy-kotel-ili-gazovyy', $html);
    }
}
