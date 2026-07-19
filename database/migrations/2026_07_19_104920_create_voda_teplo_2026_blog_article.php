<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'kotlov-by-na-vystavke-voda-i-teplo-2026';
    private const CATEGORY_SLUG = 'novosti-kompanii';

    public function up(): void
    {
        $now = now();

        DB::table('blog_categories')->updateOrInsert(
            ['slug' => self::CATEGORY_SLUG],
            [
                'name' => 'Новости компании',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $categoryId = DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'KOTLOV.BY на выставке «Вода и тепло 2026»: GE, KOTLOV XO, Ecokamin и Теплов и Сухов',
                'excerpt' => 'В апреле 2026 года KOTLOV.BY представила на международной выставке «Вода и тепло» тепловые насосы GE, пеллетные горелки KOTLOV XO, премиальные камины Ecokamin и дымоходы Теплов и Сухов.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/works/voda-teplo-2026-kotlov-cover.jpg',
                'images' => json_encode([
                    'img/blog/works/voda-teplo-2026-stand-1.jpg',
                    'img/blog/works/voda-teplo-2026-stand-2.jpg',
                    'img/blog/works/voda-teplo-2026-stand-3.jpg',
                    'img/blog/works/voda-teplo-2026-poster.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'tags' => json_encode([
                    'KOTLOV.BY',
                    'Вода и тепло 2026',
                    'тепловые насосы GE',
                    'KOTLOV XO',
                    'Ecokamin',
                    'Теплов и Сухов',
                    'выставка',
                ], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2026-04-18 10:00:00',
                'meta_title' => 'KOTLOV.BY на выставке Вода и тепло 2026 | GE, KOTLOV XO, Ecokamin',
                'meta_description' => 'KOTLOV.BY приняла участие в международной выставке «Вода и тепло 2026» в Минске и представила тепловые насосы GE, пеллетные горелки KOTLOV XO, камины Ecokamin и дымоходы Теплов и Сухов.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::SLUG)->delete();

        if (! DB::table('blog_posts')->where('category_id', DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->value('id'))->exists()) {
            DB::table('blog_categories')->where('slug', self::CATEGORY_SLUG)->delete();
        }
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1">С 14 по 17 апреля 2026 года в Минске прошла 27-я международная выставка водо- и теплоснабжения «Вода и тепло». Для KOTLOV.BY это была не просто витрина оборудования, а возможность показать посетителям живые решения: как выглядит техника на стенде, как она компонуется между собой и какие системы можно подобрать под частный дом, котельную, каминную зону или баню.</p>

<p class="text text-body-1">На стенде KOTLOV.BY были представлены направления, с которыми компания работает каждый день: тепловые насосы GE, пеллетные решения KOTLOV XO, премиальные камины Ecokamin, а также дымоходные системы Теплов и Сухов. Такой формат удобен тем, что посетитель видит не отдельные позиции из каталога, а связку оборудования — от источника тепла до дымохода и готового интерьерного решения.</p>

<blockquote>
    Для нас выставка — это способ поговорить с клиентами и монтажниками лицом к лицу. В каталоге можно увидеть характеристики, но на стенде человек сразу понимает масштаб оборудования, качество сборки и то, как система будет выглядеть в реальном проекте.
</blockquote>

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-stand-1.jpg" alt="Стенд KOTLOV.BY на выставке Вода и тепло 2026 в Минске">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-stand-2.jpg" alt="Оборудование KOTLOV.BY на выставочном стенде: GE, Ecokamin, KOTLOV XO и дымоходы">
    </div>
</div>

<h2>Что показывали на стенде</h2>

<p class="text text-body-1">Главный акцент был сделан на оборудовании, которое закрывает разные сценарии отопления и комфорта в частном доме.</p>

<ul>
    <li><strong>Тепловые насосы GE.</strong> Высокотемпературные решения для отопления и горячей воды, в том числе для проектов, где важно уйти от полностью электрического или топливного отопления.</li>
    <li><strong>Пеллетные горелки KOTLOV XO.</strong> Практичный вариант модернизации котельной и перехода на автоматизированное твердотопливное отопление.</li>
    <li><strong>Премиальные камины Ecokamin.</strong> Интерьерные каминные решения, которые работают не только как источник тепла, но и как центральный элемент пространства.</li>
    <li><strong>Дымоходы Теплов и Сухов.</strong> Нержавеющие модульные системы для котлов, каминов и бань, где важны безопасность, тяга и правильный подбор под режим работы.</li>
</ul>

<h2>Почему такие выставки важны</h2>

<p class="text text-body-1">Отопительное оборудование часто выбирают по цене, киловаттам и красивым картинкам. Но в реальном проекте важно другое: как оборудование будет обслуживаться, есть ли запас по мощности, какой дымоход нужен, можно ли подключить автоматику, насколько удобно будет пользоваться системой зимой.</p>

<p class="text text-body-1">На выставке эти вопросы проще обсуждать предметно. Посетители могли увидеть живые образцы, задать вопросы по монтажу и получить консультацию по подбору под дом, баню, камин или котельную.</p>

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-stand-3.jpg" alt="Консультации на стенде KOTLOV.BY во время выставки Вода и тепло 2026">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="900" height="640" src="/img/blog/works/voda-teplo-2026-poster.jpg" alt="Анонс участия KOTLOV.BY в выставке Вода и тепло 2026">
    </div>
</div>

<h2>Итоги участия</h2>

<p class="text text-body-1">Выставка «Вода и тепло 2026» стала для KOTLOV.BY хорошей точкой контакта с владельцами домов, монтажниками и партнёрами. Мы показали, что современное отопление — это не один котёл или одна красивая каминная топка, а система, где каждый элемент должен быть подобран под задачу.</p>

<p class="text text-body-1">Если вы не успели посетить стенд, специалисты KOTLOV.BY помогут подобрать оборудование под ваш проект: тепловой насос, котельное решение, камин, банную печь или дымоходную систему.</p>

<div class="kotlov-article-note">
    <strong>Нужен подбор под объект?</strong>
    Подготовьте площадь дома, тип отопления, желаемый источник тепла и фото котельной или места установки — так консультация будет быстрее и точнее.
</div>
HTML;
    }
};
