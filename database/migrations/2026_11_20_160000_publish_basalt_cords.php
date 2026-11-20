<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Basalt cords 25 m from the 1C stock (УТ-00003973 / 00007764 / 00007086): the warehouse photos, the brand on
 * the label (6 mm — Везувий, 8 and 10 mm — Литком), a description, and the cards go on sale (retail was set
 * by hand: 51 / 82 / 104 BYN).
 */
return new class extends Migration
{
    private const CARDS = [
        'snur-bazaltovyi-6-mm-25-mp' => ['brand' => 'Везувий', 'mm' => 6, 'image' => 'img/products/bazalt-shnur/vezuvii-6-mm-25-m.jpg', 'barcode' => '4610094711346'],
        'snur-bazaltovyi-8-mm-25-mp' => ['brand' => 'Литком', 'mm' => 8, 'image' => 'img/products/bazalt-shnur/litkom-8-mm-25-m.jpg', 'barcode' => null],
        'snur-bazaltovyi-10-mm-25-mp' => ['brand' => 'Литком', 'mm' => 10, 'image' => 'img/products/bazalt-shnur/litkom-10-mm-25-m.jpg', 'barcode' => null],
    ];

    public function up(): void
    {
        foreach (self::CARDS as $slug => $card) {
            $product = DB::table('products')->where('slug', $slug)->first(['id']);
            if (! $product) {
                continue;
            }

            $name = 'Шнур базальтовый '.$card['brand'].' '.$card['mm'].' мм, 25 м';
            $specs = array_values(array_filter([
                ['key' => 'Материал', 'value' => 'базальтовое волокно'],
                ['key' => 'Диаметр', 'value' => $card['mm'].' мм'],
                ['key' => 'Длина', 'value' => '25 м (катушка)'],
                ['key' => 'Производитель', 'value' => $card['brand']],
                ['key' => 'Страна производства', 'value' => 'Россия'],
                $card['barcode'] ? ['key' => 'Штрихкод', 'value' => $card['barcode']] : null,
            ]));

            DB::table('products')->where('id', $product->id)->update([
                'name' => $name,
                'h1' => $name,
                'brand_id' => $this->brandId($card['brand']),
                'images' => json_encode([$card['image']], JSON_UNESCAPED_SLASHES),
                'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                'short_description' => 'Плетёный базальтовый шнур диаметром '.$card['mm'].' мм для уплотнения дверок и стёкол печей, каминов и котлов. Катушка 25 м.',
                'content' => $this->content($card['brand'], $card['mm']),
                'meta_title' => $name.' — купить в Минске | KOTLOV.BY',
                'meta_description' => $name.': уплотнительный шнур для дверок печей, каминов и котлов, катушка 25 м. В наличии, доставка по Беларуси.',
                'is_active' => true,
                'created_via' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The cards keep their content.
    }

    private function brandId(string $name): int
    {
        $id = DB::table('brands')->where('name', $name)->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function content(string $brand, int $mm): string
    {
        return <<<HTML
<p><strong>Шнур базальтовый {$brand} {$mm} мм</strong> — плетёный уплотнительный шнур из базальтового волокна для печей, каминов, банных печей и твердотопливных котлов. Поставляется катушкой 25 м: хватит на несколько дверок и на запас для замены.</p>
<h2>Где применяется</h2>
<ul>
<li>уплотнение топочных и зольных дверок печей и каминов;</li>
<li>уплотнение стёкол каминных топок и дверец;</li>
<li>дверцы и люки твердотопливных и пеллетных котлов;</li>
<li>заделка зазоров в местах прилегания чугунного и стального литья.</li>
</ul>
<h2>Как подобрать диаметр</h2>
<p>Диаметр шнура подбирают по ширине паза на дверке: шнур должен входить в паз плотно и немного выступать над ним, чтобы дверка прижимала его по всему периметру. Старый шнур перед заменой удаляют, паз зачищают, новый шнур укладывают на термостойкий клей.</p>
HTML;
    }
};
