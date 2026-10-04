<?php

namespace Tests\Unit;

use App\Services\SaunaStoveFilterNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaunaStoveFilterNormalizerTest extends TestCase
{
    #[DataProvider('productFacts')]
    public function test_it_extracts_only_explicit_sauna_stove_facts(
        string $name,
        array $specs,
        array $attributes,
        array $expected,
    ): void {
        $actual = (new SaunaStoveFilterNormalizer)->extract($name, $specs, $attributes);

        $this->assertSame($expected, $actual);
    }

    public static function productFacts(): array
    {
        return [
            'FireWay structured specs' => [
                'Банная печь Fireway ПароВар 18 Ковка (K201)',
                ['Объём парной' => 'до 18 м³', 'Стекло' => 'Панорамное жаропрочное', 'Вынос топки' => 'Есть'],
                [],
                ['volume' => 18.0, 'door' => true, 'remote_firebox' => true],
            ],
            'ComfortProm name and specs' => [
                'Печь ComfortProm закрытая каменка 26 м3',
                ['Дверца' => 'без стекла (глухая)', 'Исполнение' => 'с выносом'],
                [],
                ['volume' => 26.0, 'door' => false, 'remote_firebox' => true],
            ],
            'legacy value attribute' => [
                'Печь для бани',
                [],
                ['Объём парилки' => 'от 10 до 20 м3'],
                ['volume' => 20.0, 'door' => null, 'remote_firebox' => null],
            ],
            'model number is not a volume' => [
                'Печь Медведь 30 Ковка',
                [],
                [],
                ['volume' => null, 'door' => null, 'remote_firebox' => null],
            ],
        ];
    }

    #[DataProvider('volumeRanges')]
    public function test_it_maps_maximum_volume_to_catalog_ranges(float $volume, string $range): void
    {
        $this->assertSame($range, (new SaunaStoveFilterNormalizer)->volumeRange($volume));
    }

    public static function volumeRanges(): array
    {
        return [
            [12, 'до 15'],
            [18, '15—20'],
            [24, '20—25'],
            [26, '25—30'],
            [36, '30 и более'],
        ];
    }

    #[DataProvider('verifiedModels')]
    public function test_it_uses_only_scoped_verified_model_facts(
        string $brand,
        string $name,
        array $expected,
    ): void {
        $normalizer = new SaunaStoveFilterNormalizer;
        $facts = $normalizer->withVerifiedModelFacts($brand, $name, [
            'volume' => null,
            'door' => null,
            'remote_firebox' => null,
        ]);

        $this->assertSame($expected, $facts);
    }

    public static function verifiedModels(): array
    {
        return [
            'Vesuvius 16 has documented maximum 18' => [
                'Везувий',
                'Банная печь Везувий Ураган Ковка 16 (205)',
                ['volume' => 18.0, 'door' => true, 'remote_firebox' => true],
            ],
            'EcoKamin Medved 30' => [
                'ЭкоКамин',
                'Печь банная чугунная ЭкоКамин Медведь 30 Ковка',
                ['volume' => 30.0, 'door' => null, 'remote_firebox' => true],
            ],
            'GFS Grom 50' => [
                'GFS',
                'Комплект Grom 50 (П) Президент',
                ['volume' => 50.0, 'door' => true, 'remote_firebox' => true],
            ],
            'ASTON Storm DT-4' => [
                'ASTON',
                'Печь для бани ASTON «Шторм 16» (ДТ-4)',
                ['volume' => 16.0, 'door' => false, 'remote_firebox' => true],
            ],
            'ASTON 20 glass' => [
                'ASTON',
                'Печь для бани ASTON 20 INOX стекло',
                ['volume' => 22.0, 'door' => true, 'remote_firebox' => true],
            ],
            'TMF Sayany Mini' => [
                'Термофор',
                'ПБ Саяны Мини Carbon ДА',
                ['volume' => 9.0, 'door' => false, 'remote_firebox' => true],
            ],
            'NMK Siberia 24 panorama' => [
                'НМК',
                'Печь банная чугунная «Сибирь-24». Панорамная дверца',
                ['volume' => 24.0, 'door' => true, 'remote_firebox' => true],
            ],
            'Ermak 24' => [
                'Ермак',
                'Ермак ERMAK 24 Сетка - Премиум Чугун',
                ['volume' => 26.0, 'door' => null, 'remote_firebox' => true],
            ],
            'same number from unknown brand is ignored' => [
                'Другой',
                'Печь Медведь 30',
                ['volume' => null, 'door' => null, 'remote_firebox' => null],
            ],
        ];
    }
}
