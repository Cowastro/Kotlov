<?php

namespace Tests\Unit;

use App\Services\StoveHeatingVolumeNormalizer;
use App\Services\StoveOfficialHeatingVolumeCatalog;
use PHPUnit\Framework\TestCase;

class StoveOfficialHeatingVolumeCatalogTest extends TestCase
{
    public function test_each_official_entry_has_a_valid_volume_and_source(): void
    {
        $normalizer = new StoveHeatingVolumeNormalizer;
        $entries = (new StoveOfficialHeatingVolumeCatalog)->entries();

        $this->assertCount(21, $entries);

        foreach ($entries as $slug => $entry) {
            $this->assertNotSame('', $slug);
            $this->assertNotNull($normalizer->detect([
                'Объём отапливаемого помещения' => $entry['volume'],
            ]));
            $this->assertStringStartsWith('https://', $entry['source_url']);
            $this->assertNotSame('', $entry['source_label']);
        }
    }
}
