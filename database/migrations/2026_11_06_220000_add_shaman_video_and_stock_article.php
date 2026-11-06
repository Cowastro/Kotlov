<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** ЭкоКамин ШАМАН: the video in its card and the news that the fireplace is in stock and ready to order. */
return new class extends Migration
{
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';

    private const POST_SLUG = 'ekokamin-shaman-na-sklade-gotov-k-zakazu';

    private const VIDEO = 'https://www.youtube.com/embed/a3Vijwf9rlw';

    public function up(): void
    {
        $now = now();
        DB::table('products')->where('slug', self::PRODUCT_SLUG)->update(['video_url' => self::VIDEO, 'updated_at' => $now]);

        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');
        if (! $categoryId) {
            return;
        }

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::POST_SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'ЭкоКамин ШАМАН уже на складе — готов к заказу',
                'excerpt' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт с панорамным стеклом приехал на склад KOTLOV. Смотрите видео, характеристики и оформляйте заказ с доставкой по Беларуси.',
                'content' => $this->content(),
                'cover_image' => 'img/products/ecokamin/shaman/ecokamin-shaman-real-front.webp',
                'images' => json_encode([
                    'img/products/ecokamin/shaman/ecokamin-shaman-real-front.webp',
                    'img/products/ecokamin/shaman/ecokamin-shaman-real-firebox.webp',
                    'img/products/ecokamin/shaman/ecokamin-shaman-real-door-handle.webp',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['ЭкоКамин', 'ШАМАН', 'чугунный камин', 'в наличии', 'видео'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now,
                'meta_title' => 'ЭкоКамин ШАМАН в наличии — чугунный камин 14 кВт | KOTLOV',
                'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт в наличии на складе KOTLOV: видеообзор, характеристики, доставка по Минску и Беларуси.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::POST_SLUG)->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1">Чугунный камин <a href="/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin"><strong>ЭкоКамин ШАМАН</strong></a> приехал на склад KOTLOV и уже готов к заказу. Не нужно ждать поставку под заказ: камин можно забрать или получить с доставкой по Минску за 1–2 дня, по регионам Беларуси — за 2–5 дней.</p>

<div class="ratio ratio-16x9 my-4 rounded overflow-hidden">
    <iframe src="https://www.youtube.com/embed/a3Vijwf9rlw" title="Чугунный камин Шаман" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
</div>

<h2>Что за камин</h2>
<p class="text text-body-1">ШАМАН — высокий вертикальный камин российского производителя «ЭкоКамин» с большим прямым стеклом. Литой чугунный корпус и футеровка топки, вторичный дожиг и система «чистое стекло» делают его не только украшением гостиной, но и полноценным источником тепла для загородного дома.</p>

<ul>
    <li>номинальная мощность 14 кВт, регулировка от 9,5 до 21 кВт;</li>
    <li>обогрев от 75 до 170 м² — в зависимости от утепления и высоты потолков;</li>
    <li>КПД 78,7%, длительное горение до 8 часов;</li>
    <li>полено до 44 см, верхнее подключение дымохода 180 мм;</li>
    <li>габариты 560 × 1200 × 578 мм, масса 160 кг, цвет антрацит;</li>
    <li>гарантия производителя 5 лет.</li>
</ul>

<h2>Дымоход и монтаж</h2>
<p class="text text-body-1">К камину подберём дымоход «Теплов и Сухов» — при покупке в одной корзине с камином на комплектующие дымохода действует скидка 10%. Подскажем по отступам, негорючему основанию и защите стен, а при необходимости организуем монтаж.</p>

<p class="text text-body-1"><a href="/pechi-kaminy/ecokamin-shaman-chugunnyy-kamin"><strong>Перейти к ЭкоКамин ШАМАН и оформить заказ →</strong></a></p>
HTML;
    }
};
