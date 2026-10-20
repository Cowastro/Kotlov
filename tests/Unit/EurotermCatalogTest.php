<?php

namespace Tests\Unit;

use App\Services\Suppliers\EurotermCatalog;
use Tests\TestCase;

class EurotermCatalogTest extends TestCase
{
    public function test_it_parses_product_cards_and_hidden_pagination(): void
    {
        $html = <<<'HTML'
<div class="product-item">
  <a class="vm-product-media" title="Газовый котёл Navien Deluxe ONE 24K" href="/otopitelnoe-oborudovanie/gazovye-kotly/navien-deluxe-one-24k.html">
    <img data-src="/images/virtuemart/product/resized/navien_0x200.jpg">
  </a>
</div>
<div class="vm-pagination"><ul class="pagination">
  <li><a href="/otopitelnoe-oborudovanie/gazovye-kotly/results25-48.html">2</a></li>
</ul></div>
HTML;

        $parsed = app(EurotermCatalog::class)->parseListingHtml(
            $html,
            'https://euroterm.by/otopitelnoe-oborudovanie/gazovye-kotly.html',
        );

        self::assertSame('Газовый котёл Navien Deluxe ONE 24K', $parsed['cards'][0]['title']);
        self::assertSame(
            'https://euroterm.by/otopitelnoe-oborudovanie/gazovye-kotly/navien-deluxe-one-24k.html',
            $parsed['cards'][0]['url'],
        );
        self::assertSame(
            'https://euroterm.by/otopitelnoe-oborudovanie/gazovye-kotly/results25-48.html',
            $parsed['pages'][0],
        );
    }

    public function test_it_matches_an_offer_to_the_official_card_by_model(): void
    {
        $catalog = app(EurotermCatalog::class);
        $match = $catalog->match([
            'article' => 'Deluxe ONE 24K',
            'name' => 'NAVIEN Deluxe ONE 24K, Котел газовый настенный одноконтурный',
        ], [
            [
                'title' => 'Газовый котёл Navien Deluxe ONE 24K',
                'url' => 'https://euroterm.by/navien-deluxe-one-24k.html',
                'image' => 'https://euroterm.by/navien.jpg',
            ],
            [
                'title' => 'Газовый котёл Navien Deluxe ONE 30K',
                'url' => 'https://euroterm.by/navien-deluxe-one-30k.html',
                'image' => 'https://euroterm.by/navien-30.jpg',
            ],
        ]);

        self::assertSame('https://euroterm.by/navien-deluxe-one-24k.html', $match['card']['url']);
        self::assertSame(1.0, $match['score']);
    }

    public function test_collectors_use_their_real_catalog_section(): void
    {
        self::assertSame(
            'https://euroterm.by/komplektuyushchie-dlya-sistem-otopleniya/kollektory-raspredelitelnye.html',
            app(EurotermCatalog::class)->categoryUrl([
                'category' => 'Распределительные коллекторы',
                'source_url' => 'https://euroterm.by/',
            ]),
        );
    }
}
