<?php

namespace Tests\Unit;

use App\Services\StoveMaterialNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoveMaterialNormalizerTest extends TestCase
{
    #[DataProvider('explicitMaterials')]
    public function test_it_normalizes_explicit_material_variants(string $value, string $expected): void
    {
        $normalizer = new StoveMaterialNormalizer;

        $this->assertSame($expected, $normalizer->detect('', ['Материал корпуса' => $value]));
    }

    public static function explicitMaterials(): array
    {
        return [
            ['чугун', StoveMaterialNormalizer::CAST_IRON],
            ['Составной чугун', StoveMaterialNormalizer::CAST_IRON],
            ['Чугун марки СЧ20', StoveMaterialNormalizer::CAST_IRON],
            ['сталь', StoveMaterialNormalizer::STEEL],
            ['Сталь + шамот', StoveMaterialNormalizer::STEEL],
            ['конструкционная сталь', StoveMaterialNormalizer::STEEL],
        ];
    }

    public function test_it_reads_list_style_specs_and_explicit_product_names(): void
    {
        $normalizer = new StoveMaterialNormalizer;

        $this->assertSame(StoveMaterialNormalizer::CAST_IRON, $normalizer->detect('', [
            ['key' => 'Материал топки', 'value' => 'Чугун'],
        ]));
        $this->assertSame(StoveMaterialNormalizer::STEEL, $normalizer->detect('Стальная печь для дачи'));
    }

    public function test_it_ignores_unrelated_material_fields_and_brand_only_guesses(): void
    {
        $normalizer = new StoveMaterialNormalizer;

        $this->assertNull($normalizer->detect('Печь Мета-Бел Амур', [
            'Материал дверки для камина' => 'стекло',
            'Материал теплообменника' => 'сталь',
        ]));
    }

    public function test_it_rejects_conflicting_material_facts(): void
    {
        $normalizer = new StoveMaterialNormalizer;

        $this->assertNull($normalizer->detect('Чугунная печь', [
            'Материал корпуса' => 'сталь',
        ]));
    }
}
