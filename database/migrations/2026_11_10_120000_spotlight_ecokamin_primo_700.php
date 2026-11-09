<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ЭкоКамин PRIMO 700 in the spotlight: all the manufacturer's studio photos (ecokamin.ru, item 18097) plus a front
 * shot with the fire set in, the description with the barbecue grill grate that comes as a gift and the selling
 * copy. The card opens the catalogue and the home slider (App\Support\Catalog\SpotlightProducts).
 */
return new class extends Migration
{
    private const SLUG = 'topka-cugunnaia-primo-700';

    private const DIR = 'img/products/ecokamin/primo-700/';

    public function up(): void
    {
        $product = DB::table('products')->where('slug', self::SLUG)->first(['id', 'images']);
        if (! $product) {
            return;
        }

        $images = array_map(fn (string $name): string => self::DIR.$name.'.webp', [
            'primo-700-fire', 'primo-700-front', 'primo-700-grill', 'primo-700-straight',
            'primo-700-angle', 'primo-700-side-door', 'primo-700-top', 'primo-700-back',
        ]);

        DB::table('products')->where('id', $product->id)->update([
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_featured' => true,
            'short_description' => 'Чугунная каминная топка ЭкоКамин PRIMO 700 на 12 кВт в духе знаменитой Invicta Philos: двойные чугунные стенки, вторичный дожиг, «чистое стекло», встроенный шибер, воздух извне и режим гриль-барбекю. Решётка-гриль — в подарок.',
            'content' => $this->content(),
            'meta_title' => 'Чугунная топка ЭкоКамин PRIMO 700 — решётка-гриль в подарок | KOTLOV',
            'meta_description' => 'Каминная топка PRIMO 700 из чугуна, 12 кВт, КПД 76%, поленья до 50 см. Решётка-гриль в подарок, акционная цена, доставка по Беларуси.',
            'updated_at' => now(),
        ]);

        Cache::forget('home:data');
        Cache::forget('home:spotlight-primo-700');
    }

    public function down(): void
    {
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>ЭкоКамин PRIMO 700</strong> — чугунная каминная топка в духе знаменитой французской <strong>Invicta Philos</strong>: та же узнаваемая «куполообразная» чугунная камера с рёбрами, прямое стекло и классический колосниковый режим горения. Топку делает российский завод «ЭкоКамин», поэтому она заметно доступнее европейского оригинала.</p>

<h2>Решётка-гриль — в подарок</h2>
<p class="text text-body-1">С каждой топкой PRIMO 700 идёт <strong>решётка для гриля и барбекю</strong>. Она ставится на кронштейны внутри топки над углями: можно жарить стейки, рыбу, овощи и шашлык прямо у камина, круглый год и в любую погоду. Решётка входит в комплект, без доплаты.</p>

<h2>Что внутри</h2>
<ul>
    <li><strong>двойные чугунные стенки</strong> и чугунная футеровка — топка долго держит и ровно отдаёт тепло;</li>
    <li><strong>вторичный дожиг</strong> — газы догорают в камере, меньше сажи и больше тепла с той же закладки;</li>
    <li><strong>«чистое стекло»</strong> — воздушная завеса не даёт стеклу закоптиться, огонь хорошо видно;</li>
    <li><strong>встроенная шиберная заслонка</strong> и регулировка воздуха на горение — легко держать режим <strong>длительного горения</strong>;</li>
    <li><strong>подача воздуха извне</strong> (патрубок 50 мм) — камин не забирает кислород из комнаты;</li>
    <li>стеклокерамика выдерживает до 750 °C, дверца открывается вбок;</li>
    <li>поленья <strong>до 50 см</strong> — не нужно мелко колоть дрова.</li>
</ul>

<h2>Характеристики</h2>
<ul>
    <li>тепловая мощность — 12 кВт, КПД — 76%;</li>
    <li>топливо — дрова, вид горения — колосниковое;</li>
    <li>дымоход — верхнее подключение, патрубок 180 мм;</li>
    <li>габариты (Ш × В × Г) — 690 × 662 × 452 мм;</li>
    <li>масса — 128 кг, цвет — серый чугун.</li>
</ul>

<h2>Для какого камина</h2>
<p class="text text-body-1">PRIMO 700 ставят в облицовку из кирпича или камня, в готовые порталы и в каминные пристенные конструкции. Компактная ширина 690 мм позволяет вписать топку и в небольшую гостиную, и в беседку или летнюю кухню, где решётка-гриль особенно пригодится.</p>

<h2>Дымоход и монтаж</h2>
<p class="text text-body-1">Подберём дымоход «Теплов и Сухов» на 180 мм, подскажем по отступам, теплоизоляции и негорючему основанию, а при необходимости организуем монтаж. Доставка по Минску и всей Беларуси.</p>
HTML;
    }
};
