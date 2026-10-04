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

        $this->assertCount(53, $entries);

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

    public function test_ecokamin_catalog_models_keep_their_published_volumes(): void
    {
        $entries = (new StoveOfficialHeatingVolumeCatalog)->entries();

        $this->assertSame('135–240 м³', $entries['pech-kamin-ecokamin-bavariya-panorama-prizma-s-plitoy']['volume']);
        foreach ([
            'kamin-eklips-ostrovnoi-gigant-grafit',
            'kamin-eklips-ostrovnoi-gigant-s-cernym-samotom',
            'kamin-eklips-ostrovnoi-gigant',
        ] as $slug) {
            $this->assertSame('140–325 м³', $entries[$slug]['volume']);
            $this->assertStringContainsString('ecokamin.ru/upload/', $entries[$slug]['source_url']);
        }
    }

    public function test_vesuviy_fireplace_stoves_keep_their_published_volumes(): void
    {
        $entries = (new StoveOfficialHeatingVolumeCatalog)->entries();

        foreach ([
            'pec-kamin-vezuvii-ast-13k-antracit' => 'до 260 м³',
            'pec-kamin-vezuvii-hr-15-antracit' => 'до 300 м³',
            'pec-kamin-vezuvii-hr-15r-antracit' => 'до 300 м³',
            'pec-kamin-vezuvii-kz-14rs-antracit' => 'до 280 м³',
        ] as $slug => $volume) {
            $this->assertSame($volume, $entries[$slug]['volume']);
            $this->assertStringStartsWith('https://vezuviy.su/', $entries[$slug]['source_url']);
        }
    }
}
