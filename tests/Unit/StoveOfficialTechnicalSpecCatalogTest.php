<?php

namespace Tests\Unit;

use App\Services\StoveOfficialTechnicalSpecCatalog;
use Tests\TestCase;

class StoveOfficialTechnicalSpecCatalogTest extends TestCase
{
    public function test_catalog_contains_only_explicit_manufacturer_technical_facts(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();

        $this->assertCount(14, $entries);

        foreach ($entries as $entry) {
            $keys = collect($entry['specs'])->pluck('key');

            $this->assertTrue($keys->contains('Мощность'));
            $this->assertTrue($keys->contains('Габариты (Ш×Г×В)'));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'площад')));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'объём помещения')));
        }

        $blistEntries = collect($entries)->except('ferguss-pec-ferguss-l-8606107095288-lawa-cook-ucenka');
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
        $this->assertFalse($specs->keys()->contains(fn (string $key) => str_contains(mb_strtolower($key), 'площад')));
    }
}
