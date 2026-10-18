<?php

namespace Tests\Unit;

use App\Console\Commands\EnrichTmManagementWorkbookCommand;
use ReflectionClass;
use Tests\TestCase;

class EnrichTmManagementWorkbookCommandTest extends TestCase
{
    public function test_it_extracts_structured_sfa_specs(): void
    {
        $specs = $this->invoke('sourceSpecs', [
            'SFA',
            'Насос для кухни',
            "Количество доступных входов 4\nМощность двигателя 400 Вт\nКласс IP-защиты IP44",
        ]);

        $this->assertSame('4', $specs['Количество доступных входов']);
        $this->assertSame('400 Вт', $specs['Мощность двигателя']);
        $this->assertSame('IP44', $specs['Класс IP-защиты']);
    }

    public function test_it_keeps_shinhoo_power_as_a_specification(): void
    {
        $this->assertSame([
            'Максимальная потребляемая мощность' => '5-39 Вт',
        ], $this->invoke('sourceSpecs', ['Shinhoo', 'Описание', '5-39 Вт']));
    }

    public function test_it_matches_an_exact_product_name_only_inside_the_same_brand(): void
    {
        $index = [
            'article' => [],
            'name' => [],
            'brand_name' => [
                'dedietrich|котелms24' => [415],
            ],
        ];

        $this->assertSame(415, $this->invoke('matchedProductId', [$index, 'De Dietrich', 'unknown', 'Котел MS 24']));
        $this->assertNull($this->invoke('matchedProductId', [$index, 'Other', 'unknown', 'Котел MS 24']));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $command = new EnrichTmManagementWorkbookCommand();
        return (new ReflectionClass($command))->getMethod($method)->invokeArgs($command, $arguments);
    }
}
