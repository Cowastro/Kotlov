<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';
    private const START = '<!-- shaman-interior-gallery:start -->';
    private const END = '<!-- shaman-interior-gallery:end -->';

    public function up(): void
    {
        $now = now();
        $product = DB::table('products')->where('slug', self::PRODUCT_SLUG)->first();

        if ($product && ! str_contains((string) $product->content, self::START)) {
            DB::table('products')->where('id', $product->id)->update([
                'content' => $this->insertBefore(
                    (string) $product->content,
                    '<h3>Основные характеристики</h3>',
                    $this->productInteriorSection(),
                ),
                'updated_at' => $now,
            ]);
        }

        $article = DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->first();

        if ($article && ! str_contains((string) $article->content, self::START)) {
            $images = json_decode((string) $article->images, true);
            $images = is_array($images) ? $images : [];

            DB::table('blog_posts')->where('id', $article->id)->update([
                'content' => $this->insertBefore(
                    (string) $article->content,
                    '<h2>Технические характеристики</h2>',
                    $this->articleInteriorSection(),
                ),
                'images' => json_encode(array_values(array_unique([
                    ...$images,
                    'img/banners/ecokamin-shaman/interior-desktop.webp',
                    'img/banners/ecokamin-shaman/interior-mobile.webp',
                ])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        }

        Cache::forget('home:data');
    }

    public function down(): void
    {
        foreach ([
            ['products', self::PRODUCT_SLUG],
            ['blog_posts', self::ARTICLE_SLUG],
        ] as [$table, $slug]) {
            $record = DB::table($table)->where('slug', $slug)->first();
            if (! $record) {
                continue;
            }

            DB::table($table)->where('id', $record->id)->update([
                'content' => preg_replace(
                    '/\s*'.preg_quote(self::START, '/').'.*?'.preg_quote(self::END, '/').'\s*/s',
                    "\n\n",
                    (string) $record->content,
                ),
                'updated_at' => now(),
            ]);
        }
    }

    private function insertBefore(string $content, string $needle, string $section): string
    {
        if (str_contains($content, $needle)) {
            return str_replace($needle, $section."\n\n".$needle, $content);
        }

        return rtrim($content)."\n\n".$section;
    }

    private function productInteriorSection(): string
    {
        return <<<'HTML'
<!-- shaman-interior-gallery:start -->
<h3>ЭкоКамин ШАМАН в интерьере</h3>
<p>Высокий вертикальный корпус позволяет использовать ШАМАН как самостоятельный архитектурный акцент. Тёмный чугун хорошо сочетается с натуральным камнем, деревом и спокойной отделкой стен, а большое стекло делает живой огонь главным элементом гостиной.</p>

<figure><img loading="lazy" width="1536" height="1024" src="/img/banners/ecokamin-shaman/interior-desktop.webp" alt="Чугунный камин ЭкоКамин ШАМАН с огнём в современном интерьере гостиной"><figcaption>Пример размещения ЭкоКамин ШАМАН в современной гостиной: высокий силуэт, верхний дымоход и негорючее основание из натурального камня.</figcaption></figure>

<figure><img loading="lazy" width="1024" height="1536" src="/img/banners/ecokamin-shaman/interior-mobile.webp" alt="Печь-камин ЭкоКамин ШАМАН в интерьере загородного дома"><figcaption>ШАМАН как центральный интерьерный объект. Конкретные отступы, защиту стен и конфигурацию дымохода рассчитывают под помещение.</figcaption></figure>
<!-- shaman-interior-gallery:end -->
HTML;
    }

    private function articleInteriorSection(): string
    {
        return <<<'HTML'
<!-- shaman-interior-gallery:start -->
<h2>Как ЭкоКамин ШАМАН выглядит в интерьере</h2>

<p class="text text-body-1">Главное достоинство этой модели заметно именно в жилом пространстве. Узкий высокий корпус не выглядит тяжёлым, несмотря на 160 кг чугуна, а вертикальное стекло подчёркивает высоту пламени. ШАМАН подходит для интерьеров, где камин должен работать не только как отопительный прибор, но и как самостоятельный композиционный центр.</p>

<div class="blog-image-2"><img loading="lazy" width="1536" height="1024" src="/img/banners/ecokamin-shaman/interior-desktop.webp" alt="ЭкоКамин ШАМАН 14 кВт с горящими дровами в современной гостиной"></div>

<p class="text text-body-1">Матовый антрацитовый чугун особенно выразительно смотрится рядом с тёплым деревом, фактурной штукатуркой и каменным подиумом. При этом интерьерная фотография показывает идею оформления, а не готовую монтажную схему: размеры негорючего основания, проход дымохода и безопасные расстояния необходимо рассчитывать индивидуально.</p>

<div class="blog-image-2"><img loading="lazy" width="1024" height="1536" src="/img/banners/ecokamin-shaman/interior-mobile.webp" alt="Вертикальный чугунный камин ЭкоКамин ШАМАН в загородном доме"></div>

<p class="text text-body-1">За счёт компактного основания модель можно вписать даже в относительно узкую зону гостиной. Важно заранее предусмотреть верхнее подключение дымохода диаметром 180 мм, приток воздуха и защиту ближайших конструкций от нагрева.</p>
<!-- shaman-interior-gallery:end -->
HTML;
    }
};
