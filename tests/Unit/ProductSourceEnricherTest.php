<?php

namespace Tests\Unit;

use App\Services\ProductSourceEnricher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductSourceEnricherTest extends TestCase
{
    public function test_it_extracts_tenko_official_characteristics(): void
    {
        $html = <<<'HTML'
        <h1 class="bold">Блок тэнов 9000Вт крепление гайка латунная 380В 2&quot; L=420mm</h1>
        <p class="article">Артикул товара: <span class="bold article_data">49181</span></p>
        <div class="one-char"><div class="left">Материал</div><div class="right">Нержавейка</div></div>
        <div class="one-char"><div class="left">Номинальная потребляемая мощность, кВт</div><div class="right">9 kW</div></div>
        <div class="one-char"><div class="left">Номинальное напряжение, В</div><div class="right">380</div></div>
        HTML;

        $method = new ReflectionMethod(ProductSourceEnricher::class, 'parsePage');
        $parsed = $method->invoke(new ProductSourceEnricher(), $html, 'https://tenko.ua/ru/catalog/good/example');

        $this->assertCount(4, $parsed['specs']);
        $this->assertContains(['key' => 'Артикул производителя', 'value' => '49181', 'unit' => ''], $parsed['specs']);
        $this->assertContains(['key' => 'Номинальное напряжение, В', 'value' => '380', 'unit' => ''], $parsed['specs']);
    }

    public function test_it_extracts_rusklimat_b2b_characteristics(): void
    {
        $html = <<<'HTML'
        <div class="characteristics">
            <table><tr><th>Фото</th><th>Наименование</th></tr></table>
            <div class="characteristics__line">
                <span class="characteristics__leftText">Фото</span>
                <div class="characteristics__right">Наименование</div>
            </div>
            <div class="characteristics__line">
                <div class="characteristics__left">
                    <span class="characteristics__leftText">Объем внутреннего бака</span>
                </div>
                <div class="characteristics__center"></div>
                <div class="characteristics__right">100 л&nbsp;</div>
            </div>
            <div class="characteristics__line">
                <div class="characteristics__left">
                    <span class="characteristics__leftText">Материал бака</span>
                </div>
                <div class="characteristics__right"><span>Нержавеющая сталь</span></div>
            </div>
        </div>
        HTML;

        $method = new ReflectionMethod(ProductSourceEnricher::class, 'extractSpecs');
        $specs = $method->invoke(new ProductSourceEnricher(), $html);

        $this->assertCount(2, $specs);
        $this->assertContains([
            'key' => 'Объем внутреннего бака',
            'value' => '100 л',
            'unit' => '',
        ], $specs);
        $this->assertContains([
            'key' => 'Материал бака',
            'value' => 'Нержавеющая сталь',
            'unit' => '',
        ], $specs);
    }

    public function test_it_does_not_use_rusklimat_b2b_marketing_meta_as_short_description(): void
    {
        $html = <<<'HTML'
        <html><head>
            <meta name="description" content="Купить оптом с доставкой по России в B2B.РУСКЛИМАТ.">
        </head><body>
            <div class="product-description">Бойлер косвенного нагрева с баком из нержавеющей стали для системы горячего водоснабжения.</div>
        </body></html>
        HTML;

        $method = new ReflectionMethod(ProductSourceEnricher::class, 'parsePage');
        $parsed = $method->invoke(
            new ProductSourceEnricher(),
            $html,
            'https://b2b.rusklimat.com/catalog/product/example/'
        );

        $this->assertStringStartsWith('Бойлер косвенного нагрева', $parsed['short_description']);
        $this->assertStringNotContainsString('купить оптом', mb_strtolower($parsed['short_description']));
        $this->assertLessThanOrEqual(240, mb_strlen($parsed['short_description']));
    }

    public function test_sanitize_description_wraps_root_inline_formatting_without_escaping_it(): void
    {
        $enricher = new ProductSourceEnricher();
        $html = '<strong>Чугунная банная печь ЭТНА 18</strong> оснащается глухой чугунной дверцей.';

        $sanitized = $enricher->sanitizeDescriptionHtml($html);

        $this->assertSame(
            '<p><strong>Чугунная банная печь ЭТНА 18</strong> оснащается глухой чугунной дверцей.</p>',
            $sanitized
        );
        $this->assertSame($sanitized, $enricher->sanitizeDescriptionHtml($sanitized));
    }

    public function test_pkmercury_pages_keep_a_large_gallery_without_adding_manufacturer_page_links(): void
    {
        $images = implode('', array_map(
            fn (int $number): string => sprintf(
                '<a href="https://pkmercury.by/wp-content/uploads/2025/06/tverdotoplivnyj-kotel-kstb-95-1t-izobrazhenie-%d.jpg"><img src="https://pkmercury.by/wp-content/uploads/2025/06/tverdotoplivnyj-kotel-kstb-95-1t-izobrazhenie-%d-1024x1024.jpg"></a>',
                $number,
                $number,
            ),
            range(1, 12),
        ));

        $html = '<html><head><meta property="og:image" content="https://pkmercury.by/wp-content/uploads/2025/06/tverdotoplivnyj-kotel-kstb-95-1t.jpg"></head><body>'
            .$images
            .'<iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TEST"></iframe>'
            .'<a href="https://www.youtube.com/channel/UCB0aeZpMU4W67QL1ZIPD4ow">Youtube</a>'
            .'</body></html>';

        $method = new ReflectionMethod(ProductSourceEnricher::class, 'parsePage');
        $parsed = $method->invoke(
            new ProductSourceEnricher(),
            $html,
            'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-95-1t/'
        );

        $this->assertCount(12, $parsed['images']);
        $this->assertSame([], array_values(array_filter(
            $parsed['images'],
            fn (string $url): bool => ! str_contains($url, '/wp-content/uploads/')
        )));
        $this->assertSame('', $parsed['video_url']);
        $this->assertSame([], $parsed['documents']);
    }
}
