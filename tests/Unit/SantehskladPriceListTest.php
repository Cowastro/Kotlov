<?php

namespace Tests\Unit;

use App\Services\Suppliers\SantehskladPriceList;
use PHPUnit\Framework\TestCase;

class SantehskladPriceListTest extends TestCase
{
    public function test_snapshot_keeps_wholesale_and_rrp_separate_and_has_stable_identities(): void
    {
        $parser = new SantehskladPriceList;
        $payload = $parser->load(dirname(__DIR__, 2).'/resources/data/suppliers/santehsklad-sakovich-2026-09-17.json');

        $this->assertCount(103, $payload['rows']);
        $this->assertSame(40, collect($payload['rows'])->where('source_sheet', 'DROVA')->count());
        $this->assertSame(55, collect($payload['rows'])->where('source_sheet', 'PELLET')->count());
        $this->assertSame(8, collect($payload['rows'])->where('source_sheet', 'ПЕЧИ')->count());

        $standart = collect($payload['rows'])->firstWhere('identity', 'standart_12');
        $this->assertSame(2250, $standart['retail']);
        $this->assertSame(1732.5, $standart['wholesale']);
        $this->assertEquals(23.0, $standart['margin_percent']);

        $this->assertSame('standart_12', $parser->productIdentity('Твердотопливный котел Sakovich Standart 12 кВт (сталь 4 мм)'));
        $this->assertSame('standart_pro_14', $parser->productIdentity('Котел Сакович Standart Pro 14'));
        $this->assertSame('pellet_standart_praktik_15', $parser->productIdentity('Пеллетный котел Sakovich Pellet Standart 15 с горелкой Praktik и бункером'));
        $this->assertSame('promka_8_no_glass', $parser->productIdentity('Печь Sakovich Promka 8 кВт без стекла'));
        $this->assertSame('kipi_rotary_4_16', $parser->productIdentity('Пеллетная горелка KIPI Rotary 4–16 кВт'));

        $legacyKipi = collect($payload['rows'])->firstWhere('identity', 'accessory_22c200b9e5e180b28724');
        $this->assertSame('kipi_rotary_4_16', $parser->rowMatchIdentity($legacyKipi));
        $this->assertStringContainsString('KIPI Rotary 5-16 кВт', $legacyKipi['name']);
        $this->assertStringContainsString('KIPI Rotary 4–16 кВт', $parser->catalogName($legacyKipi));
    }
}
