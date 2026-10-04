<?php

namespace Tests\Unit;

use App\Console\Commands\ApplyOfficialStoveHeatingVolumesCommand;
use App\Services\StoveHeatingAreaFromVolumeConverter;
use App\Services\StoveHeatingAreaNormalizer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ApplyOfficialStoveHeatingVolumesCommandTest extends TestCase
{
    public function test_it_refreshes_an_area_previously_derived_by_the_command(): void
    {
        [$specs, $derivedArea] = $this->merge(
            [['key' => 'Площадь отапливаемого помещения', 'value' => 'до 20 м² (при высоте потолка 2,5 м)']],
            72.0,
            ['derived_heating_area' => ['value' => 20.0]],
        );

        $this->assertSame(72.0, $derivedArea);
        $this->assertSame('до 72 м² (при высоте потолка 2,5 м)', $specs[0]['value']);
    }

    public function test_it_preserves_an_explicit_area_that_no_longer_matches_derived_provenance(): void
    {
        [$specs, $derivedArea] = $this->merge(
            [['key' => 'Площадь отапливаемого помещения', 'value' => 'до 50 м²']],
            72.0,
            ['derived_heating_area' => ['value' => 20.0]],
        );

        $this->assertNull($derivedArea);
        $this->assertSame('до 50 м²', $specs[0]['value']);
    }

    public function test_it_adds_a_derived_area_when_no_area_is_present(): void
    {
        [$specs, $derivedArea] = $this->merge([], 40.0, []);

        $this->assertSame(40.0, $derivedArea);
        $this->assertSame('до 40 м² (при высоте потолка 2,5 м)', $specs[0]['value']);
    }

    /** @return array{0: array<int|string, mixed>, 1: float|null} */
    private function merge(array $specs, ?float $area, array $serviceInfo): array
    {
        $method = new ReflectionMethod(ApplyOfficialStoveHeatingVolumesCommand::class, 'withDerivedAreaSpec');

        return $method->invoke(
            new ApplyOfficialStoveHeatingVolumesCommand,
            $specs,
            $area,
            $serviceInfo,
            new StoveHeatingAreaNormalizer,
            new StoveHeatingAreaFromVolumeConverter,
        );
    }
}
