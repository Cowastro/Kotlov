<?php

namespace Tests\Unit;

use App\Services\StoveHeatingAreaNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoveHeatingAreaNormalizerTest extends TestCase
{
    #[DataProvider('explicitAreas')]
    public function test_it_normalizes_explicit_heated_area_values(string $value, string $expected): void
    {
        $normalizer = new StoveHeatingAreaNormalizer;

        $this->assertSame($expected, $normalizer->detect(['Площадь обогрева' => $value]));
    }

    public static function explicitAreas(): array
    {
        return [
            ['35 м²', StoveHeatingAreaNormalizer::UNDER_50],
            ['до 50 м2', StoveHeatingAreaNormalizer::UNDER_50],
            ['50', StoveHeatingAreaNormalizer::FROM_50_TO_100],
            ['80 кв. м', StoveHeatingAreaNormalizer::FROM_50_TO_100],
            ['50–100 м²', StoveHeatingAreaNormalizer::FROM_50_TO_100],
            ['120 м2', StoveHeatingAreaNormalizer::OVER_100],
            ['более 100 м²', StoveHeatingAreaNormalizer::OVER_100],
        ];
    }

    public function test_it_reads_list_style_specs_and_attribute_facts(): void
    {
        $normalizer = new StoveHeatingAreaNormalizer;

        $this->assertSame(StoveHeatingAreaNormalizer::FROM_50_TO_100, $normalizer->detect([
            ['key' => 'Максимальная площадь обогрева', 'value' => '90 м²'],
        ]));
        $this->assertSame(StoveHeatingAreaNormalizer::OVER_100, $normalizer->detect([], [
            ['name' => 'Отапливаемая площадь', 'value' => '140'],
        ]));
    }

    public function test_it_ignores_power_volume_and_unrelated_dimensions(): void
    {
        $normalizer = new StoveHeatingAreaNormalizer;

        $this->assertNull($normalizer->detect([
            'Мощность' => '12 кВт',
            'Объем отапливаемого помещения' => '180 м³',
            'Размер помещения' => '80 м²',
        ]));
    }

    public function test_it_rejects_conflicting_explicit_area_facts(): void
    {
        $normalizer = new StoveHeatingAreaNormalizer;

        $this->assertNull($normalizer->detect(
            ['Площадь обогрева' => '45 м²'],
            [['name' => 'Площадь отапливаемого помещения', 'value' => '120 м²']]
        ));
    }
}
