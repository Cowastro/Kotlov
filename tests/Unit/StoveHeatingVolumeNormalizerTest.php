<?php

namespace Tests\Unit;

use App\Services\StoveHeatingVolumeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoveHeatingVolumeNormalizerTest extends TestCase
{
    #[DataProvider('explicitVolumes')]
    public function test_it_normalizes_explicit_room_volumes(string $value, string $expected): void
    {
        $normalizer = new StoveHeatingVolumeNormalizer;

        $this->assertSame($expected, $normalizer->detect([
            'Объём отапливаемого помещения' => $value,
        ]));
    }

    public static function explicitVolumes(): array
    {
        return [
            ['до 50 м³', StoveHeatingVolumeNormalizer::UP_TO_100],
            ['60-100 м3', StoveHeatingVolumeNormalizer::UP_TO_100],
            ['60–150 м³', StoveHeatingVolumeNormalizer::FROM_101_TO_200],
            ['140–200 м³', StoveHeatingVolumeNormalizer::FROM_101_TO_200],
            ['200–300 м³', StoveHeatingVolumeNormalizer::OVER_200],
        ];
    }

    public function test_it_reads_list_specs_and_unit_in_key(): void
    {
        $normalizer = new StoveHeatingVolumeNormalizer;

        $this->assertSame(StoveHeatingVolumeNormalizer::FROM_101_TO_200, $normalizer->detect([
            ['key' => 'Объем помещения, м3', 'value' => '70-140'],
        ]));
    }

    public function test_it_does_not_treat_area_or_power_as_volume(): void
    {
        $normalizer = new StoveHeatingVolumeNormalizer;

        $this->assertNull($normalizer->detect([
            'Площадь отапливаемого помещения' => '80 м²',
            'Мощность' => '12 кВт',
        ]));
    }
}
