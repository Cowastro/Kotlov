<?php

namespace Tests\Unit;

use App\Support\Catalog\BelomoG4ThermalModels;
use PHPUnit\Framework\TestCase;

class BelomoG4ThermalModelsTest extends TestCase
{
    public function test_it_describes_all_four_g4_thermal_executions_in_plain_language(): void
    {
        $models = BelomoG4ThermalModels::all();

        $this->assertSame([
            '8181.00.00.000-20',
            '8181.00.00.000-21',
            '8336.00.00.000-06',
            '8336.00.00.000-07',
        ], array_column($models, 'article_label'));
        $this->assertSame(['left', 'right', 'left', 'right'], array_column($models, 'direction'));
        $this->assertSame([200, 200, 110, 110], array_column($models, 'length'));
        $this->assertSame([true, true, true, true], array_column($models, 'thermal'));
    }
}
