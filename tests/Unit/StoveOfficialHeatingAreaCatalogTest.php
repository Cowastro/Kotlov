<?php

namespace Tests\Unit;

use App\Services\StoveHeatingAreaNormalizer;
use App\Services\StoveOfficialHeatingAreaCatalog;
use PHPUnit\Framework\TestCase;

class StoveOfficialHeatingAreaCatalogTest extends TestCase
{
    public function test_every_entry_contains_an_explicit_area_and_official_https_source(): void
    {
        $entries = (new StoveOfficialHeatingAreaCatalog)->entries();

        $this->assertCount(9, $entries);
        foreach ($entries as $slug => $entry) {
            $this->assertNotSame('', $slug);
            $this->assertGreaterThan(0, $entry['area']);
            $this->assertMatchesRegularExpression('/^https:\/\/(?:www\.)?(?:panadero\.com|mbs\.rs)\//', $entry['source_url']);
            $this->assertNotSame('', $entry['source_label']);
        }
    }

    public function test_every_area_maps_to_a_catalog_filter_range(): void
    {
        $normalizer = new StoveHeatingAreaNormalizer;

        foreach ((new StoveOfficialHeatingAreaCatalog)->entries() as $entry) {
            $this->assertNotNull($normalizer->detect([
                'Площадь отапливаемого помещения' => $entry['area'].' м²',
            ]));
        }
    }
}
