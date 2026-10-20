<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'invicta-shaman-novinka-dostupen-k-zakazu';
    private const PRODUCT_PATH = '/pechi-kaminy/invicta-shaman-chugunnyy-kamin';
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
                'title' => 'Invicta Shaman: новый чугунный камин уже доступен к заказу',
                'excerpt' => 'Новый чугунный камин Invicta Shaman мощностью 14 кВт доступен к заказу в Беларуси. Первую партию ожидаем на следующей неделе.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/invicta-shaman-cover.jpg',
                'images' => json_encode([], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'Invicta',
                    'Shaman',
                    'чугунный камин',
                    'печь-камин',
                    'камин 14 кВт',
                    'камины в Беларуси',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2026-10-20 14:30:00',
                'meta_title' => 'Invicta Shaman — новый чугунный камин 14 кВт | KOTLOV.BY',
                'meta_description' => 'Чугунный камин Invicta Shaman 14 кВт уже доступен к заказу в Беларуси. Обогрев до 170 м², КПД 78,7%, длительное горение до 8 часов. Поступление ожидается на следующей неделе.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('banners')->updateOrInsert(
            ['image' => self::BANNER_IMAGE, 'position' => 'hero'],
            [
                'title' => 'Invicta Shaman',
                'subtitle' => 'Новинка · доступен к заказу',
                'description' => 'Чугунный камин мощностью 14 кВт. Поступление первой партии ожидается на следующей неделе.',
                'image_mobile' => null,
                'link' => self::PRODUCT_PATH,
                'button_text' => 'Заказать Shaman',
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
<p class="text text-body-1"><strong>Invicta Shaman уже доступен к заказу в KOTLOV.BY.</strong> Первую партию новых чугунных каминов ожидаем на складе поставщика в Москве на следующей неделе. Заказ можно оформить заранее — менеджер подтвердит срок поставки и рассчитает доставку по Беларуси.</p>

<div class="blog-image-2">
    <img loading="eager" width="2000" height="2000" src="/img/blog/invicta-shaman-cover.jpg" alt="Чугунный камин Invicta Shaman 14 кВт">
</div>

<h2>Высокий камин для просторного дома</h2>

<p class="text text-body-1">Shaman — вертикальный чугунный камин с прямым стеклом и выразительным современным силуэтом. Номинальная мощность 14 кВт рассчитана на помещения площадью от 75 до 170 м², а регулируемый диапазон мощности составляет от 9,5 до 21 кВт. Модель подойдёт для загородного дома, большой гостиной или открытого пространства с высоким потолком.</p>

<div class="kotlov-article-note">
    <strong>Ключевые параметры</strong>
    14 кВт · обогрев до 170 м² · КПД 78,7% · чугунная конструкция · дымоход 180 мм · длительное горение до 8 часов.
</div>

<h2>Что важно в конструкции Invicta Shaman</h2>

<p class="text text-body-1">Корпус и футеровка выполнены из чугуна. Система вторичного дожига помогает полнее использовать топливо, а подача воздуха на стекло снижает образование копоти. Дверца открывается вбок, максимальная длина полена — 44 см. Габариты камина составляют 560 × 1200 × 578 мм, масса — 160 кг.</p>

<p class="text text-body-1">Перед покупкой важно проверить дымоход, безопасные расстояния и реальную тепловую нагрузку дома. Специалисты KOTLOV.BY помогут подобрать дымоход, комплектующие и вариант монтажа, чтобы камин работал стабильно и безопасно.</p>

<h2>Цена и заказ</h2>

<p class="text text-body-1">Розничная цена в карточке товара пересчитывается в белорусские рубли по актуальному курсу. Поскольку первая партия ещё в пути, наличие и срок доставки менеджер подтверждает при оформлении заказа.</p>

<p class="text text-body-1"><a href="/pechi-kaminy/invicta-shaman-chugunnyy-kamin"><strong>Посмотреть актуальную цену и заказать Invicta Shaman →</strong></a></p>

<p class="text text-body-1">Другие готовые решения можно сравнить в разделе <a href="/pechki"><strong>печей и каминов</strong></a>, а подходящий дымоход — в разделе <a href="/dymohody"><strong>дымоходных систем</strong></a>.</p>
HTML;
    }
};
