<?php

namespace Tests\Unit;

use App\Services\Suppliers\EurotermPriceList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EurotermPriceListTest extends TestCase
{
    #[Test]
    public function it_reads_the_complete_normalized_supplier_snapshot(): void
    {
        $payload = app(EurotermPriceList::class)->load(
            base_path('resources/data/suppliers/euroterm-2026-10-20.json')
        );

        $this->assertSame('2026-10-20', $payload['source_date']);
        $this->assertCount(121, $payload['rows']);
        $this->assertSame(3557, (int) collect($payload['rows'])->sum('stock_quantity'));

        $boiler = collect($payload['rows'])->firstWhere('article_key', 'deluxeone24k');
        $this->assertSame(1950.0, (float) $boiler['wholesale']);
        $this->assertSame(2300.0, (float) $boiler['retail']);
        $this->assertSame(4, $boiler['stock_quantity']);
        $this->assertSame('Navien', $boiler['brand']);
        $this->assertStringContainsString('gazovye-kotly', $boiler['source_url']);

        $merged = collect($payload['rows'])->firstWhere('article_key', 'ts075mst');
        $this->assertSame(8, $merged['stock_quantity']);
        $this->assertSame([126, 127], $merged['source_rows']);
    }

    #[Test]
    public function it_normalizes_visually_identical_latin_and_cyrillic_articles(): void
    {
        $parser = app(EurotermPriceList::class);

        $this->assertSame($parser->articleKey('K-01B'), $parser->articleKey('К-01В'));
        $this->assertSame('jkd808', $parser->articleKey('JKD 808'));
    }
}
