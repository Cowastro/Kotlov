<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Novelty 2026 ЭкоКамин АЛЯСКА — a fireplace with a cast-iron firebox on a woodbox (the card was made from
 * ecokamin.ru, /catalog/kaminy/kamin_alyaska/19673/, by supplier:sync-ecokamin-stoves --only-new): a news post,
 * the «новинка» mark, a description and the features of the maker's banner that its page table lacks
 * (built-in damper, secondary afterburning, grill/barbecue, long burning, logs up to 50 cm).
 */
return new class extends Migration
{
    private const NEWS_SLUG = 'novinka-ekokamin-alyaska-kamin-s-chugunnoy-topkoy';

    private const CARD = 'kamin-aliaska';

    private const EXTRA_SPECS = [
        'Двойные чугунные стенки' => 'Да',
        'Встроенная шиберная заслонка' => 'Да',
        'Система вторичного дожига' => 'Да',
        'Система гриль/барбекю' => 'Да',
        'Режим длительного горения' => 'Да',
        'Длина поленьев' => 'до 500 мм',
        'Встроенный дровник' => 'Да',
        'Артикул производителя' => 'KA225',
    ];

    public function up(): void
    {
        $product = DB::table('products')->where('slug', self::CARD)->first(['id', 'specs', 'images', 'category_id']);
        if (! $product) {
            return;
        }
        $now = now();
        $categorySlug = (string) DB::table('categories')->where('id', $product->category_id)->value('slug');
        $url = '/'.($categorySlug ?: 'catalog').'/'.self::CARD;

        $specs = collect(json_decode((string) $product->specs, true) ?: [])->filter(fn ($row) => is_array($row) && filled($row['key'] ?? null))->values();
        foreach (self::EXTRA_SPECS as $key => $value) {
            $index = $specs->search(fn ($row) => mb_strtolower(trim((string) $row['key'])) === mb_strtolower($key));
            $index === false ? $specs->push(['key' => $key, 'value' => $value]) : $specs->put($index, ['key' => $key, 'value' => $value]);
        }

        DB::table('products')->where('id', $product->id)->update([
            'name' => 'Камин ЭкоКамин АЛЯСКА с чугунной топкой на дровнике',
            'h1' => 'Камин ЭкоКамин АЛЯСКА с чугунной топкой на дровнике',
            'short_description' => 'Отопительный камин с чугунной топочной камерой и двойными чугунными стенками на встроенном дровнике: 12 кВт, встроенный шибер, вторичный дожиг, «чистое стекло», режим гриль/барбекю и длительное горение. Патрубок 180 мм, поленья до 50 см.',
            'content' => $this->card($url),
            'specs' => $specs->values()->toJson(JSON_UNESCAPED_UNICODE),
            'meta_title' => 'Камин ЭкоКамин АЛЯСКА с чугунной топкой — новинка 2026 | KOTLOV.BY',
            'meta_description' => 'Камин ЭкоКамин АЛЯСКА: чугунная топка с двойными стенками на дровнике, 12 кВт, встроенный шибер, вторичный дожиг, гриль/барбекю, патрубок 180 мм. Цена, характеристики, доставка по Беларуси.',
            'is_new' => true,
            'updated_at' => $now,
        ]);

        DB::table('blog_categories')->updateOrInsert(['slug' => 'kaminy-i-topki'], [
            'name' => 'Камины и топки', 'sort_order' => 30, 'is_active' => true, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');
        $images = array_values(array_filter((array) json_decode((string) $product->images, true), 'is_string'));
        $cover = $images[0] ?? null;

        DB::table('blog_posts')->updateOrInsert(['slug' => self::NEWS_SLUG], [
            'category_id' => $categoryId,
            'author_id' => null,
            'title' => 'Новинка 2026: камин ЭкоКамин АЛЯСКА с чугунной топкой на дровнике',
            'excerpt' => 'ЭкоКамин выпустил АЛЯСКУ — камин с чугунной топочной камерой и двойными чугунными стенками на встроенном дровнике. 12 кВт, встроенный шибер, вторичный дожиг, «чистое стекло», режим гриль/барбекю, поленья до 50 см.',
            'content' => $this->news($url),
            'cover_image' => $cover,
            'images' => json_encode(array_slice($images, 0, 4), JSON_UNESCAPED_SLASHES),
            'tags' => json_encode(['ЭкоКамин', 'АЛЯСКА', 'чугунный камин', 'камин на дровнике', 'камин с грилем', 'отопительный камин', 'новинка'], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'published_at' => $now,
            'meta_title' => 'Камин ЭкоКамин АЛЯСКА — новинка 2026 с чугунной топкой | KOTLOV.BY',
            'meta_description' => 'Новинка ЭкоКамин АЛЯСКА: чугунная топка с двойными стенками на дровнике, 12 кВт, шибер, вторичный дожиг, гриль/барбекю, длительное горение, патрубок 180 мм. Доставка по Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('home:data');
        Cache::forget('home:data:v5');
    }

    public function down(): void
    {
        // Keep the published post and the card.
    }

    private function card(string $url): string
    {
        $news = '/blog/'.self::NEWS_SLUG;

        return <<<HTML
<p><strong>ЭкоКамин АЛЯСКА</strong> — отопительный камин 2026 года с чугунной топочной камерой на встроенном дровнике. Строгий прямоугольный корпус, большое прямое стекло и открытая ниша для дров снизу: камин выглядит законченно без облицовки и не требует отдельной поленницы рядом.</p>
<h2>Чем хорош</h2>
<ul>
<li><strong>Чугунная топка с двойными стенками.</strong> Чугун долго накапливает тепло и отдаёт его ровно, двойные стенки берегут корпус от перегрева и продлевают срок службы.</li>
<li><strong>12 кВт</strong> — хватит, чтобы отапливать гостиную, дачу или дом небольшой площади.</li>
<li><strong>Встроенная шиберная заслонка</strong> — тягу регулируют на самом камине, отдельный шибер на дымоходе не нужен.</li>
<li><strong>Вторичный дожиг и «чистое стекло»</strong>: газы догорают в топке, на стекле меньше копоти.</li>
<li><strong>Режим гриль/барбекю</strong> — в топке можно готовить на решётке.</li>
<li><strong>Длительное горение</strong> и колосниковое горение, загрузка поленьев длиной до 50 см.</li>
<li>Подача воздуха извне — патрубок 80 мм, дымоход 180 мм с верхним подключением.</li>
</ul>
<h2>Размеры и монтаж</h2>
<p>Ширина 734 мм, высота 1094 мм, глубина 485 мм, вес 196 кг. Камин ставят на негорючее основание; дымоход — диаметром 180 мм. Поможем рассчитать дымоход и подобрать комплект для установки.</p>
<p>Подробнее о новинке — в новости <a href="{$news}">«Камин ЭкоКамин АЛЯСКА с чугунной топкой»</a>.</p>
HTML;
    }

    private function news(string $url): string
    {
        return <<<HTML
<p>ЭкоКамин представил новинку 2026 года — <strong>камин АЛЯСКА</strong> с чугунной топочной камерой. Это отопительный камин «всё в одном»: топка, корпус и дровник в одном изделии, поэтому его можно поставить у стены без облицовки и сразу подключить к дымоходу.</p>
<h2>Главное о модели</h2>
<ul>
<li><strong>Чугунная топочная камера</strong> с <strong>двойными чугунными стенками</strong> — чугун держит тепло и прогревает комнату ровно и долго.</li>
<li><strong>Мощность 12 кВт</strong>, дрова, колосниковое горение.</li>
<li><strong>Встроенная шиберная заслонка</strong> для регулировки тяги.</li>
<li><strong>Вторичный дожиг</strong> и система <strong>«чистое стекло»</strong>.</li>
<li><strong>Система гриль/барбекю</strong> и <strong>режим длительного горения</strong>.</li>
<li><strong>Встроенный дровник</strong> под топкой, поленья — до 50 см.</li>
<li>Патрубок дымохода <strong>180 мм</strong>, подключение сверху, подача воздуха извне — 80 мм.</li>
<li>Габариты 734 × 1094 × 485 мм, вес 196 кг, большое прямое стекло из термостойкой стеклокерамики (до 750 °C).</li>
</ul>
<h2>Кому подойдёт</h2>
<p>АЛЯСКА рассчитана на тех, кому камин нужен и для тепла, и для уюта: в загородный дом, на дачу, в гостиную с панорамными окнами. Чугунная топка выдерживает ежедневную топку, а встроенный шибер и длительное горение позволяют реже подкладывать дрова. Режим гриль/барбекю превращает камин в место для готовки зимой.</p>
<h2>Что учесть при установке</h2>
<p>Для камина нужен дымоход диаметром 180 мм и негорючее основание под ним. Подачу воздуха извне (80 мм) удобно предусмотреть сразу — в доме с хорошей вентиляцией и герметичными окнами это особенно важно. Наши специалисты рассчитают дымоход и подберут комплект для монтажа.</p>
<p><a href="{$url}">Камин ЭкоКамин АЛЯСКА</a> — цена, характеристики и наличие на странице товара. Доставка по всей Беларуси, монтаж — по запросу.</p>
HTML;
    }
};
