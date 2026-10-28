<?php

namespace Tests\Unit;

use App\Services\Suppliers\SantehskladCatalogContent;
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
        $this->assertSame('pellet_universal_kipi_bundle_20', $parser->productIdentity('Пеллетный котел Sakovich Pellet Universal 20 с горелкой KIPI Rotary и бункером'));
        $this->assertSame('promka_8_no_glass', $parser->productIdentity('Печь Sakovich Promka 8 кВт без стекла'));
        $this->assertSame('kipi_rotary_4_16', $parser->productIdentity('Пеллетная горелка KIPI Rotary 4–16 кВт'));

        $legacyKipi = collect($payload['rows'])->firstWhere('identity', 'accessory_22c200b9e5e180b28724');
        $this->assertSame('kipi_rotary_4_16', $parser->rowMatchIdentity($legacyKipi));
        $this->assertStringContainsString('KIPI Rotary 5-16 кВт', $legacyKipi['name']);
        $this->assertStringContainsString('KIPI Rotary 4–16 кВт', $parser->catalogName($legacyKipi));
    }

    public function test_content_sources_cover_the_previously_empty_product_families(): void
    {
        $parser = new SantehskladPriceList;
        $payload = $parser->load(dirname(__DIR__, 2).'/resources/data/suppliers/santehsklad-sakovich-2026-09-17.json');
        $content = new SantehskladCatalogContent;
        $source = (new \ReflectionClass($content))->getMethod('source');

        foreach ([
            'max_300',
            'pellet_eco_70',
            'pellet_standart_4mm_16',
            'accessory_5c3208ca6d03a1cc138b',
            'accessory_2765461f5b68c92c9365',
            'accessory_401ce62ce6dd9ab47735',
            'accessory_50a8b50657694630e752',
            'accessory_847fd749aaef346fa509',
            'accessory_fd1ca7cffec6c00d726f',
            'accessory_26a5c502c230c6187412',
        ] as $identity) {
            $row = collect($payload['rows'])->firstWhere('identity', $identity);

            $this->assertNotNull($row, "Missing fixture row {$identity}");
            $this->assertNotEmpty($source->invoke($content, $row)['image'], "Missing source image for {$identity}");
        }
    }
}
