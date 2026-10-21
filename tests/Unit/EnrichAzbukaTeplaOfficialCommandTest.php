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
            ['Valfex', 'VCP 25-60G 130', 'https://www.teplodvor.ru/valfex-vcp-25-60g-130.html'],
            ['Valfex', 'VCP 25-40G 180', 'https://www.teplodvor.ru/valfex-vcp-25-40g.html'],
            ['Valfex', '10305016-200', 'https://valfex.ru/catalog/truby-metalloplastikovye/truba-metalloplastikovaya-pe-xb-al-pe-xb-2/'],
            ['SALUS', 'RT310', 'https://saluscontrols.com/global/product/rt310-wired-non-programmable-thermostat/'],
            ['SALUS', 'ERT20RF', 'https://saluscontrols.com/global/product/ert20tx-wireless-electronic-surface-mounted-thermostat-non-programmable/'],
            ['Barberi', '46002000MB', 'https://www.barberi.it/ww/ru/produktsiya/b2-rotary-mixing-valves-and-actuators/3-way-mixing-valves/460'],
            ['Barberi', 'V07M250 AA L2', 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/thermostatic-mixing-valves-l-comfort/V07AAL2'],
            ['Barberi', '27B040N4P3', 'https://www.barberi.it/ww/ru/produktsiya/b3-regulating-groups-and-heating-components/recessed-regulating-groups/recessed-regulating-groups-bdhb/27BN'],
            ['Barberi', 'V13M2500 A L2', 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V13'],
            ['Barberi', 'V14M3200 B', 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V14'],
            ['Barberi', 'V13025005 BR', 'https://www.barberi.it/ww/ru/produktsiya/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V135R'],
            ['Barberi', 'V14025005 AR', 'https://www.barberi.it/ww/ru/produktsiya/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V145R'],
            ['Vaillant', 'VRC 720', 'https://www.vaillant.com/en/products/sensocomfort/'],
            ['Vaillant', 'VU 656/5-5', 'https://www.vaillant.com/en/products/ecotec-plus-vc--5-5/'],
            ['Vaillant', 'VR 92', 'https://professional.vaillant.co.uk/for-installers/products/sensocomfort-sensocomfort-rf-149888.html'],
            ['Ariston', 'FAST R Displey 14', 'https://www.teplodvor.ru/ariston-fast-r-display-14l-ng.html'],
            ['Ariston', 'CLAS XC 35 FF NG', 'https://www.ariston.com/en-eg/products/boilers-eg/sottocat-boiler-eg/clas-xc/'],
            ['Fondital', 'MINORKA CTFS 24', 'https://www.fondital.com/ww/en/minorca-ctfs-cu'],
            ['Protherm', 'Lynx 24', 'https://www.teplodvor.ru/protherm-rys-hk24.html'],
            ['SANHA', '12305016 MultiFit-Flex 16x2 200m', 'https://www.sanha.com/en/MultiFit-Flex-pipe-furled/12300016'],
            ['Maincor', '4160021500 16x2 500m', 'https://www.wolf-online-shop.de/Maincor-MFL-PE-RT-AL-PE-RT-16x2-0-500m-Ring-rot%3A%3A57719.html'],
            ['AFRISO', '4261600', 'https://afriso.pl/katalog-produktow-afriso/i-2-03-b-przewodowy-system-sterowania-ogrzewaniem-plaszczyznowym-cositherm-basic/4261600-termostat-pokojowy-ta3'],
            ['ИПРО', 'КотелОК', 'https://ipro-gsm.ru/internet-magazin-2/product/kotelok'],
            ['TECLine', 'Пленка TECline', 'https://azbukatepla.by/shop/truby-i-armatura/pe-rt-polietilen/plenka-folga-dlya-teplogo-pola-tecline'],
            ['Kospel', 'SWK-120 TERMO TOP', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/swk-termo-top.html'],
            ['Kospel', 'SW-251 TERMO MAX', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/sw-i-swz-termo-max.html'],
            ['Kospel', 'WP Thermo Hit 140', 'https://kospel.pl/wymienniki-i-zasobniki-cwu/wpw.html'],
            ['TESY', '301395', 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-15s-500-75'],
        ];
    }

    public function test_it_uses_verified_direct_images_when_gallery_parsing_is_incomplete(): void
    {
        $command = new EnrichAzbukaTeplaOfficialCommand();
        $method = new ReflectionMethod($command, 'officialImages');

        $this->assertSame(
            ['https://valfex.ru/upload/iblock/e03/tsirkulyatsionnyy_energoeffektivnyy_nasos_valfex_alpha_25_60.jpg'],
            $method->invoke($command, 'Valfex', 'ALPHA 25-60 180', '')
        );
        $this->assertSame(
            ['https://saluscontrols.com/global/wp-content/uploads/sites/3/2024/03/RT310_002.png'],
            $method->invoke($command, 'SALUS', 'RT310', '')
        );
    }
}
