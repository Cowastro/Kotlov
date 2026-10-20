<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';
    private const PRODUCT_PATH = '/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin';
    private const BANNER_IMAGE = 'banners/invicta-shaman-launch.svg';

    public function up(): void
    {
        $now = now();

        DB::table('blog_categories')->updateOrInsert(
            ['slug' => 'kaminy-i-topki'],
            [
                'name' => 'Камины и топки',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::ARTICLE_SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'ЭкоКамин ШАМАН — современный аналог легендарного Invicta Shaman',
                'excerpt' => 'Новый чугунный камин ЭкоКамин ШАМАН мощностью 14 кВт — современный аналог легендарного Invicta Shaman. Поступление первой партии ожидается 26–30 октября 2026 года.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/invicta-shaman-cover.jpg',
                'images' => json_encode([], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'ЭкоКамин',
                    'ШАМАН',
                    'аналог Invicta Shaman',
                    'чугунный камин',
                    'печь-камин',
                    'камин 14 кВт',
                    'камины в Беларуси',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2026-10-20 14:30:00',
                'meta_title' => 'ЭкоКамин ШАМАН — аналог Invicta Shaman 14 кВт | KOTLOV.BY',
                'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт — современный аналог легендарного Invicta Shaman. До 170 м², КПД 78,7%, поступление 26–30 октября 2026 года.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('banners')->updateOrInsert(
            ['image' => self::BANNER_IMAGE, 'position' => 'hero'],
            [
                'title' => 'ЭкоКамин ШАМАН',
                'subtitle' => 'Новинка · доступен к заказу',
                'description' => 'Чугунный камин мощностью 14 кВт. Поступление первой партии ожидается 26–30 октября 2026 года.',
                'image_mobile' => null,
                'link' => self::PRODUCT_PATH,
                'button_text' => 'Заказать ШАМАН',
                'sort_order' => -200,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('banners')->where('image', self::BANNER_IMAGE)->where('position', 'hero')->delete();
        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->delete();
        Cache::forget('home:data');
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>ЭкоКамин ШАМАН уже доступен к заказу в KOTLOV.BY.</strong> Это камин российского производителя «ЭкоКамин», выполненный в духе легендарного Invicta Shaman. Поступление первой партии на склад поставщика в Москве ожидается 26–30 октября 2026 года.</p>

<div class="blog-image-2">
    <img loading="eager" width="2000" height="2000" src="/img/blog/invicta-shaman-cover.jpg" alt="Чугунный камин ЭкоКамин ШАМАН 14 кВт">
</div>

<h2>Высокий камин для просторного дома</h2>

<p class="text text-body-1">ШАМАН — вертикальный чугунный камин с прямым стеклом и выразительным современным силуэтом. По формату и характеру модели это современный аналог легендарного Invicta Shaman, но производитель этой модели — российская компания «ЭкоКамин». Номинальная мощность 14 кВт рассчитана на помещения площадью от 75 до 170 м².</p>

<div class="kotlov-article-note">
    <strong>Ключевые параметры</strong>
    14 кВт · обогрев до 170 м² · КПД 78,7% · чугунная конструкция · дымоход 180 мм · длительное горение до 8 часов.
</div>

<h2>Что важно в конструкции ЭкоКамин ШАМАН</h2>

<p class="text text-body-1">Корпус и футеровка выполнены из чугуна. Система вторичного дожига помогает полнее использовать топливо, а подача воздуха на стекло снижает образование копоти. Дверца открывается вбок, максимальная длина полена — 44 см. Габариты камина составляют 560 × 1200 × 578 мм, масса — 160 кг.</p>

<p class="text text-body-1">Перед покупкой важно проверить дымоход, безопасные расстояния и реальную тепловую нагрузку дома. Специалисты KOTLOV.BY помогут подобрать дымоход, комплектующие и вариант монтажа, чтобы камин работал стабильно и безопасно.</p>

<h2>Цена и заказ</h2>

<p class="text text-body-1">Актуальная розничная цена указана в карточке товара. Поступление первой партии ожидается 26–30 октября 2026 года.</p>

<p class="text text-body-1"><a href="/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin"><strong>Посмотреть актуальную цену и заказать ЭкоКамин ШАМАН →</strong></a></p>

<p class="text text-body-1">Другие готовые решения можно сравнить в разделе <a href="/pechki"><strong>печей и каминов</strong></a>, а подходящий дымоход — в разделе <a href="/dymohody"><strong>дымоходных систем</strong></a>.</p>
HTML;
    }
};
