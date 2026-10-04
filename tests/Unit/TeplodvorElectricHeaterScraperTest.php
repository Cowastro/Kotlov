<?php

namespace Tests\Unit;

use App\Services\TeplodvorElectricHeaterScraper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeplodvorElectricHeaterScraperTest extends TestCase
{
    #[Test]
    public function it_parses_an_in_stock_product_without_confusing_internal_codes_with_price(): void
    {
        $html = <<<'HTML'
            <h1>Электрическая печь KARINA Nova 10E</h1>
            <div class="status yes">Есть в наличии</div>
            <div data-kod="1015136" itemprop="offers"><span class="js_shop_price">1942</span>
            <meta itemprop="price" content="1942"><meta itemprop="priceCurrency" content="BYN"></div>
            <table><tr><td>Мощность</td><td>10 кВт</td></tr><tr><td>Объём парной</td><td>8–14 м³</td></tr></table>
            <img src="/userfls/shop/large/nova-10e.jpg">
        HTML;

        $card = app(TeplodvorElectricHeaterScraper::class)->parse(
            $html,
            'https://www.teplodvor.by/shop/elektricheskie-pechi/elektricheskaya-pech-karina-nova-10e/',
        );

        $this->assertSame('in_stock', $card['status']);
        $this->assertSame(1942.0, $card['price']);
        $this->assertSame('KARINA', $card['brand']);
        $this->assertSame('10 кВт', $card['specs']['Мощность']);
        $this->assertSame('https://www.teplodvor.by/userfls/shop/large/nova-10e.jpg', $card['images'][0]);
    }

    #[Test]
    public function it_keeps_discontinued_products_out_of_the_sync(): void
    {
        $html = <<<'HTML'
            <h1>Электрическая печь Harvia Cilindro PC70</h1>
            <div class="outofstock">Снят с производства</div>
            <strong itemprop="price">1618.57</strong>
        HTML;

        $card = app(TeplodvorElectricHeaterScraper::class)->parse(
            $html,
            'https://www.teplodvor.by/shop/elektricheskie-pechi/elektricheskaya-pech-harvia-cilindro-pc70/',
        );

        $this->assertSame('discontinued', $card['status']);
        $this->assertSame(1618.57, $card['price']);
    }

    #[Test]
    public function it_builds_a_stable_exact_model_key(): void
    {
        $scraper = app(TeplodvorElectricHeaterScraper::class);

        $this->assertSame('nova10e', $scraper->canonicalModel('Электрическая печь KARINA Nova 10E', 'KARINA'));
        $this->assertSame('nova10e', $scraper->canonicalModel('Электрокаменка KARINA Nova 10E', 'KARINA'));
        $this->assertNotSame('nova10e', $scraper->canonicalModel('Электрическая печь KARINA Nova 12E', 'KARINA'));
    }
}
