<?php

namespace Tests\Unit;

use App\Services\Suppliers\DomKotlovVenmaScraper;
use PHPUnit\Framework\TestCase;

class DomKotlovVenmaScraperTest extends TestCase
{
    public function test_it_parses_exact_venma_card_and_applies_partner_discount(): void
    {
        $html = <<<'HTML'
<h1>Пеллетная горелка Venma Comfort 16</h1>
<span itemprop=image><img itemprop='url contentUrl image' src='/images/product/246/246890/main.jpg'></span>
<span itemprop=image><img src='/images/image/005/extra.png'></span>
<div itemprop=description>Автоматическая факельная пеллетная горелка. Мощность 16 кВт.</div>
<link itemprop=availability href='https://schema.org/InStock'>
<meta itemprop=price content="6090">
<meta itemprop=mpn content="246890">
<meta itemprop=sku content="pelletnaya-gorelka-venma-comfort-16">
<p><span>Технические характеристики:</span></p>
<table><tr><td><p>Мощность, кВт:</p></td><td><p>4 - 16</p></td></tr></table>
HTML;

        $card = (new DomKotlovVenmaScraper())->parseProductHtml($html, 'https://domkotlov.by/test');

        $this->assertNotNull($card);
        $this->assertSame('16', $card['model']);
        $this->assertSame('VENMA-246890', $card['article']);
        $this->assertSame(6090.0, $card['retail_price']);
        $this->assertSame(5176.5, $card['purchase_price']);
        $this->assertTrue($card['in_stock']);
        $this->assertSame(['Мощность, кВт' => '4 - 16'], $card['specs']);
        $this->assertCount(2, $card['images']);
    }

    public function test_it_uses_the_product_name_instead_of_a_stale_url_model(): void
    {
        $html = <<<'HTML'
<h1>Пеллетная горелка Venma Comfort 25</h1>
<div itemprop=description>VENMA Comfort 25.</div>
<link itemprop=availability href='https://schema.org/OutOfStock'>
<meta itemprop=price content="6909">
HTML;

        $card = (new DomKotlovVenmaScraper())->parseProductHtml(
            $html,
            'https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-10',
        );

        $this->assertSame('25', $card['model']);
        $this->assertSame('VENMA-25', $card['article']);
        $this->assertFalse($card['in_stock']);
    }

    public function test_it_accepts_a_supplier_title_with_a_power_suffix(): void
    {
        $html = <<<'HTML'
<h1>Пеллетная горелка Venma Comfort R58 (40–58 кВт)</h1>
<link itemprop=availability href='https://schema.org/InStock'>
<meta itemprop=price content="9975">
HTML;

        $card = (new DomKotlovVenmaScraper())->parseProductHtml($html, 'https://domkotlov.by/r58');

        $this->assertNotNull($card);
        $this->assertSame('R58', $card['model']);
        $this->assertSame(9975.0, $card['retail_price']);
    }

    public function test_it_reads_the_visible_supplier_price_when_microdata_is_missing(): void
    {
        $html = <<<'HTML'
<h1>Пеллетная горелка Venma Comfort R58</h1>
<b>Цена: <i>9975</i> руб.</b>
<link itemprop=availability href='https://schema.org/InStock'>
HTML;

        $card = (new DomKotlovVenmaScraper())->parseProductHtml($html, 'https://domkotlov.by/r58');

        $this->assertNotNull($card);
        $this->assertSame(9975.0, $card['retail_price']);
        $this->assertSame(8478.75, $card['purchase_price']);
    }

    public function test_warehouse_stock_is_limited_to_models_up_to_100_kw(): void
    {
        $scraper = new DomKotlovVenmaScraper();
        $base = ['in_stock' => true, 'stock_text' => 'В наличии на сайте поставщика'];

        $r100 = $scraper->applyWarehouseAvailabilityPolicy($base + ['model' => 'R100']);
        $r125 = $scraper->applyWarehouseAvailabilityPolicy($base + ['model' => 'R125']);

        $this->assertTrue($r100['in_stock']);
        $this->assertSame('available_without_quantity', $r100['stock_status']);
        $this->assertFalse($r125['in_stock']);
        $this->assertSame('preorder', $r125['stock_status']);
        $this->assertSame('Под заказ у поставщика', $r125['stock_text']);
        $this->assertTrue($r125['source_in_stock']);
    }

    public function test_listing_keeps_only_unique_venma_product_links(): void
    {
        $html = <<<'HTML'
<a href="/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-16">one</a>
<a href="/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-16">duplicate</a>
<a href="/katalog/pelletnye-gorelki/zapasnaya-chast-venma">accessory</a>
HTML;

        $urls = (new DomKotlovVenmaScraper())->parseListingHtml($html);

        $this->assertSame([
            'https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-16',
        ], $urls);
    }

    public function test_sitemap_can_be_used_when_the_category_page_is_regionally_empty(): void
    {
        $xml = <<<'XML'
<urlset>
  <url><loc>https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-16</loc></url>
  <url><loc>https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-r100</loc></url>
  <url><loc>https://domkotlov.by/katalog/pelletnye-gorelki/zapasnaya-chast-venma</loc></url>
</urlset>
XML;

        $urls = (new DomKotlovVenmaScraper())->parseSitemapXml($xml);

        $this->assertSame([
            'https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-16',
            'https://domkotlov.by/katalog/pelletnye-gorelki/pelletnaya-gorelka-venma-comfort-r100',
        ], $urls);
    }
}
