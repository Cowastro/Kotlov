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

        $this->assertStringStartsWith('Купить Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт', $description);
        $this->assertLessThanOrEqual(2, substr_count(mb_strtoupper($description), 'KOTLOV'));
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

    private function product(string $name): Product
    {
        $brand = new Brand(['name' => 'KOTLOV']);
        $product = new Product(['name' => $name]);
        $product->setRelation('brand', $brand);

        return $product;
    }
}
