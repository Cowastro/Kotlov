<?php

namespace Tests\Unit;

use App\Console\Commands\EnrichAzbukaTeplaOfficialCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class EnrichAzbukaTeplaOfficialCommandTest extends TestCase
{
    #[DataProvider('officialSources')]
    public function test_it_maps_supplier_models_to_verified_official_pages(
        string $brand,
        string $model,
        string $expected
    ): void {
        $command = new EnrichAzbukaTeplaOfficialCommand();
        $method = new ReflectionMethod($command, 'officialSource');

        $this->assertSame($expected, $method->invoke($command, $brand, $model, ''));
    }

    public static function officialSources(): array
    {
        return [
            ['Auraton', 'Aquila SET черный', 'https://www.auraton.pl/en/offer/thermostats/auraton-aquila-set'],
            ['Valfex', 'ALPHA 25-40 180', 'https://valfex.ru/catalog/seriya-alpha/tsirkulyatsionnyy-energoeffektivnyy-nasos-valfex-alpha-25-40/'],
            ['SALUS', 'RT310', 'https://saluscontrols.com/global/product/rt310-wired-non-programmable-thermostat/'],
            ['Barberi', '46002000MB', 'https://www.barberi.it/ww/ru/produktsiya/b2-rotary-mixing-valves-and-actuators/3-way-mixing-valves/460'],
            ['Barberi', 'V07M250 AA L2', 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/thermostatic-mixing-valves-l-comfort/V07AAL2'],
            ['Barberi', '27B040N4P3', 'https://www.barberi.it/ww/ru/produktsiya/b3-regulating-groups-and-heating-components/recessed-regulating-groups/recessed-regulating-groups-bdhb/27BN'],
            ['Kospel', 'SWK-120 TERMO TOP', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/swk-termo-top.html'],
            ['Kospel', 'SW-251 TERMO MAX', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/sw-i-swz-termo-max.html'],
            ['Kospel', 'WP Thermo Hit 140', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/wpw.html'],
            ['TESY', '301395', 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-15s-500-75'],
        ];
    }
}
