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

        $this->assertCount(64, $entries);
        foreach ($entries as $slug => $entry) {
            $this->assertNotSame('', $slug);
            $this->assertGreaterThan(0, $entry['area']);
            $this->assertMatchesRegularExpression('/^https:\/\/(?:www\.|old\.)?(?:panadero\.com|mbs\.rs|nordflam\.eu|t-m-f\.ru)\//', $entry['source_url']);
            $this->assertNotSame('', $entry['source_label']);
        }
    }

    public function test_current_heating_stove_slugs_reuse_verified_model_evidence(): void
    {
        $entries = (new StoveOfficialHeatingAreaCatalog)->entries();

        $this->assertSame(56, $entries['po-student-cd-sk-tv']['area']);
        $this->assertSame(74, $entries['po-farengeit-10-antracit']['area']);
        $this->assertSame(37, $entries['po-vodogreinaia-normal-batareia-tv-to-antracit-115']['area']);
        $this->assertSame(19, $entries['po-zoluska-2016']['area']);
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
