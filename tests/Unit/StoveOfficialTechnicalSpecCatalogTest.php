<?php

namespace Tests\Unit;

use App\Services\StoveOfficialTechnicalSpecCatalog;
use Tests\TestCase;

class StoveOfficialTechnicalSpecCatalogTest extends TestCase
{
    public function test_catalog_contains_only_explicit_manufacturer_technical_facts(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();

        $this->assertCount(31, $entries);

        foreach ($entries as $entry) {
            $keys = collect($entry['specs'])->pluck('key');

            $this->assertTrue($keys->contains('Габариты (Ш×Г×В)'));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'площад')));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'объём помещения')));
        }

        $blistEntries = collect($entries)->filter(
            fn (array $entry): bool => str_starts_with($entry['source_url'], 'https://blist.co.rs/')
        );
        foreach ($blistEntries as $entry) {
            $this->assertStringStartsWith('https://blist.co.rs/', $entry['source_url']);
        }
    }

    public function test_roma_e_keeps_water_circuit_facts_separate_from_total_power(): void
    {
        $entry = (new StoveOfficialTechnicalSpecCatalog)->entries()['blist-pec-roma-e-bezevaia'];
        $specs = collect($entry['specs'])->keyBy('key');

        $this->assertSame('20–22', $specs['Мощность']['value']);
        $this->assertSame('15–17', $specs['Мощность водяного контура']['value']);
        $this->assertSame('22', $specs['Объём водяного контура']['value']);
    }

    public function test_ferguss_l_uses_the_exact_ean_catalog_entry_without_inferred_area(): void
    {
        $entry = (new StoveOfficialTechnicalSpecCatalog)->entries()['ferguss-pec-ferguss-l-8606107095288-lawa-cook-ucenka'];
        $specs = collect($entry['specs'])->keyBy('key');

        $this->assertStringContainsString('ferguss-katalog-2018.pdf', $entry['source_url']);
        $this->assertStringContainsString('8606107095288', $entry['source_label']);
        $this->assertSame('12,8', $specs['Мощность']['value']);
        $this->assertSame('168', $specs['Масса']['value']);
        $this->assertSame('74', $specs['КПД']['value']);
        $this->assertSame('120', $specs['Диаметр дымохода']['value']);
        $this->assertSame('Печь Ferguss L Ornament Cook (8606107095288) (УЦЕНКА)', $entry['product_name']);
        $this->assertSame('Печь Ferguss L Ornament Cook с варочной плитой', $entry['h1']);
        $this->assertStringContainsString('Ferguss L Ornament Cook', $entry['meta_title']);
        $this->assertStringContainsString('12,8 кВт', $entry['meta_description']);
        $this->assertStringNotContainsString('Lawa', $entry['product_name']);
        $this->assertStringContainsString('Площадь отопления производителем для этой модели не заявлена', $entry['content']);
        $this->assertFalse($specs->keys()->contains(fn (string $key) => str_contains(mb_strtolower($key), 'площад')));
    }

    public function test_fireway_models_use_only_their_official_product_pages_without_inferred_area(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();
        $konnekta = $entries['pech-kamin-fireway-konnecta'];
        $skif = $entries['otopitelno-varochnaya-pech-fireway-skif'];
        $konnektaSpecs = collect($konnekta['specs'])->keyBy('key');
        $skifSpecs = collect($skif['specs'])->keyBy('key');

        $this->assertSame('https://fireway.pro/pech-chugunnaya-konnekta.html', $konnekta['source_url']);
        $this->assertSame('590×473×882', $konnektaSpecs['Габариты (Ш×Г×В)']['value']);
        $this->assertSame('10', $konnektaSpecs['Мощность']['value']);
        $this->assertSame('Чугун', $konnektaSpecs['Материал корпуса']['value']);
        $this->assertSame('150', $konnektaSpecs['Диаметр дымохода']['value']);

        $this->assertSame('https://fireway.pro/pech-kamin-skif.html', $skif['source_url']);
        $this->assertSame('950×600×863', $skifSpecs['Габариты (Ш×Г×В)']['value']);
        $this->assertSame('8', $skifSpecs['Мощность']['value']);
        $this->assertSame('Есть', $skifSpecs['Варочная поверхность']['value']);
        $this->assertSame('Есть', $skifSpecs['Духовой шкаф']['value']);

        foreach ([$konnektaSpecs, $skifSpecs] as $specs) {
            $this->assertFalse($specs->keys()->contains(
                fn (string $key) => str_contains(mb_strtolower($key), 'площад')
                    || str_contains(mb_strtolower($key), 'объём помещения')
            ));
        }
    }

    public function test_ecokamin_models_use_exact_official_cards_without_inferred_heating_area(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();
        $slugs = [
            'otopitelnaya-pech-ecokamin-ogonek',
            'kamin-panorama-tri-stekla-grafit',
            'kamin-panorama-tri-stekla-chernyiy',
            'kamin-praga-tri-stekla-new-chernyiy',
            'kamin-praga-tri-stekla-new-cernyi-samot-cernyi',
            'kamin-madrid-na-drovnike-podovyi',
            'kamin-madrid-na-drovnike-gigant-centralnyi-cernyi-samot-podovyi',
            'kamin-madrid-na-drovnike-gigant-sleva-cernyi-samot-podovyi',
        ];

        foreach ($slugs as $slug) {
            $entry = $entries[$slug];
            $keys = collect($entry['specs'])->pluck('key');

            $this->assertStringContainsString('ecokamin.ru/catalog/', $entry['source_url']);
            $this->assertFalse($keys->contains(
                fn (string $key) => str_contains(mb_strtolower($key), 'площад')
                    || str_contains(mb_strtolower($key), 'объём помещения')
            ));
        }

        $ogonek = collect($entries['otopitelnaya-pech-ecokamin-ogonek']['specs'])->keyBy('key');
        $this->assertSame('300×477×421', $ogonek['Габариты (Ш×Г×В)']['value']);
        $this->assertSame('23', $ogonek['Масса']['value']);
        $this->assertFalse($ogonek->has('Мощность'));

        $praga = collect($entries['kamin-praga-tri-stekla-new-chernyiy']['specs'])->keyBy('key');
        $this->assertSame('14', $praga['Мощность']['value']);
        $this->assertSame('223', $praga['Масса']['value']);

        $madrid = collect($entries['kamin-madrid-na-drovnike-podovyi']['specs'])->keyBy('key');
        $this->assertSame('8', $madrid['Мощность']['value']);
        $this->assertSame('187', $madrid['Масса']['value']);
        $this->assertFalse($madrid->has('Материал корпуса'));
    }

    public function test_metabel_models_use_the_official_catalog_without_inferred_heating_area(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();
        $expected = [
            'pech-otopitelnaya-meta-bel-yamal' => ['358×546×456', null, '115', '37', 'Верхнее'],
            'pech-kamin-meta-bel-rona-aot-60' => ['480×437×1188', '6', '150', '110', 'Верхнее'],
            'pec-kamin-meta-bel-narva-7m' => ['466×481×855', '7', '150', '93', 'Заднее'],
            'pec-kamin-meta-bel-svitiaz-nr' => ['700×400×1076', '7', '150', '120', 'Верхнее'],
        ];

        foreach ($expected as $slug => [$dimensions, $power, $diameter, $weight, $connection]) {
            $entry = $entries[$slug];
            $specs = collect($entry['specs'])->keyBy('key');

            $this->assertSame('https://metabel.by/images/produktsiya-meta-bel.pdf', $entry['source_url']);
            $this->assertSame($dimensions, $specs['Габариты (Ш×Г×В)']['value']);
            $this->assertSame($diameter, $specs['Диаметр дымохода']['value']);
            $this->assertSame($weight, $specs['Масса']['value']);
            $this->assertSame($connection, $specs['Подключение дымохода']['value']);
            $this->assertSame($power, $specs->get('Мощность')['value'] ?? null);
            $this->assertFalse($specs->keys()->contains(
                fn (string $key) => str_contains(mb_strtolower($key), 'площад')
                    || str_contains(mb_strtolower($key), 'объём помещения')
            ));
        }

        $this->assertSame('Есть', collect($entries['pech-otopitelnaya-meta-bel-yamal']['specs'])->keyBy('key')['Варочная поверхность']['value']);
        $this->assertSame('Есть', collect($entries['pec-kamin-meta-bel-narva-7m']['specs'])->keyBy('key')['Варочная поверхность']['value']);
    }

    public function test_hidden_current_metabel_cards_supply_exact_specs_for_lava_moscow_and_montblanc(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();
        $expected = [
            'pec-kamin-meta-bel-lava' => [
                'url' => 'https://metabel.by/produktsiya/pechi-kaminy/pech-kamin-lava-aot-6-0',
                'dimensions' => '450×400×900',
                'power' => '6',
                'weight' => '95',
            ],
            'pec-kamin-meta-bel-moskva-9' => [
                'url' => 'https://metabel.by/produktsiya/pechi-kaminy/pech-kamin-moskva-9-aot-9-0-01',
                'dimensions' => '554×540×1048',
                'power' => '9',
                'weight' => '150',
            ],
            'pec-kamin-meta-bel-monblan-700' => [
                'url' => 'https://metabel.by/produktsiya/pechi-kaminy/pech-kamin-monblan-700-aot-10-0',
                'dimensions' => '690×570×906',
                'power' => '10',
                'weight' => '263',
            ],
        ];

        foreach ($expected as $slug => $expectedEntry) {
            $entry = $entries[$slug];
            $specs = collect($entry['specs'])->keyBy('key');

            $this->assertSame($expectedEntry['url'], $entry['source_url']);
            $this->assertSame($expectedEntry['dimensions'], $specs['Габариты (Ш×Г×В)']['value']);
            $this->assertSame($expectedEntry['power'], $specs['Мощность']['value']);
            $this->assertSame($expectedEntry['weight'], $specs['Масса']['value']);
            $this->assertSame('150', $specs['Диаметр дымохода']['value']);
            $this->assertSame('Сталь', $specs['Материал корпуса']['value']);
            $this->assertSame('не менее 75', $specs['КПД']['value']);
            $this->assertSame('36', $specs['Гарантия']['value']);
            $this->assertFalse($specs->keys()->contains(
                fn (string $key) => str_contains(mb_strtolower($key), 'площад')
                    || str_contains(mb_strtolower($key), 'объём помещения')
            ));
        }

        $montblanc = collect($entries['pec-kamin-meta-bel-monblan-700']['specs'])->keyBy('key');
        $this->assertSame('до 6', $montblanc['Толщина стали']['value']);
        $this->assertSame('Да', $montblanc['Система «регулируемого вторичного дожига»']['value']);
        $this->assertSame('Да', $montblanc['Подача воздуха извне']['value']);
    }
}
