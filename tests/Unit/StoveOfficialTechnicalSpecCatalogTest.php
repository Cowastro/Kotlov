<?php

namespace Tests\Unit;

use App\Services\StoveOfficialTechnicalSpecCatalog;
use Tests\TestCase;

class StoveOfficialTechnicalSpecCatalogTest extends TestCase
{
    public function test_catalog_contains_only_explicit_blist_technical_facts(): void
    {
        $entries = (new StoveOfficialTechnicalSpecCatalog)->entries();

        $this->assertCount(13, $entries);

        foreach ($entries as $entry) {
            $this->assertStringStartsWith('https://blist.co.rs/', $entry['source_url']);
            $keys = collect($entry['specs'])->pluck('key');

            $this->assertTrue($keys->contains('Мощность'));
            $this->assertTrue($keys->contains('Габариты (Ш×Г×В)'));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'площад')));
            $this->assertFalse($keys->contains(fn (string $key) => str_contains(mb_strtolower($key), 'объём помещения')));
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
}
