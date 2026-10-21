<?php

namespace Tests\Unit;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use PHPUnit\Framework\TestCase;

class ScrapesAqualiderCardTest extends TestCase
{
    public function test_it_parses_the_current_aqualider_product_page(): void
    {
        $parser = new class {
            use ScrapesAqualiderCard;

            public function parse(string $html): array
            {
                return $this->parseCard($html, 'https://aqualider.by/p/gidroakkumulyator-100l-36580');
            }
        };

        $schema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Гидроаккумулятор 100л UNIPUMP',
            'sku' => '36580',
            'brand' => ['@type' => 'Brand', 'name' => 'UNIPUMP'],
            'description' => 'Гидроаккумулятор для водоснабжения.',
            'image' => [
                'https://aqualider.by/static/products/tank-1-md.webp',
                'https://aqualider.by/static/products/tank-2-md.webp',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => '690.63',
                'priceCurrency' => 'BYN',
                'availability' => 'https://schema.org/InStock',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
        <html><head>
        <script type="application/ld+json">{$schema}</script>
        </head><body>
        <nav class="aq-crumbs"><a>Главная</a><a>Мембранные баки для воды</a><a>Гидроаккумуляторы</a></nav>
        <dl>
          <dt>Бренд</dt><dd><a title="UNIPUMP"><img alt="UNIPUMP"></a></dd>
          <dt>Присоединительный размер</dt><dd>1&quot;</dd>
          <dt>Максимальное давление в системе, бар</dt><dd>6</dd>
        </dl>
        <div class="aq-gallery__slide"><img src="https://aqualider.by/static/products/tank-1-md.webp"></div>
        </body></html>
        HTML;

        $result = $parser->parse($html);

        self::assertSame('Гидроаккумулятор 100л UNIPUMP', $result['name']);
        self::assertSame('UNIPUMP', $result['brand']);
        self::assertSame('36580', $result['article']);
        self::assertSame(690.63, $result['price']);
        self::assertTrue($result['inStock']);
        self::assertSame('in_stock', $result['stockStatus']);
        self::assertSame('1"', $result['specs']['Присоединительный размер']);
        self::assertSame('6', $result['specs']['Максимальное давление в системе, бар']);
        self::assertCount(2, $result['images']);
        self::assertStringContainsString('Гидроаккумуляторы', $result['breadcrumb']);
    }
}
