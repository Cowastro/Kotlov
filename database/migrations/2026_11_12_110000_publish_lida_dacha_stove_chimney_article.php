<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Database\Migrations\Migration;

/**
 * Lida regional article: a budget chimney for a cast iron stove at a dacha (the stove «Везувий» was the owner's, the
 * chimney and installation are ours; the master will finish the job). Same pattern as the Lida chimney article: the
 * city storefront feed, «Новости магазинов», the store contacts on Калинина, 57.
 */
return new class extends Migration
{
    public function up(): void
    {
        $city = City::query()->where('slug', 'lida')->where('is_active', true)->where('storefront_enabled', true)->first();
        if (! $city || ! PartnerBranch::query()->visibleIn($city)->exists()) {
            return;
        }

        $category = BlogCategory::firstOrCreate(['slug' => 'novosti-magazinov'], [
            'name' => 'Новости магазинов',
            'sort_order' => 11,
            'is_active' => true,
        ]);

        BlogPost::firstOrCreate(['slug' => 'dymohod-dlya-pechi-na-dache-lida'], [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'title' => 'Дымоход для печи на даче в Лиде: бюджетный монтаж через кровлю',
            'excerpt' => 'Дача в Лиде: чугунная печь «Везувий», дымоход из нержавеющей стали через перекрытие и кровлю, растяжки, защитный экран и предтопочный лист. Показываем бюджетное решение и что важно при монтаже.',
            'cover_image' => 'img/blog/works/lida-dacha-pech/pech-i-dymohod-v-dome.webp',
            'tags' => ['Лида', 'Монтаж дымоходов', 'Дымоход для печи', 'Печь на дачу', 'Сэндвич-дымоход', 'Лидский район'],
            'meta_title' => 'Дымоход для печи на даче в Лиде — монтаж через кровлю | KOTLOV',
            'meta_description' => 'Монтаж дымохода для печи на даче в Лиде: нержавеющий дымоход через перекрытие и кровлю, растяжки, экран и предтопочный лист. Бюджетное решение. KOTLOV, Лида, Калинина, 57.',
            'is_published' => true,
            'published_at' => now(),
            'content' => <<<'HTML'
<p>Новый объект в Лиде — небольшая дача из блоков, где хозяева решили топить дровами. Печь у владельцев уже была: чугунная <strong>«Везувий»</strong> с варочной поверхностью. Нашей задачей было сделать к ней дымоход — безопасно и без лишних затрат. Получился <strong>бюджетный вариант</strong>, который печь уже прогревает, а наш мастер ещё доработает отделку.</p>

<nav class="local-shop-nav" aria-label="Дымоходы и контакты в Лиде">
    <a href="/dymohody">Каталог дымоходов</a>
    <a href="/dymohody-sendvich">Сэндвич-дымоходы</a>
    <a href="/pechki">Печи</a>
    <a href="/#city-branch">Магазин на Калинина, 57</a>
</nav>

<div class="local-shop-photos">
    <figure>
        <a href="/img/blog/works/lida-dacha-pech/pech-i-dymohod-v-dome.webp">
            <img src="/img/blog/works/lida-dacha-pech/pech-i-dymohod-v-dome.webp" alt="Чугунная печь Везувий на даче в Лиде с дымоходом, защитным экраном и предтопочным листом" width="835" height="1225" loading="lazy" decoding="async">
        </a>
        <figcaption>Печь в углу комнаты: дымоход, экран на стене и лист на полу.</figcaption>
    </figure>
    <figure>
        <a href="/img/blog/works/lida-dacha-pech/dymohod-nad-krovley.webp">
            <img src="/img/blog/works/lida-dacha-pech/dymohod-nad-krovley.webp" alt="Дымоход из нержавеющей стали над кровлей дачи в Лиде с растяжками и колпаком" width="960" height="1280" loading="lazy" decoding="async">
        </a>
        <figcaption>Снаружи: труба выше конька, растяжки и колпак.</figcaption>
    </figure>
</div>

<h2>Как устроен дымоход</h2>
<ul>
    <li><strong>Внутри комнаты</strong> от печи идёт одностенная труба, а перед перекрытием — переход на утеплённый сэндвич-дымоход из нержавеющей стали. Сэндвич нужен там, где труба проходит через потолок и чердак: его наружная стенка греется намного меньше.</li>
    <li><strong>Через кровлю</strong> труба выведена с герметичной проходкой, чтобы не текло по шиферу.</li>
    <li><strong>Над крышей</strong> дымоход поднят выше конька — так он лучше тянет и ветер не задувает дым обратно. Высокую трубу держат <strong>растяжки</strong>, сверху стоит <strong>колпак</strong> от осадков.</li>
</ul>

<h2>Безопасность в доме</h2>
<p>Стены на даче отделаны горючими панелями, поэтому рядом с печью поставили <strong>металлический защитный экран</strong> — он отражает жар от трубы и печи. На деревянный пол под печью и перед дверцей уложен <strong>предтопочный лист</strong>: случайно выпавший уголёк не прожжёт доски. Это минимальный набор, без которого печь в деревянном доме ставить нельзя.</p>

<h2>Почему «бюджетный вариант» и что доделаем</h2>
<p>Хозяева хотели начать топить уже этой осенью и не тратиться сразу на облицовку. Поэтому сейчас сделана рабочая часть: дымоход, проход через перекрытие и кровлю, защита стены и пола. Наш мастер ещё вернётся на объект и доработает отделку вокруг печи. Такой подход удобен для дач: сначала безопасный и тёплый дом, потом — красота.</p>

<h2>Что подготовить для расчёта дымохода на дачу</h2>
<ol>
    <li><strong>Печь.</strong> Модель или фото паспорта: нужен диаметр патрубка и требования к дымоходу.</li>
    <li><strong>Фото комнаты и потолка.</strong> Где стоит печь, как проходит перекрытие, из чего стены и пол.</li>
    <li><strong>Фото крыши.</strong> Материал кровли и место, где труба выйдет наружу, расстояние до конька.</li>
    <li><strong>Высота дома.</strong> От пола до потолка и от потолка до конька — по ней считается длина трубы.</li>
</ol>
<p>По этим данным мы подберём комплект: трубы, переходник с одностенной на сэндвич, проходку через перекрытие и кровлю, растяжки, колпак и защиту стены и пола.</p>

<h2>Дымоход и монтаж в Лиде и Лидском районе</h2>
<p>Комплект дымохода для печи или котла можно подобрать в <strong>магазине KOTLOV, г. Лида, ул. Калинина, 57</strong>. Покажите фото печи, комнаты и крыши — подскажем комплектацию, проверим наличие и договоримся о выезде мастера по Лиде и району.</p>
<p><a href="tel:+375333667609">+375 (33) 366-76-09</a> · <a href="https://t.me/kotlovlida">Отправить фото в Telegram</a> · <a href="/#city-branch">График работы и карта</a></p>
HTML,
        ]);
    }

    public function down(): void
    {
        // Preserve the published article and any later editorial edits.
    }
};
