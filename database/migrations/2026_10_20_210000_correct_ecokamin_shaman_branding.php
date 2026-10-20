<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_PRODUCT_SLUG = 'invicta-shaman-chugunnyy-kamin';
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';
    private const OLD_ARTICLE_SLUG = 'invicta-shaman-novinka-dostupen-k-zakazu';
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';
    private const BANNER_IMAGE = 'banners/invicta-shaman-launch.svg';

    public function up(): void
    {
        $now = now();
        $brandId = DB::table('brands')->where('name', 'ЭкоКамин')->value('id');
        $product = DB::table('products')->whereIn('slug', [self::OLD_PRODUCT_SLUG, self::PRODUCT_SLUG])->first();

        if ($product) {
            $update = [
                'name' => 'Камин чугунный ЭкоКамин ШАМАН',
                'slug' => self::PRODUCT_SLUG,
                'h1' => 'Камин чугунный ЭкоКамин ШАМАН',
                'short_description' => 'Чугунный камин ЭкоКамин ШАМАН мощностью 14 кВт — современный аналог легендарного Invicta Shaman. Для помещений до 170 м², с длительным горением, вторичным дожигом и системой очистки стекла.',
                'content' => $this->productContent(),
                'meta_title' => 'ЭкоКамин ШАМАН — купить чугунный камин в %city%',
                'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт — современный аналог легендарного Invicta Shaman. До 170 м², КПД 78,7%, длительное горение до 8 часов.',
                'updated_at' => $now,
            ];

            if ($brandId) {
                $update['brand_id'] = $brandId;
            }

            DB::table('products')->where('id', $product->id)->update($update);

            $manufacturerAttributeId = DB::table('attributes')
                ->where('category_id', $product->category_id)
                ->where('name', 'Производитель')
                ->value('id');

            if ($manufacturerAttributeId) {
                DB::table('product_attribute_values')->updateOrInsert(
                    ['product_id' => $product->id, 'attribute_id' => $manufacturerAttributeId],
                    [
                        'option_id' => null,
                        'is_checked' => null,
                        'value' => 'ЭкоКамин, Россия',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        $article = DB::table('blog_posts')->whereIn('slug', [self::OLD_ARTICLE_SLUG, self::ARTICLE_SLUG])->first();
        if ($article) {
            DB::table('blog_posts')->where('id', $article->id)->update([
                'slug' => self::ARTICLE_SLUG,
                'title' => 'ЭкоКамин ШАМАН — современный аналог легендарного Invicta Shaman',
                'excerpt' => 'Новый чугунный камин ЭкоКамин ШАМАН мощностью 14 кВт — современный аналог легендарного Invicta Shaman. Поступление первой партии ожидается 26–30 октября 2026 года.',
                'content' => $this->articleContent(),
                'tags' => json_encode(['ЭкоКамин', 'ШАМАН', 'аналог Invicta Shaman', 'чугунный камин', 'печь-камин', 'камин 14 кВт'], JSON_UNESCAPED_UNICODE),
                'meta_title' => 'ЭкоКамин ШАМАН — аналог Invicta Shaman 14 кВт | KOTLOV.BY',
                'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт — современный аналог легендарного Invicta Shaman. До 170 м², КПД 78,7%, поступление 26–30 октября 2026 года.',
                'updated_at' => $now,
            ]);
        }

        DB::table('banners')
            ->where('image', self::BANNER_IMAGE)
            ->where('position', 'hero')
            ->update([
                'title' => 'ЭкоКамин ШАМАН',
                'link' => '/pechi-kaminy/'.self::PRODUCT_SLUG,
                'button_text' => 'Заказать ШАМАН',
                'updated_at' => $now,
            ]);

        foreach ([
            ['/pechi-kaminy/'.self::OLD_PRODUCT_SLUG, '/pechi-kaminy/'.self::PRODUCT_SLUG],
            ['/blog/'.self::OLD_ARTICLE_SLUG, '/blog/'.self::ARTICLE_SLUG],
        ] as [$from, $to]) {
            DB::table('redirects')->updateOrInsert(
                ['from_url' => $from],
                ['to_url' => $to, 'status_code' => 301, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Branding correction is intentionally durable.
    }

    private function productContent(): string
    {
        return <<<'HTML'
<p><strong>ЭкоКамин ШАМАН</strong> — чугунный камин российского производителя «ЭкоКамин». По формату, вертикальному силуэту и характеру модели это современный аналог легендарного <strong>Invicta Shaman</strong>, но не товар бренда Invicta.</p>
<p>Высокий вертикальный камин рассчитан на просторную гостиную, загородный дом или помещение с высоким потолком. Номинальная мощность 14 кВт подходит для отопления площади от 75 до 170 м².</p>
<p>Диапазон мощности от 9,5 до 21 кВт позволяет регулировать интенсивность обогрева. КПД модели составляет 78,7%. Корпус и футеровка выполнены из чугуна, а система вторичного дожига помогает эффективнее использовать топливо.</p>
<h3>Особенности модели</h3>
<ul>
    <li>номинальная мощность — 14 кВт;</li>
    <li>площадь обогрева — от 75 до 170 м²;</li>
    <li>КПД — 78,7%;</li>
    <li>длительное горение — до 8 часов;</li>
    <li>система вторичного дожига и подача воздуха на стекло;</li>
    <li>максимальная длина полена — 44 см;</li>
    <li>диаметр дымохода — 180 мм;</li>
    <li>габариты — 560 × 1200 × 578 мм;</li>
    <li>масса — 160 кг.</li>
</ul>
<p>Перед заказом специалисты KOTLOV.BY помогут проверить параметры дымохода, безопасные расстояния и необходимую мощность для конкретного дома. Поступление первой партии ожидается <strong>26–30 октября 2026 года</strong>.</p>
HTML;
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>ЭкоКамин ШАМАН уже доступен к заказу в KOTLOV.BY.</strong> Это камин российского производителя «ЭкоКамин», выполненный в духе легендарного Invicta Shaman. Поступление первой партии на склад поставщика в Москве ожидается 26–30 октября 2026 года.</p>
<div class="blog-image-2"><img loading="eager" width="2000" height="2000" src="/img/blog/invicta-shaman-cover.jpg" alt="Чугунный камин ЭкоКамин ШАМАН 14 кВт"></div>
<h2>Высокий камин для просторного дома</h2>
<p class="text text-body-1">ШАМАН — вертикальный чугунный камин с прямым стеклом и выразительным современным силуэтом. По формату и характеру модели это современный аналог легендарного Invicta Shaman, но производитель этой модели — российская компания «ЭкоКамин». Номинальная мощность 14 кВт рассчитана на помещения площадью от 75 до 170 м².</p>
<div class="kotlov-article-note"><strong>Ключевые параметры</strong> 14 кВт · обогрев до 170 м² · КПД 78,7% · чугунная конструкция · дымоход 180 мм · длительное горение до 8 часов.</div>
<h2>Что важно в конструкции ЭкоКамин ШАМАН</h2>
<p class="text text-body-1">Корпус и футеровка выполнены из чугуна. Система вторичного дожига помогает полнее использовать топливо, а подача воздуха на стекло снижает образование копоти. Дверца открывается вбок, максимальная длина полена — 44 см. Габариты камина составляют 560 × 1200 × 578 мм, масса — 160 кг.</p>
<p class="text text-body-1">Перед покупкой важно проверить дымоход, безопасные расстояния и реальную тепловую нагрузку дома. Специалисты KOTLOV.BY помогут подобрать дымоход, комплектующие и вариант монтажа.</p>
<h2>Цена и заказ</h2>
<p class="text text-body-1">Актуальная розничная цена указана в карточке товара. Поступление первой партии ожидается 26–30 октября 2026 года.</p>
<p class="text text-body-1"><a href="/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin"><strong>Посмотреть актуальную цену и заказать ЭкоКамин ШАМАН →</strong></a></p>
HTML;
    }
};
