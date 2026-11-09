<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** News: the cast-iron ЭкоКамин PRIMO 700 firebox with a barbecue grill grate as a gift. */
return new class extends Migration
{
    private const SLUG = 'ekokamin-primo-700-reshetka-gril-v-podarok';

    public function up(): void
    {
        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');
        if (! $categoryId) {
            return;
        }

        $now = now();
        DB::table('blog_posts')->updateOrInsert(['slug' => self::SLUG], [
            'category_id' => $categoryId,
            'author_id' => null,
            'title' => 'ЭкоКамин PRIMO 700: чугунная топка с решёткой-гриль в подарок',
            'excerpt' => 'Чугунная каминная топка ЭкоКамин PRIMO 700 в духе знаменитой Invicta Philos: 12 кВт, двойные чугунные стенки, вторичный дожиг и режим гриль-барбекю. К каждой топке — решётка-гриль в подарок.',
            'content' => $this->content(),
            'cover_image' => 'img/blog/ecokamin-primo/primo-700-fire.webp',
            'images' => json_encode([
                'img/blog/ecokamin-primo/primo-700-fire.webp',
                'img/blog/ecokamin-primo/primo-700-grill.webp',
                'img/blog/ecokamin-primo/primo-700-side-door.webp',
            ], JSON_UNESCAPED_SLASHES),
            'tags' => json_encode(['ЭкоКамин', 'PRIMO 700', 'чугунная топка', 'каминная топка', 'решётка-гриль', 'подарок', 'акция'], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'published_at' => $now,
            'meta_title' => 'ЭкоКамин PRIMO 700 — чугунная топка с решёткой-гриль в подарок | KOTLOV.BY',
            'meta_description' => 'Чугунная каминная топка ЭкоКамин PRIMO 700: 12 кВт, КПД 76%, вторичный дожиг, поленья до 50 см. Решётка-гриль в подарок, акционная цена до 30 ноября, доставка по Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::SLUG)->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>К каждой чугунной топке <a href="/topki/topka-cugunnaia-primo-700">ЭкоКамин PRIMO 700</a> теперь идёт решётка-гриль в подарок.</strong> Камин зимой греет дом, а в любой вечер на нём можно приготовить стейк, рыбу или овощи прямо над углями. Решётка входит в комплект без доплаты. До 30 ноября топка ещё и продаётся по акционной цене производителя.</p>

<div class="blog-image-2">
    <img loading="eager" width="1100" height="960" src="/img/blog/ecokamin-primo/primo-700-fire.webp" alt="Чугунная каминная топка ЭкоКамин PRIMO 700 с огнём">
</div>

<h2>Классика в духе Invicta Philos</h2>
<p class="text text-body-1">PRIMO 700 сделана в духе знаменитой французской топки <strong>Invicta Philos</strong>: ребристый «купол» из чугуна, большое прямое стекло и колосниковое горение. Её выпускает российский завод «ЭкоКамин», поэтому она заметно дешевле европейского оригинала, а запчасти и сервис доступны.</p>

<div class="kotlov-article-note">
    <strong>Коротко о PRIMO 700</strong>
    12 кВт · КПД 76% · чугун · вторичный дожиг · «чистое стекло» · встроенный шибер · воздух извне · поленья до 50 см · решётка-гриль в подарок.
</div>

<h2>Решётка-гриль: камин превращается в барбекю</h2>

<div class="blog-image-2">
    <img loading="lazy" width="1000" height="667" src="/img/blog/ecokamin-primo/primo-700-grill.webp" alt="Решётка-гриль внутри топки ЭкоКамин PRIMO 700">
</div>

<p class="text text-body-1">Решётка ставится на кронштейны внутри топки, над слоем углей. Когда дрова прогорели, открываете дверцу — и готовите, как на мангале, только у себя в гостиной, беседке или на летней кухне. Дым уходит в дымоход, поэтому в доме не пахнет гарью, а готовить можно в любую погоду.</p>

<h2>Чем хороша топка</h2>
<ul>
    <li><strong>двойные чугунные стенки</strong> и чугунная футеровка — топка долго держит и ровно отдаёт тепло;</li>
    <li><strong>вторичный дожиг</strong> — газы догорают в камере: меньше сажи, больше тепла с той же закладки;</li>
    <li><strong>«чистое стекло»</strong> — воздушная завеса не даёт стеклу закоптиться;</li>
    <li><strong>встроенный шибер</strong> и регулировка воздуха — легко держать режим длительного горения;</li>
    <li><strong>воздух извне</strong> через патрубок 50 мм — камин не забирает кислород из комнаты;</li>
    <li><strong>поленья до 50 см</strong> — не нужно мелко колоть дрова.</li>
</ul>

<div class="blog-image-2">
    <img loading="lazy" width="667" height="1000" src="/img/blog/ecokamin-primo/primo-700-side-door.webp" alt="ЭкоКамин PRIMO 700 с открытой боковой дверцей">
</div>

<h2>Характеристики</h2>
<ul>
    <li>тепловая мощность — 12 кВт, КПД — 76%;</li>
    <li>дымоход — верхнее подключение, патрубок 180 мм;</li>
    <li>габариты (Ш × В × Г) — 690 × 662 × 452 мм, масса — 128 кг;</li>
    <li>стеклокерамика до 750 °C, дверца открывается вбок.</li>
</ul>

<h2>Дымоход и монтаж</h2>
<p class="text text-body-1">PRIMO 700 ставят в облицовку из кирпича или камня, в готовый портал или в каминную конструкцию в беседке. Подберём дымоход «Теплов и Сухов» на 180 мм, подскажем по отступам и теплоизоляции, при необходимости организуем монтаж. Доставка по Минску и всей Беларуси.</p>

<p class="text text-body-1"><a href="/topki/topka-cugunnaia-primo-700"><strong>Перейти к ЭкоКамин PRIMO 700 и заказать топку с решёткой-гриль в подарок →</strong></a></p>
HTML;
    }
};
