<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ЭкоКамин ПРАЙМ, the new series of fireboxes: ВЕГА ПРАЙМ 1000 (straight glass) and ДЕЛЬТА ПРАЙМ 1000 / 1100 (three
 * glasses), cards made from ecokamin.ru by supplier:sync-ecokamin-fireboxes. Every card gets its own description and the
 * «новинка» mark; ДЕЛЬТА ПРАЙМ 1000 is three-sided (ecokamin.ru says «односторонняя»). A news article and a hero
 * banner on the home page present the series.
 */
return new class extends Migration
{
    private const ARTICLE_SLUG = 'ekokamin-praim-novye-topki-vega-i-delta';

    private const BANNER_IMAGE = 'banners/ecokamin-prime-launch';

    /** slug => [model, variant letters]. */
    private const CARDS = [
        'topka-vega-praim-1000-siber-vstroennyi-ramka-dekorativnaia' => ['vega', ''],
        'topka-vega-praim-1000-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot' => ['vega', 'B'],
        'topka-vega-praim-1000-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia' => ['vega', 'P'],
        'topka-vega-praim-1000-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot' => ['vega', 'PB'],
        'topka-delta-praim-1000-tri-stekla-siber-vstroennyi-ramka-dekorativnaia' => ['delta', ''],
        'topka-delta-praim-1000b-tri-stekla-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot' => ['delta', 'B'],
        'topka-delta-praim-1000p-tri-stekla-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia' => ['delta', 'P'],
        'topka-delta-praim-1000pb-tri-stekla-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot' => ['delta', 'PB'],
        'topka-delta-praim-1100-tri-stekla-futerovka-cugun-siber-vstroennyi-ramka-dekorativnaia' => ['delta1100', ''],
    ];

    public function up(): void
    {
        $now = now();
        $kindAttribute = DB::table('attributes')->where('name', 'Вид топки')->value('id');

        foreach (self::CARDS as $slug => [$model, $variant]) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'specs']);
            if (! $product) {
                continue;
            }
            $title = $this->title($model, $variant);
            $update = [
                'h1' => $title,
                'content' => $this->cardContent($model, $variant),
                'short_description' => $this->shortDescription($model, $variant),
                'meta_title' => $title.' — купить в %city% | KOTLOV.BY',
                'meta_description' => $this->shortDescription($model, $variant),
                'is_new' => true,
                'updated_at' => $now,
            ];
            if ($model === 'delta') {
                $specs = json_decode((string) $product->specs, true);
                if (is_array($specs)) {
                    foreach ($specs as $key => $row) {
                        if ($key === 'Вид топки') {
                            $specs[$key] = 'трехсторонняя';
                        } elseif (is_array($row) && ($row['key'] ?? null) === 'Вид топки') {
                            $specs[$key]['value'] = 'трехсторонняя';
                        }
                    }
                    $update['specs'] = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if ($kindAttribute) {
                    DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $kindAttribute)
                        ->update(['value' => 'трехсторонняя', 'updated_at' => $now]);
                }
            }
            DB::table('products')->where('id', $product->id)->update($update);
        }

        DB::table('blog_categories')->updateOrInsert(['slug' => 'kaminy-i-topki'], [
            'name' => 'Камины и топки', 'sort_order' => 30, 'is_active' => true, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');

        DB::table('blog_posts')->updateOrInsert(['slug' => self::ARTICLE_SLUG], [
            'category_id' => $categoryId,
            'author_id' => null,
            'title' => 'Новинка ЭкоКамин: топки ВЕГА ПРАЙМ 1000 и ДЕЛЬТА ПРАЙМ 1000 / 1100',
            'excerpt' => 'Новая серия каминных топок ЭкоКамин ПРАЙМ: ВЕГА ПРАЙМ 1000 с прямым стеклом и трёхсторонние ДЕЛЬТА ПРАЙМ 1000 и 1100 с чугунной топочной камерой. 18 кВт, вертикальный подъём дверцы, встроенный шибер и подача воздуха извне.',
            'content' => $this->articleContent(),
            'cover_image' => 'img/blog/ecokamin-prime/delta-prime-1100.webp',
            'images' => json_encode([
                'img/blog/ecokamin-prime/vega-prime-1000.webp',
                'img/blog/ecokamin-prime/delta-prime-1000.webp',
                'img/blog/ecokamin-prime/delta-prime-1100.webp',
            ], JSON_UNESCAPED_SLASHES),
            'tags' => json_encode(['ЭкоКамин', 'ЭкоКамин ПРАЙМ', 'ВЕГА ПРАЙМ 1000', 'ДЕЛЬТА ПРАЙМ 1000', 'ДЕЛЬТА ПРАЙМ 1100',
                'каминная топка', 'топка с подъёмной дверцей', 'трёхсторонняя топка', 'чугунная топка'], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'published_at' => $now,
            'meta_title' => 'ЭкоКамин ПРАЙМ: топки ВЕГА ПРАЙМ 1000, ДЕЛЬТА ПРАЙМ 1000 и 1100 — новинка | KOTLOV.BY',
            'meta_description' => 'Новые каминные топки ЭкоКамин ПРАЙМ в Беларуси: ВЕГА ПРАЙМ 1000 и трёхсторонние ДЕЛЬТА ПРАЙМ 1000/1100. 18 кВт, подъёмная дверца, шибер, воздух извне, белый или чёрный шамот, подовое или колосниковое горение.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('banners')->updateOrInsert(['image' => self::BANNER_IMAGE, 'position' => 'hero'], [
            'title' => 'Топки ЭкоКамин ПРАЙМ',
            'subtitle' => 'Новинка ЭкоКамин',
            'description' => 'ВЕГА ПРАЙМ 1000 с прямым стеклом и трёхсторонние ДЕЛЬТА ПРАЙМ 1000 и 1100: вертикальный подъём дверцы, встроенный шибер, воздух извне, белый или чёрный шамот.',
            'image_mobile' => null,
            'link' => '/blog/'.self::ARTICLE_SLUG,
            'button_text' => 'Смотреть новинки',
            'sort_order' => -210,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('banners')->where('image', self::BANNER_IMAGE)->where('position', 'hero')->delete();
        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->delete();
        DB::table('products')->whereIn('slug', array_keys(self::CARDS))->update(['is_new' => false]);
        Cache::forget('home:data');
    }

    private function title(string $model, string $variant): string
    {
        return match ($model) {
            'vega' => 'Каминная топка ЭкоКамин ВЕГА ПРАЙМ 1000'.$variant,
            'delta' => 'Каминная топка ЭкоКамин ДЕЛЬТА ПРАЙМ 1000'.$variant.' (три стекла)',
            default => 'Каминная топка ЭкоКамин ДЕЛЬТА ПРАЙМ 1100 (три стекла, чугунная камера)',
        };
    }

    /** What the letters of the model mean: P — подовое горение, B — чёрный шамот. */
    private function variantText(string $model, string $variant): string
    {
        if ($model === 'delta1100') {
            return 'подовое горение и футеровка из чугуна';
        }
        $burning = str_contains($variant, 'P') ? 'подовое горение (дрова лежат на поду)' : 'колосниковое горение (колосниковая решётка и зольный ящик)';
        $lining = str_contains($variant, 'B') ? 'чёрный литьевой шамот' : 'светлый литьевой шамот';

        return $burning.', '.$lining;
    }

    private function shortDescription(string $model, string $variant): string
    {
        return match ($model) {
            'vega' => 'Новинка ЭкоКамин ПРАЙМ: топка ВЕГА ПРАЙМ 1000'.$variant.' с прямым стеклом, 18 кВт, вертикальный подъём дверцы, встроенный шибер, подача воздуха извне; '.$this->variantText($model, $variant).'.',
            'delta' => 'Новинка ЭкоКамин ПРАЙМ: трёхсторонняя топка ДЕЛЬТА ПРАЙМ 1000'.$variant.', 18 кВт, вертикальный подъём дверцы, встроенный шибер, подача воздуха извне; '.$this->variantText($model, $variant).'.',
            default => 'Новинка ЭкоКамин ПРАЙМ: трёхсторонняя топка ДЕЛЬТА ПРАЙМ 1100 с чугунной топочной камерой, 18 кВт, подовое горение, дверца 1103 мм с вертикальным подъёмом, встроенный шибер.',
        };
    }

    private function cardContent(string $model, string $variant): string
    {
        $variantText = $this->variantText($model, $variant);
        [$intro, $sizes, $glass] = match ($model) {
            'vega' => [
                'ВЕГА ПРАЙМ 1000'.$variant.' — фронтальная топка новой серии ЭкоКамин ПРАЙМ с прямым стеклом шириной под метр. Это классический «телевизор» для камина в гостиной: большое окно на огонь, вертикально поднимающаяся дверца и аккуратная декоративная рамка без лишних деталей.',
                '1039 × 1706 × 699 мм (ширина × высота × глубина)', 'прямое (фронтальное) стекло',
            ],
            'delta' => [
                'ДЕЛЬТА ПРАЙМ 1000'.$variant.' — трёхсторонняя топка новой серии ЭкоКамин ПРАЙМ: огонь виден спереди и с двух боков. Такая топка ставится в выступающий портал или в перегородку между зонами и становится центром интерьера.',
                '1244 × 1678 × 736 мм (ширина × высота × глубина)', 'три стекла: фронтальное и два боковых',
            ],
            default => [
                'ДЕЛЬТА ПРАЙМ 1100 — флагман серии ЭкоКамин ПРАЙМ: трёхсторонняя топка с чугунной топочной камерой и дверцей шириной 1103 мм. Чугун держит и отдаёт тепло дольше шамота и не боится ударов поленьев.',
                '1330 × 1648 × 726 мм (ширина × высота × глубина), масса 420 кг', 'три стекла: фронтальное и два боковых',
            ],
        };

        return <<<HTML
<p><strong>{$intro}</strong></p>
<h2>Исполнение этой модели</h2>
<p>В этой версии — {$variantText}. Серия ПРАЙМ выпускается в нескольких вариантах: с белой или чёрной футеровкой и с колосниковым или подовым горением, поэтому топку легко подобрать под стиль интерьера и привычный способ топки.</p>
<h2>Что есть в каждой топке ПРАЙМ</h2>
<ul>
<li>Вертикальный подъём дверцы с максимальным открыванием — удобно закладывать дрова и чистить стекло.</li>
<li>Встроенная шиберная заслонка и чугунный патрубок дымохода Ø 250 мм.</li>
<li>Подача воздуха извне (Ø 150 мм) — топка не забирает воздух из комнаты.</li>
<li>Система вторичного дожига и «чистое стекло» — меньше дыма и копоти.</li>
<li>Передовая система управления огнём, монтажная бленда и декоративная рамка.</li>
<li>Футеровка из 3D-ламелей литьевого шамота (у ДЕЛЬТА ПРАЙМ 1100 — чугун).</li>
<li>Три конвекционных канала и регулируемые по высоте ножки.</li>
</ul>
<h2>Основные характеристики</h2>
<ul>
<li>Тепловая мощность — 18 кВт, топливо — дрова.</li>
<li>Остекление — {$glass}, термостойкая стеклокерамика (до 750 °C).</li>
<li>Габариты — {$sizes}.</li>
<li>Подключение дымохода — верхнее, Ø 250 мм.</li>
<li>Производитель — ЭкоКамин (Россия), поставщик в Беларуси — ООО «СанБизнесГруп».</li>
</ul>
<h2>Подбор и монтаж</h2>
<p>Специалисты KOTLOV.BY помогут выбрать исполнение, рассчитают дымоход Ø 250 мм, подскажут материалы для облицовки и безопасные расстояния, организуют доставку по Минску и всей Беларуси. Наличие и срок поставки подтвердит менеджер после оформления заказа.</p>
HTML;
    }

    private function articleContent(): string
    {
        $link = fn (string $slug, string $text): string => '<a href="/topki/'.$slug.'"><strong>'.$text.'</strong></a>';
        $vega = $link('topka-vega-praim-1000-siber-vstroennyi-ramka-dekorativnaia', 'ВЕГА ПРАЙМ 1000');
        $vegaP = $link('topka-vega-praim-1000-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia', 'ВЕГА ПРАЙМ 1000P');
        $vegaB = $link('topka-vega-praim-1000-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot', 'ВЕГА ПРАЙМ 1000B');
        $vegaPB = $link('topka-vega-praim-1000-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot', 'ВЕГА ПРАЙМ 1000PB');
        $delta = $link('topka-delta-praim-1000-tri-stekla-siber-vstroennyi-ramka-dekorativnaia', 'ДЕЛЬТА ПРАЙМ 1000');
        $deltaP = $link('topka-delta-praim-1000p-tri-stekla-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia', 'ДЕЛЬТА ПРАЙМ 1000P');
        $deltaB = $link('topka-delta-praim-1000b-tri-stekla-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot', 'ДЕЛЬТА ПРАЙМ 1000B');
        $deltaPB = $link('topka-delta-praim-1000pb-tri-stekla-podovoe-gorenie-siber-vstroennyi-ramka-dekorativnaia-cernyi-samot', 'ДЕЛЬТА ПРАЙМ 1000PB');
        $delta1100 = $link('topka-delta-praim-1100-tri-stekla-futerovka-cugun-siber-vstroennyi-ramka-dekorativnaia', 'ДЕЛЬТА ПРАЙМ 1100');

        return <<<HTML
<p class="text text-body-1"><strong>ЭкоКамин выпустил новую серию каминных топок ПРАЙМ.</strong> В неё вошли фронтальная ВЕГА ПРАЙМ 1000 и трёхсторонние ДЕЛЬТА ПРАЙМ 1000 и ДЕЛЬТА ПРАЙМ 1100 с чугунной топочной камерой. Все новинки уже есть в каталоге KOTLOV.BY — их можно заказать с подбором дымохода и доставкой по Беларуси.</p>

<div class="blog-image-2">
    <img loading="eager" width="739" height="965" src="/img/blog/ecokamin-prime/delta-prime-1100.webp" alt="Трёхсторонняя каминная топка ЭкоКамин ДЕЛЬТА ПРАЙМ 1100 с чугунной камерой">
</div>

<h2>Что общего у топок серии ПРАЙМ</h2>

<p class="text text-body-1">Серия ПРАЙМ — это топки мощностью 18 кВт с вертикальным подъёмом дверцы и максимальным открыванием. В каждую встроены шиберная заслонка и чугунный патрубок Ø 250 мм, есть подача воздуха извне Ø 150 мм, вторичный дожиг и система «чистое стекло». Монтажная бленда и декоративная рамка входят в комплект, футеровка — 3D-ламели из литьевого шамота.</p>

<div class="kotlov-article-note">
    <strong>Коротко о серии</strong>
    18 кВт · вертикальный подъём дверцы · встроенный шибер · воздух извне · вторичный дожиг · белый или чёрный шамот · подовое или колосниковое горение.
</div>

<h2>ВЕГА ПРАЙМ 1000 — фронтальная топка с прямым стеклом</h2>

<div class="blog-image-2">
    <img loading="lazy" width="684" height="1045" src="/img/blog/ecokamin-prime/vega-prime-1000.webp" alt="Каминная топка ЭкоКамин ВЕГА ПРАЙМ 1000 с прямым стеклом">
</div>

<p class="text text-body-1">Классическая топка-«телевизор» с большим прямым стеклом для камина в гостиной. Габариты 1039 × 1706 × 699 мм. Доступны четыре исполнения: {$vega} — колосниковое горение и светлый шамот, {$vegaP} — подовое горение, {$vegaB} — чёрный шамот, {$vegaPB} — подовое горение и чёрный шамот.</p>

<h2>ДЕЛЬТА ПРАЙМ 1000 — огонь с трёх сторон</h2>

<div class="blog-image-2">
    <img loading="lazy" width="762" height="970" src="/img/blog/ecokamin-prime/delta-prime-1000.webp" alt="Трёхсторонняя каминная топка ЭкоКамин ДЕЛЬТА ПРАЙМ 1000">
</div>

<p class="text text-body-1">Три стекла — фронтальное и два боковых — открывают огонь почти со всех сторон, поэтому ДЕЛЬТА ставится в выступающий портал или в перегородку между кухней и гостиной. Габариты 1244 × 1678 × 736 мм. Те же четыре исполнения: {$delta}, {$deltaP} (подовое горение), {$deltaB} (чёрный шамот) и {$deltaPB}.</p>

<h2>ДЕЛЬТА ПРАЙМ 1100 — чугунная топочная камера</h2>

<p class="text text-body-1">{$delta1100} — флагман серии. Топочная камера и футеровка из чугуна, подовое горение, дверца шириной 1103 мм и разводка тёплого воздуха 2 × 125 мм. Габариты 1330 × 1648 × 726 мм, масса 420 кг. Чугун дольше держит тепло и не боится ударов поленьев.</p>

<h2>Как выбрать исполнение</h2>

<ul>
<li><strong>Колосниковое горение</strong> (модели без буквы P) — дрова лежат на решётке, воздух подходит снизу, зола уходит в ящик: быстрый розжиг и простая уборка.</li>
<li><strong>Подовое горение</strong> (P) — дрова горят на поду, пламя ровнее и «живее», как в открытом камине.</li>
<li><strong>Чёрный шамот</strong> (B) — топка смотрится глубже и контрастнее, копоть на футеровке почти не видна; белый шамот подчёркивает игру огня.</li>
</ul>

<h2>Заказ и монтаж</h2>

<p class="text text-body-1">Все модели ПРАЙМ поставляются в Беларусь через официального представителя ЭкоКамин — ООО «СанБизнесГруп». Актуальная цена указана в карточке каждой модели, наличие и срок поставки подтвердит менеджер. Специалисты KOTLOV.BY помогут подобрать дымоход Ø 250 мм, материалы для облицовки и организуют монтаж.</p>

<p class="text text-body-1">Другие модели можно сравнить в разделе <a href="/topki"><strong>каминных топок</strong></a>, а дымоход — в разделе <a href="/dymohody"><strong>дымоходных систем</strong></a>.</p>
HTML;
    }
};
