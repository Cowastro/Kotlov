<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('categories')
            ->where('slug', 'schetchiki-gaza')
            ->update([
                'h1' => 'Газовые счетчики для квартиры и дома',
                'content' => '<p>Газовые счетчики для квартиры и частного дома: диафрагменные и ультразвуковые модели БелОМО. Подбор по типоразмеру G1,6–G10, направлению потока и монтажной длине.</p>',
                'meta_title' => 'Газовые счетчики — купить в Минске, цены | KOTLOV',
                'meta_keywords' => 'газовый счетчик купить, счетчик газа Минск, счетчик газа для квартиры, газовые счетчики БелОМО',
                'meta_description' => 'Купить газовый счетчик в Минске: бытовые и электронные модели БелОМО для квартиры и дома. Цены, характеристики, подбор и доставка по Беларуси.',
                'updated_at' => $now,
            ]);

        DB::table('brands')
            ->where('slug', 'belomo')
            ->update([
                'h1' => 'Газовые счетчики БелОМО',
                'content' => <<<'HTML'
<h2>Газовые счетчики БелОМО</h2>
<p>БелОМО — белорусский производитель приборов учета газа. В каталоге KOTLOV представлены бытовые диафрагменные счетчики серий СГД и СГМН, а также ультразвуковые модели СКАТ для разных диапазонов расхода.</p>
<h2>Как выбрать счетчик БелОМО</h2>
<p>Сначала определите требуемый типоразмер по суммарному максимальному расходу газовой плиты, котла и другого оборудования. Затем сверьте левое или правое направление потока, межосевое расстояние 110, 200 или 250 мм и необходимость термокомпенсатора. Точную модификацию следует согласовать с проектом и обслуживающей газоснабжающей организацией.</p>
<h2>Серии в каталоге</h2>
<ul>
<li><strong>СГД</strong> — диафрагменные счетчики для бытового учета газа;</li>
<li><strong>СГМН</strong> — модели с различным направлением подключения и монтажной длиной;</li>
<li><strong>СКАТ</strong> — ультразвуковые счетчики для более высоких расходов газа.</li>
</ul>
<p>Перед заказом менеджер поможет сверить исполнение, цену и комплект документов конкретной модели. Монтаж и ввод прибора в эксплуатацию выполняются уполномоченной организацией.</p>
HTML,
                'meta_title' => 'Газовые счетчики БелОМО — купить в Беларуси | KOTLOV',
                'meta_keywords' => 'БелОМО счетчики газа, счетчик газа БелОМО, СГД, СГМН, СКАТ',
                'meta_description' => 'Газовые счетчики БелОМО: серии СГД, СГМН и СКАТ. Цены, характеристики, подбор исполнения и доставка по Беларуси.',
                'updated_at' => $now,
            ]);

        $categoryId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        $brandId = DB::table('brands')->where('slug', 'belomo')->value('id');

        if (! $categoryId || ! $brandId) {
            return;
        }

        DB::table('products')
            ->where('category_id', $categoryId)
            ->where('brand_id', $brandId)
            ->where('is_archived', false)
            ->orderBy('id')
            ->get(['id', 'name', 'short_description', 'content', 'meta_title', 'meta_description'])
            ->each(function (object $product) use ($now): void {
                $name = trim((string) $product->name);
                $isUltrasonic = Str::contains(mb_strtolower($name), ['ультразвуковой', 'скат']);
                $type = $isUltrasonic ? 'ультразвуковой' : 'диафрагменный';
                $details = $this->modelDetails($name);
                $selection = implode(', ', array_filter($details));

                $short = $name.' — '.$type.' счетчик газа БелОМО'.($selection !== '' ? '. '.$selection.'.' : '.');
                $content = '<p><strong>'.e($name).'</strong> — '.($isUltrasonic ? 'ультразвуковой' : 'диафрагменный').' прибор учета газа производства БелОМО.</p>'
                    .'<p>'.($selection !== '' ? 'Основные параметры исполнения: '.e($selection).'. ' : '')
                    .'Перед покупкой сверьте максимальный расход подключенного оборудования, направление потока, монтажные размеры и требования проекта.</p>'
                    .'<p>Актуальную цену, наличие и комплект документов подтвердит менеджер. Установку, пломбирование и ввод в эксплуатацию выполняет уполномоченная организация.</p>';

                $updates = ['updated_at' => $now];

                if ($this->isBlank($product->short_description)) {
                    $updates['short_description'] = Str::limit($short, 250, '');
                }
                if ($this->isBlank($product->content) || Str::contains((string) $product->content, 'Актуализировано по каталогу elicon.by')) {
                    $updates['content'] = $content;
                }
                if ($this->isBlank($product->meta_title) || Str::contains((string) $product->meta_title, 'купить в Минске')) {
                    $updates['meta_title'] = Str::limit($name.' БелОМО — купить в Минске | KOTLOV', 70, '');
                }
                if ($this->isBlank($product->meta_description) || Str::contains((string) $product->meta_description, 'Актуальная цена')) {
                    $updates['meta_description'] = Str::limit($name.' БелОМО: цена, характеристики и подбор исполнения. Доставка по Беларуси.', 170, '');
                }

                if (count($updates) > 1) {
                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        $brandId = DB::table('brands')->where('slug', 'belomo')->value('id');

        if ($categoryId && $brandId) {
            DB::table('products')
                ->where('category_id', $categoryId)
                ->where('brand_id', $brandId)
                ->where('short_description', 'like', '%счетчик газа БелОМО%')
                ->update(['short_description' => null]);

            DB::table('products')
                ->where('category_id', $categoryId)
                ->where('brand_id', $brandId)
                ->where('content', 'like', '%прибор учета газа производства БелОМО%')
                ->update(['content' => null]);

            DB::table('products')
                ->where('category_id', $categoryId)
                ->where('brand_id', $brandId)
                ->where('meta_title', 'like', '%БелОМО — купить в Минске | KOTLOV')
                ->update(['meta_title' => null]);

            DB::table('products')
                ->where('category_id', $categoryId)
                ->where('brand_id', $brandId)
                ->where('meta_description', 'like', '%БелОМО: цена, характеристики и подбор исполнения.%')
                ->update(['meta_description' => null]);
        }

        DB::table('categories')->where('slug', 'schetchiki-gaza')->update([
            'h1' => 'Счетчики газа',
            'content' => null,
            'meta_title' => null,
            'meta_keywords' => null,
            'meta_description' => null,
        ]);

        DB::table('brands')->where('slug', 'belomo')->update([
            'h1' => 'БелОМО',
            'content' => null,
            'meta_title' => null,
            'meta_keywords' => null,
            'meta_description' => null,
        ]);
    }

    private function isBlank(?string $value): bool
    {
        return trim((string) $value) === '';
    }

    /** @return list<string> */
    private function modelDetails(string $name): array
    {
        $details = [];

        if (preg_match('/\bG\s?(1[,.]6|2[,.]5|4|6|10)\b/ui', $name, $match)) {
            $details[] = 'типоразмер G'.str_replace('.', ',', $match[1]);
        }
        if (preg_match('/\b(левый|правый)\b/ui', $name, $match)) {
            $details[] = mb_strtolower($match[1]).' поток';
        }
        if (preg_match('/L\s*=\s*(110|200|250)/ui', $name, $match)) {
            $details[] = 'монтажная длина '.$match[1].' мм';
        }
        if (Str::contains(mb_strtolower($name), 'термокомпенсатор')) {
            $details[] = 'с термокомпенсатором';
        }

        return $details;
    }
};
