<?php

namespace Tests\Unit;

use App\Services\StoveOfficialTechnicalSpecCatalog;
use Tests\TestCase;

class StoveOfficialTechnicalSpecCatalogTest extends TestCase
{
    public function test_catalog_contains_only_explicit_manufacturer_technical_facts(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();

        $this->assertCount(24, $entries);

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
}
