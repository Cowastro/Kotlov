<?php

namespace Tests\Unit;

use App\Services\ElectricSaunaHeaterFilterNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ElectricSaunaHeaterFilterNormalizerTest extends TestCase
{
    public function test_it_extracts_only_explicit_technical_values(): void
    {
        $normalizer = new ElectricSaunaHeaterFilterNormalizer;

        $facts = $normalizer->extract([
            'Мощность' => '8,0 кВт',
            'Объём парной' => '7 м³-12 м³',
            'Закладка камней' => '25 кг',
        ]);

        $this->assertSame(8.0, $facts['power']);
        $this->assertSame(12.0, $facts['volume']);
        $this->assertSame(25.0, $facts['stones']);
    }

    public function test_it_does_not_treat_unrelated_numbers_as_filter_values(): void
    {
        $normalizer = new ElectricSaunaHeaterFilterNormalizer;

        $facts = $normalizer->extract(['Модель' => 'Harvia 80E', 'Гарантия' => '24 месяца']);

        $this->assertNull($facts['power']);
        $this->assertNull($facts['volume']);
        $this->assertNull($facts['stones']);
    }

    public function test_it_completes_verified_karina_nova_6e_facts(): void
    {
        $normalizer = new ElectricSaunaHeaterFilterNormalizer;

        $facts = $normalizer->withVerifiedModelFacts(
            'KARINA',
            'Электрическая печь KARINA Nova 6E',
            ['power' => null, 'volume' => null, 'stones' => null],
        );

        $this->assertSame(6.0, $facts['power']);
        $this->assertSame(8.0, $facts['volume']);
        $this->assertSame(100.0, $facts['stones']);
    }

    #[DataProvider('rangeProvider')]
    public function test_it_maps_boundaries_consistently(float $value, string $method, string $expected): void
    {
        $normalizer = new ElectricSaunaHeaterFilterNormalizer;

        $this->assertSame($expected, $normalizer->{$method}($value));
    }

    public static function rangeProvider(): array
    {
        return [
            [5.0, 'powerRange', 'до 5'],
            [8.0, 'powerRange', '5 — 8'],
            [9.0, 'powerRange', '8 — 11'],
            [15.0, 'powerRange', '11 — 15'],
            [20.0, 'powerRange', '15 и более'],
            [15.0, 'volumeRange', '10 — 15'],
            [20.0, 'volumeRange', '15 — 20'],
            [30.0, 'volumeRange', '20 — 30'],
            [31.0, 'volumeRange', '30 и более'],
            [25.0, 'stonesRange', '15 — 25'],
            [35.0, 'stonesRange', '25 — 35'],
        ];
    }
}
