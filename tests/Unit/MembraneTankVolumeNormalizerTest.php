<?php

namespace Tests\Unit;

use App\Services\MembraneTankVolumeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MembraneTankVolumeNormalizerTest extends TestCase
{
    #[DataProvider('tankNames')]
    public function test_it_extracts_tank_volume_from_catalog_names(string $name, int $expected): void
    {
        $this->assertSame($expected, (new MembraneTankVolumeNormalizer)->extract($name));
    }

    public static function tankNames(): array
    {
        return [
            ['Мембранный бак Wester WRV 150', 150],
            ['Мембранный бак Flamco Flexcon R 35', 35],
            ['Гидроаккумулятор ВПк 200', 200],
            ['Расширительный бак UNIPUMP 12 л вертикальный', 12],
            ['Гидроаккумулятор В 80 «ХИТ»', 80],
            ['Расширительный бак 8 F', 8],
            ['Гидроаккумулятор В 500 ОС', 500],
        ];
    }

    #[DataProvider('accessories')]
    public function test_it_does_not_treat_accessory_compatibility_as_tank_volume(string $name): void
    {
        $this->assertNull((new MembraneTankVolumeNormalizer)->extract($name));
    }

    public static function accessories(): array
    {
        return [
            ['Мембрана 24 л (EPDM) с логотипом'],
            ['Комплект крепления для баков 8-35л Wester'],
            ['Кронштейн для насосной станции (гидроаккум. 24 л)'],
            ['Группа подключения мембранного бака Wester'],
        ];
    }

    public function test_it_prefers_an_explicit_volume_characteristic(): void
    {
        $normalizer = new MembraneTankVolumeNormalizer;

        $this->assertSame(50, $normalizer->extract(
            'Мембранный бак модель 10',
            ['Объем бака' => '50 л'],
        ));
    }

    public function test_it_reads_key_value_specs_used_by_product_cards(): void
    {
        $normalizer = new MembraneTankVolumeNormalizer;

        $this->assertSame(80, $normalizer->extract(
            'Расширительный бак модель R',
            [['key' => 'Объём бака', 'value' => '80 л']],
        ));
    }
}
