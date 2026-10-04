<?php

namespace Tests\Unit;

use App\Services\StoveHeatingAreaFromVolumeConverter;
use PHPUnit\Framework\TestCase;

class StoveHeatingAreaFromVolumeConverterTest extends TestCase
{
    public function test_it_uses_the_maximum_volume_and_standard_ceiling_height(): void
    {
        $converter = new StoveHeatingAreaFromVolumeConverter;

        $this->assertSame(96.0, $converter->convert('105–240 м³'));
        $this->assertSame(40.0, $converter->convert('до 100 м3'));
        $this->assertSame(68.0, $converter->convert('100–170 м³'));
        $this->assertSame('до 96 м² (при высоте потолка 2,5 м)', $converter->label(96.0));
    }

    public function test_it_rejects_missing_or_implausible_volume(): void
    {
        $converter = new StoveHeatingAreaFromVolumeConverter;

        $this->assertNull($converter->convert('не указано'));
        $this->assertNull($converter->convert('6000 м³'));
    }
}
