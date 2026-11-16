<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Novelty ЭкоКамин АЛЬФА 1000 T — the tunnel firebox (both cards were made from ecokamin.ru by
 * supplier:sync-ecokamin-fireboxes): a news post, the «новинка» mark, and fixes of the maker's page data
 * that contradict the maker's own banner (it is a two-sided tunnel firebox with a built-in damper, not «односторонняя»).
 */
return new class extends Migration
{
    private const NEWS_SLUG = 'novinka-ekokamin-alfa-1000-t-tunnelnaya-topka';

    private const CARDS = [
        'kaminnaia-topka-ekokamin-alfa-1000-t-tunnelnaia-podovoe-gorenie',
        'kaminnaia-topka-ekokamin-alfa-1000-tb-tunnelnaia-podovoe-gorenie-cernyi-samot',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::CARDS as $slug) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'specs', 'content', 'short_description']);
            if (! $product) {
                continue;
            }
            $specs = collect(json_decode((string) $product->specs, true) ?: [])
                ->reject(fn ($row) => in_array(trim((string) ($row['key'] ?? '')), ['Главная'], true))
                ->map(function ($row) {
                    $fix = [
                        'Вид топки' => 'туннельная (двусторонняя, два стекла)',
                        'Открывание дверцы' => 'Боковое, обе дверцы',
                        'Встроенная шиберная заслонка' => 'Да',
                        'Система подачи воздуха на горение' => 'Подовая, регулируется заслонкой',
                    ];
                    $key = trim((string) ($row['key'] ?? ''));
                    if (isset($fix[$key])) {
                        $row['value'] = $fix[$key];
                    }

                    return $row;
                })->values()->all();
            $texts = fn (?string $text) => str_replace(
                ['односторонним остеклением', 'одностороннее остекление', 'одностороннего остекления', 'односторонняя'],
                ['двусторонним остеклением', 'двустороннее остекление', 'двустороннего остекления', 'двусторонняя'],
                (string) $text
            );
            $content = $texts($product->content);
            if (! str_contains($content, self::NEWS_SLUG)) {
                $content .= "\n\n".'<p>Подробнее о новинке — в новости <a href="/blog/'.self::NEWS_SLUG.'">«АЛЬФА 1000 T: туннельная топка ЭкоКамин»</a>.</p>';
            }
            DB::table('products')->where('id', $product->id)->update([
                'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                'content' => $content,
                'short_description' => $texts($product->short_description),
                'is_new' => true,
                'updated_at' => $now,
            ]);
            try {
                app(\App\Services\ProductSourceEnricher::class)->syncSpecsToAttributeValues(\App\Models\Product::query()->find($product->id));
            } catch (\Throwable) {
                // The card shows specs from the JSON; filter attributes are refreshed by the next sync.
            }
        }

        DB::table('blog_categories')->updateOrInsert(['slug' => 'kaminy-i-topki'], [
            'name' => 'Камины и топки', 'sort_order' => 30, 'is_active' => true, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');
        $cover = 'img/blog/ecokamin-alfa/alfa-1000t-tunnelnaya-novinka.webp';

        DB::table('blog_posts')->updateOrInsert(['slug' => self::NEWS_SLUG], [
            'category_id' => $categoryId,
            'author_id' => null,
            'title' => 'Новинка 2026: туннельная топка ЭкоКамин АЛЬФА 1000 T',
            'excerpt' => 'В линейке АЛЬФА появилась туннельная топка: огонь виден с двух сторон, две боковые дверцы, 14 кВт, подовое горение, вторичный дожиг, «чистое стекло», встроенный шибер и патрубок 250 мм. Две версии — светлый и чёрный шамот.',
            'content' => $this->content(),
            'cover_image' => $cover,
            'images' => json_encode([$cover], JSON_UNESCAPED_SLASHES),
            'tags' => json_encode(['ЭкоКамин', 'АЛЬФА 1000 T', 'туннельная топка', 'двусторонняя топка', 'каминная топка',
                'топка для камина', 'новинка'], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'published_at' => $now,
            'meta_title' => 'Туннельная топка ЭкоКамин АЛЬФА 1000 T — новинка 2026 | KOTLOV.BY',
            'meta_description' => 'Новинка ЭкоКамин АЛЬФА 1000 T: туннельная каминная топка, огонь с двух сторон, 14 кВт, подовое горение, вторичный дожиг, шибер, патрубок 250 мм. Светлый и чёрный шамот, доставка по Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('home:data');
        Cache::forget('home:data:v5');
    }

    public function down(): void
    {
        // Keep the published post and the card fixes.
    }

    private function content(): string
    {
        $t = '/topki/'.self::CARDS[0];
        $tb = '/topki/'.self::CARDS[1];

        return <<<HTML
<p>ЭкоКамин расширил серию АЛЬФА: вышла <strong>туннельная каминная топка АЛЬФА 1000 T</strong>. У неё две стеклянные дверцы — спереди и сзади, поэтому огонь виден с обеих сторон. Такую топку ставят в проём между комнатами или в центр зала, и камин становится живой перегородкой между гостиной и столовой.</p>
<h2>Главное о модели</h2>
<ul>
<li><strong>Мощность 14 кВт</strong>, КПД 80 %.</li>
<li><strong>Две боковые дверцы</strong> — обе открываются вбок, загружать дрова удобно с любой стороны. Размер проёма дверцы 970 × 618 мм.</li>
<li><strong>Подовое горение</strong>: воздух подаётся под дрова, они горят ровно и полностью.</li>
<li><strong>Вторичный дожиг</strong> и система <strong>«чистое стекло»</strong> — меньше дыма и копоти на стёклах.</li>
<li><strong>Встроенная шиберная заслонка</strong> — тягу можно регулировать без отдельного шибера на дымоходе.</li>
<li>Патрубок дымохода <strong>250 мм</strong>, подключение сверху, подача воздуха извне — патрубок 150 мм.</li>
<li>Длительное горение до 6 часов, два конвекционных канала, регулируемые ножки (+50 мм).</li>
<li>Габариты 1206 × 1262 × 521 мм, вес 290 кг. Гарантия производителя — 5 лет.</li>
</ul>
<h2>Две версии</h2>
<p><a href="{$t}">АЛЬФА 1000 T</a> (артикул TAN1000TP) — со светлым литьевым шамотом: классический вид, пламя на светлом фоне выглядит ярче.</p>
<p><a href="{$tb}">АЛЬФА 1000 TB</a> (артикул TAN1000TPB) — с чёрным шамотом: строгий современный вид, на тёмной футеровке меньше заметна копоть.</p>
<h2>Что учесть при установке</h2>
<p>Туннельной топке нужен проём в стене или перегородке и облицовка с двух сторон. Дымоход — диаметром 250 мм. Для подачи воздуха на горение удобно сразу предусмотреть канал извне 150 мм: в доме с хорошей вентиляцией и закрытыми окнами это особенно важно. Поможем рассчитать дымоход и подобрать облицовку.</p>
<p>Топки ЭкоКамин сейчас продаются по <a href="/akcii/ecokamin-skidki">акции производителя</a> — до 30 ноября 2026 года новинка доступна со скидкой 20 %. Доставка по всей Беларуси, монтаж — по запросу.</p>
HTML;
    }
};
