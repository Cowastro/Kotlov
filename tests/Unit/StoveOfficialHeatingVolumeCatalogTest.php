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

        $this->assertCount(45, $entries);

        foreach ($entries as $slug => $entry) {
            $this->assertNotSame('', $slug);
            $this->assertNotNull($normalizer->detect([
                'Объём отапливаемого помещения' => $entry['volume'],
            ]));
            $this->assertStringStartsWith('https://', $entry['source_url']);
            $this->assertNotSame('', $entry['source_label']);
        }
    }

    public function test_ecokamin_praga_uses_official_volume_without_area_conversion(): void
    {
        $entries = (new StoveOfficialHeatingVolumeCatalog)->entries();
        $praga = collect($entries)->filter(
            fn (array $entry, string $slug) => str_starts_with($slug, 'kamin-praga-uglovoy-')
        );

        $this->assertCount(4, $praga);

        foreach ($praga as $entry) {
            $this->assertSame('105–240 м³', $entry['volume']);
            $this->assertStringStartsWith('https://ecokamin.ru/catalog/kaminy/praga/', $entry['source_url']);
        }
    }
}
