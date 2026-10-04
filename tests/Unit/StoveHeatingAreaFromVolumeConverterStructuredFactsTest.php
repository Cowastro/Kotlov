<?php

namespace Tests\Unit;

use App\Services\StoveHeatingAreaFromVolumeConverter;
use PHPUnit\Framework\TestCase;

class StoveHeatingAreaFromVolumeConverterStructuredFactsTest extends TestCase
{
    public function test_it_converts_supported_structured_volume_specs(): void
    {
        $converter = new StoveHeatingAreaFromVolumeConverter;

        $this->assertSame(80.0, $converter->detect([
            ['key' => 'Объем помещения, м3', 'value' => '140-200'],
        ]));
        $this->assertSame(56.0, $converter->detect([
            'Объём отапливаемого помещения' => 'до 140 м³',
        ]));
    }

    public function test_it_ignores_unrelated_volumes_and_conflicts(): void
    {
        $converter = new StoveHeatingAreaFromVolumeConverter;

        $this->assertNull($converter->detect([
            'Объём водяного контура' => '22 л',
            'Объём духовки' => '30 л',
        ]));
        $this->assertNull($converter->detect([
            'Объём помещения' => '100 м³',
        ], [
            ['name' => 'Максимальный объём обогрева', 'value' => '200 м³'],
        ]));
    }
}
