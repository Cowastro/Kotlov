<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Product;
use App\Services\SeoMetadataBuilder;
use PHPUnit\Framework\TestCase;

class SeoMetadataBuilderTest extends TestCase
{
    private SeoMetadataBuilder $seo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seo = new SeoMetadataBuilder();
    }

    public function test_commercial_category_titles_keep_the_product_type(): void
    {
        $cases = [
            'tverdotoplivnye' => 'Твердотопливные котлы: цены, купить в Минске | KOTLOV',
            'kotly-na-pelletah' => 'Пеллетные котлы: цены, купить в Минске | KOTLOV',
            'gazovye' => 'Газовые котлы: цены, купить в Минске | KOTLOV',
            'elektricheskie' => 'Электрические котлы: цены, купить в Минске | KOTLOV',
            'electric' => 'Электрические водонагреватели — купить в Минске | KOTLOV',
            'pechki' => 'Печи для дома: цены, купить в Минске | KOTLOV',
            'pechi-kaminy' => 'Печи-камины: цены, купить в Минске | KOTLOV',
            'peci-drovianye-otopitelnye' => 'Дровяные печи: цены, купить в Минске | KOTLOV',
        ];

        foreach ($cases as $slug => $expected) {
            $title = $this->seo->categoryTitle(
                $slug,
                'Слишком короткое имя',
                'в Минске',
                str_repeat('Очень длинный сохранённый заголовок ', 5),
            );

            $this->assertSame($expected, $title);
            $this->assertLessThanOrEqual(SeoMetadataBuilder::TITLE_LIMIT, mb_strlen($title));
        }
    }

    public function test_it_does_not_repeat_brand_already_present_in_product_name(): void
    {
        $product = $this->product('Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт');

        $this->assertSame(
            'Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт',
            $this->seo->productName($product)
        );
    }

    public function test_it_rebuilds_long_spammy_title_within_limit(): void
    {
        $product = $this->product('Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт');
        $product->meta_title = 'KOTLOV Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт — купить в Минске | KOTLOV';

        $title = $this->seo->productTitle($product, 'в Минске');

        $this->assertLessThanOrEqual(SeoMetadataBuilder::TITLE_LIMIT, mb_strlen($title));
        $this->assertLessThanOrEqual(2, substr_count(mb_strtoupper($title), 'KOTLOV'));
    }

    public function test_it_replaces_stale_price_in_description(): void
    {
        $product = $this->product('Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт');
        $product->price = 12960;
        $product->meta_description = 'Пеллетная горелка для котельной. Цена 14 400 руб. Доставка по Беларуси.';

        $description = $this->seo->productDescription($product, 'в Беларуси');

        $this->assertStringContainsString('Цена 12 960 BYN', $description);
        $this->assertStringNotContainsString('14 400', $description);
    }

    public function test_it_rebuilds_description_with_repeated_brand(): void
    {
        $product = $this->product('Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт');
        $product->price = 12960;
        $product->meta_description = 'Купить KOTLOV пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт | KOTLOV.';

        $description = $this->seo->productDescription($product, 'в Беларуси');

        $this->assertStringStartsWith('Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт', $description);
        $this->assertLessThanOrEqual(2, substr_count(mb_strtoupper($description), 'KOTLOV'));
    }

    public function test_it_replaces_short_generic_supplier_description_with_useful_product_facts(): void
    {
        $product = $this->product('Пеллетный котел TIS Pellet 15');
        $product->price = 9800;
        $product->meta_description = 'Пеллетный котел TIS Pellet 15 — купить по лучшей цене.';

        $description = $this->seo->productDescription($product, 'в Беларуси', [
            'Мощность: 15 кВт кВт',
            'Отапливаемая площадь: до 150 м²',
        ]);

        $this->assertStringContainsString('Мощность: 15 кВт', $description);
        $this->assertStringNotContainsString('кВт кВт', $description);
        $this->assertStringContainsString('Отапливаемая площадь: до 150 м²', $description);
        $this->assertStringContainsString('Цена 9 800 BYN', $description);
        $this->assertStringNotContainsString('по лучшей цене', $description);
        $this->assertLessThanOrEqual(SeoMetadataBuilder::DESCRIPTION_LIMIT, mb_strlen($description));
    }

    public function test_it_keeps_detailed_stored_description_even_when_it_contains_commercial_phrase(): void
    {
        $product = $this->product('Котел TIS Pellet 25');
        $product->meta_description = 'Котел TIS Pellet 25 с автоматической подачей топлива и погодозависимым управлением — купить в Беларуси с официальной гарантией.';

        $description = $this->seo->productDescription($product, 'в Беларуси', ['Мощность: 25 кВт']);

        $this->assertSame($product->meta_description, $description);
    }

    public function test_generic_title_keeps_site_suffix_and_stays_within_limit(): void
    {
        $title = $this->seo->title(
            'Очень длинный заголовок статьи о подборе и установке пеллетных горелок для отопления большого здания | KOTLOV',
            'Fallback | KOTLOV'
        );

        $this->assertLessThanOrEqual(SeoMetadataBuilder::TITLE_LIMIT, mb_strlen($title));
        $this->assertStringEndsWith(' | KOTLOV', $title);
    }

    public function test_it_builds_short_description_only_from_substantial_existing_content(): void
    {
        $short = $this->seo->shortDescriptionFromContent(
            '<h2>NOVA3</h2><p>Автоматическая система подачи пеллет перемещает топливо из удалённого бункера к котлу и помогает организовать стабильную работу котельной.</p>'
        );

        $this->assertNotNull($short);
        $this->assertStringNotContainsString('<', $short);
        $this->assertStringContainsString('Автоматическая система подачи пеллет', $short);
        $this->assertLessThanOrEqual(220, mb_strlen($short));
        $this->assertNull($this->seo->shortDescriptionFromContent('<p>Слишком коротко.</p>'));
    }

    public function test_short_description_truncation_preserves_valid_utf8_at_cyrillic_boundary(): void
    {
        $content = '<p>' . str_repeat('а', 218) . 'р продолжение описания товара для проверки границы.</p>';

        $short = $this->seo->shortDescriptionFromContent($content);

        $this->assertNotNull($short);
        $this->assertTrue(mb_check_encoding($short, 'UTF-8'));
        $this->assertStringNotContainsString("\u{FFFD}", $short);
        $this->assertStringEndsWith('…', $short);
    }

    private function product(string $name): Product
    {
        $brand = new Brand(['name' => 'KOTLOV']);
        $product = new Product(['name' => $name]);
        $product->setRelation('brand', $brand);

        return $product;
    }
}
