<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PORTFOLIO_URL = 'https://www.ecokamin.ru/portfolio/';

    private const ARTICLE_SLUG = 'realnye-kaminy-ecokamin-v-interere-portfolio';

    private const ITEMS = [
        [
            'key' => 'rio',
            'source_id' => '17944',
            'slug' => 'kamin-ecokamin-rio-kr206',
            'name_like' => '%РИО%KR206%',
            'title' => 'ЭкоКамин РИО в готовом интерьере',
            'description' => 'Реальная установка показывает главное достоинство РИО: высокий огонь хорошо виден через фронтальное и боковые стёкла, а компактное основание визуально не перегружает помещение.',
            'images' => [
                'img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp',
                'img/products/ecokamin/portfolio/rio-portfolio-1.webp',
                'img/products/ecokamin/portfolio/rio-portfolio-2.webp',
                'img/products/ecokamin/portfolio/rio-portfolio-3.webp',
                'img/products/ecokamin/portfolio/rio-portfolio-4.webp',
            ],
        ],
        [
            'key' => 'alpha-800-lb',
            'source_id' => '16200',
            'slug' => 'kaminnaya-topka-ekokamin-alfa-800-lb-dva-stekla-levaya-chernyiy-shamot',
            'name_like' => '%АЛЬФА 800 LB%два стекла%лев%',
            'title' => 'Реализованный проект с топкой АЛЬФА 800 LB',
            'description' => 'Угловое остекление встроено в облицовку под тёмный камень. Такой пример помогает оценить обзор пламени сбоку и заранее продумать положение топки относительно зоны отдыха.',
            'images' => [
                'img/products/ecokamin/portfolio/alpha-800-lb-1.webp',
                'img/products/ecokamin/portfolio/alpha-800-lb-2.webp',
            ],
        ],
        [
            'key' => 'alpha-700-three-glass',
            'source_id' => '16798',
            'slug' => null,
            'name_like' => '%АЛЬФА 700%три стекла%черн%шамот%',
            'title' => 'АЛЬФА 700 с трёхсторонним остеклением в интерьере',
            'description' => 'Трёхсторонняя топка встроена в центральную облицовку и сохраняет обзор огня из нескольких зон комнаты. На реальном объекте хорошо видны пропорции готового каминного короба.',
            'images' => [
                'img/products/ecokamin/portfolio/alpha-700-three-glass-1.webp',
                'img/products/ecokamin/portfolio/alpha-700-three-glass-2.webp',
            ],
        ],
        [
            'key' => 'munich-corner-graphite',
            'source_id' => '8864',
            'slug' => null,
            'name_like' => '%МЮНХЕН%ПРИСТЕННО-УГЛОВОЙ%графит%',
            'title' => 'Камин МЮНХЕН графит в деревянном доме',
            'description' => 'Готовая установка демонстрирует пристенно-угловую компоновку, высокий дымоходный короб и сочетание графитового корпуса с деревянной отделкой помещения.',
            'images' => [
                'img/products/ecokamin/portfolio/munich-corner-graphite-1.webp',
                'img/products/ecokamin/portfolio/munich-corner-graphite-2.webp',
                'img/products/ecokamin/portfolio/munich-corner-graphite-3.webp',
                'img/products/ecokamin/portfolio/munich-corner-graphite-4.webp',
            ],
        ],
        [
            'key' => 'delta-800-three-glass',
            'source_id' => '5544',
            'slug' => null,
            'name_like' => '%ДЕЛЬТА 800 B%три стекла%черн%шамот%',
            'title' => 'Монтаж топки ДЕЛЬТА 800 B три стекла',
            'description' => 'Фотографии с этапа монтажа показывают трёхстороннее остекление до чистовой отделки. Это полезный пример для понимания габаритов топки, основания и будущей облицовки.',
            'images' => [
                'img/products/ecokamin/portfolio/delta-800-three-glass-1.webp',
                'img/products/ecokamin/portfolio/delta-800-three-glass-2.webp',
                'img/products/ecokamin/portfolio/delta-800-three-glass-3.webp',
                'img/products/ecokamin/portfolio/delta-800-three-glass-4.webp',
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach (self::ITEMS as $item) {
            $products = DB::table('products')
                ->where(function ($query) use ($item): void {
                    $query->where('content_source_url', 'like', '%/'.$item['source_id'].'/%')
                        ->orWhere('name', 'like', $item['name_like']);

                    if ($item['slug']) {
                        $query->orWhere('slug', $item['slug']);
                    }
                })
                ->get(['id', 'name', 'images', 'content']);

            foreach ($products as $product) {
                $images = $this->mergeImages($product->images, $item['images']);
                $content = (string) ($product->content ?? '');
                $marker = 'data-ecokamin-portfolio="'.$item['key'].'"';

                if (! str_contains($content, $marker)) {
                    $content = rtrim($content)."\n\n".$this->productPortfolioBlock($item, (string) $product->name);
                }

                DB::table('products')->where('id', $product->id)->update([
                    'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'content' => $content,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->expandRioArticle();
        $this->publishPortfolioArticle();

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // Published marketing content and galleries are intentionally durable.
    }

    private function mergeImages(mixed $current, array $portfolioImages): array
    {
        $decoded = is_string($current) ? json_decode($current, true) : $current;
        $images = is_array($decoded) ? $decoded : [];

        return collect([...$images, ...$portfolioImages])
            ->filter(fn ($image): bool => is_string($image) && trim($image) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function productPortfolioBlock(array $item, string $productName): string
    {
        $figures = collect($item['images'])
            ->take(4)
            ->map(function (string $image, int $index) use ($productName): string {
                $number = $index + 1;

                return '<figure><img loading="lazy" src="/'.e($image).'" alt="'.e($productName).' — реальный пример установки, фото '.$number.'"><figcaption>Реальный объект из портфолио производителя.</figcaption></figure>';
            })
            ->implode("\n");

        return <<<HTML
<section data-ecokamin-portfolio="{$item['key']}">
<h2>{$item['title']}</h2>
<p>{$item['description']}</p>
{$figures}
<p><small>Источник фотографий: <a href="https://www.ecokamin.ru/portfolio/" rel="nofollow noopener" target="_blank">официальное портфолио ЭкоКамин</a>. Фотографии показывают примеры реализованных объектов; состав облицовки и монтажное решение проектируются индивидуально.</small></p>
<p><a href="/blog/realnye-kaminy-ecokamin-v-interere-portfolio"><strong>Смотреть другие реальные проекты ЭкоКамин →</strong></a></p>
</section>
HTML;
    }

    private function expandRioArticle(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        $article = DB::table('blog_posts')->where('slug', 'kaminy-ecokamin-rio-obzor-treh-modeley')->first();
        if (! $article) {
            return;
        }

        $content = (string) $article->content;
        if (! str_contains($content, 'data-rio-real-installation')) {
            $content .= <<<'HTML'

<section data-rio-real-installation>
<h2>Реальный камин РИО в интерьере</h2>
<p class="text text-body-1">Фотографии из официального портфолио ЭкоКамин позволяют оценить серию не только по каталожным рендерам. На готовом объекте видно, как компактный РИО KR206 смотрится рядом с панорамным окном, сколько пространства занимает основание и какой обзор огня дают три стекла.</p>

<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp" alt="Реальный установленный камин ЭкоКамин РИО с огнём"><p><small>Реальная установка РИО. Для публикации аккуратно очищены только строительные детали по краю кадра; конструкция камина не изменялась.</small></p></div>

<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/rio-portfolio-2.webp" alt="Камин ЭкоКамин РИО — вид трёхстороннего остекления в интерьере"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/rio-portfolio-3.webp" alt="Работающий камин ЭкоКамин РИО — боковой обзор пламени"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/rio-portfolio-4.webp" alt="ЭкоКамин РИО на компактном основании — реальный объект"></div>

<p class="text text-body-1">Объект подтверждает сильную сторону конструкции: огонь остаётся заметным при взгляде спереди и сбоку. При выборе места важно оставить безопасные расстояния, предусмотреть негорючее основание, подачу воздуха и дымоход по проекту.</p>
<p class="text text-body-1"><small>Источник оригинальных фотографий: официальное портфолио производителя ЭкоКамин.</small></p>
</section>
HTML;
        }

        $images = $this->mergeImages($article->images, [
            'img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp',
            'img/products/ecokamin/portfolio/rio-portfolio-1.webp',
            'img/products/ecokamin/portfolio/rio-portfolio-2.webp',
            'img/products/ecokamin/portfolio/rio-portfolio-3.webp',
            'img/products/ecokamin/portfolio/rio-portfolio-4.webp',
        ]);

        DB::table('blog_posts')->where('id', $article->id)->update([
            'content' => $content,
            'cover_image' => 'img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp',
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_description' => 'Подробный обзор ЭкоКамин РИО KR206, KR221 и KR224G: отличия моделей, характеристики, монтаж, видео и реальные фото камина с огнём в интерьере.',
            'updated_at' => now(),
        ]);
    }

    private function publishPortfolioArticle(): void
    {
        if (! Schema::hasTable('blog_posts') || ! Schema::hasTable('blog_categories')) {
            return;
        }

        $now = now();
        DB::table('blog_categories')->updateOrInsert(
            ['slug' => 'kaminy-i-topki'],
            ['name' => 'Камины и топки', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        );
        $categoryId = DB::table('blog_categories')->where('slug', 'kaminy-i-topki')->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::ARTICLE_SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'Реальные камины ЭкоКамин в интерьере: РИО, АЛЬФА, МЮНХЕН и ДЕЛЬТА',
                'excerpt' => 'Не рендеры, а реальные объекты: показываем, как камины и топки ЭкоКамин выглядят после установки, и объясняем, что учитывать при выборе модели и облицовки.',
                'content' => $this->portfolioArticleContent(),
                'cover_image' => 'img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp',
                'images' => json_encode($this->articleImages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['ЭкоКамин', 'портфолио', 'камины в интерьере', 'РИО', 'АЛЬФА', 'МЮНХЕН', 'ДЕЛЬТА'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now->copy()->subMinute(),
                'meta_title' => 'Камины ЭкоКамин в интерьере — реальные фото РИО, АЛЬФА, МЮНХЕН и ДЕЛЬТА',
                'meta_description' => 'Реальные фотографии установленных каминов и топок ЭкоКамин: РИО, АЛЬФА 700 и 800, МЮНХЕН, ДЕЛЬТА. Примеры облицовки и советы по выбору.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function articleImages(): array
    {
        return collect(self::ITEMS)
            ->flatMap(fn (array $item): array => $item['images'])
            ->unique()
            ->values()
            ->all();
    }

    private function portfolioArticleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>Каталожная фотография показывает форму изделия, но только реальный объект помогает понять масштаб, обзор пламени и сочетание камина с отделкой.</strong> Мы отобрали проекты из официального портфолио ЭкоКамин и сопоставили их с моделями нашего каталога.</p>

<div class="kotlov-article-note"><strong>Важно:</strong> это примеры реализованных объектов из портфолио производителя, а не универсальные монтажные схемы. Основание, изоляция, дымоход, приточный воздух и безопасные расстояния рассчитываются для конкретного дома.</div>

<h2>ЭкоКамин РИО: компактный камин с тремя стёклами</h2>
<p class="text text-body-1">РИО привлекает высоким пламенем и трёхсторонним обзором. На реальном объекте компактная центральная опора оставляет силуэт визуально лёгким, а боковые стёкла открывают огонь из соседних зон комнаты.</p>
<div class="blog-image-2"><img loading="eager" src="/img/products/ecokamin/portfolio/rio-portfolio-hero-retouched.webp" alt="Реальный камин ЭкоКамин РИО с огнём в интерьере"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/rio-portfolio-3.webp" alt="Боковой обзор огня через стекло камина ЭкоКамин РИО"></div>
<p class="text text-body-1"><a href="/blog/kaminy-ecokamin-rio-obzor-treh-modeley"><strong>Подробный обзор трёх моделей ЭкоКамин РИО →</strong></a></p>

<h2>АЛЬФА 800 LB: угловая топка в каменной облицовке</h2>
<p class="text text-body-1">Два стекла позволяют развернуть камин к основной зоне отдыха. Тёмная каменная облицовка на объекте поддерживает геометрию топки, а конвекционные решётки встроены в вертикальный короб.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/alpha-800-lb-1.webp" alt="Топка ЭкоКамин АЛЬФА 800 LB в готовой угловой облицовке"></div>

<h2>АЛЬФА 700 три стекла: огонь как центр комнаты</h2>
<p class="text text-body-1">Трёхстороннее остекление особенно выразительно в центральной или простеночной композиции. Реальные фотографии помогают оценить не только топку, но и пропорции всей облицовки от пола до потолка.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/alpha-700-three-glass-1.webp" alt="ЭкоКамин АЛЬФА 700 три стекла в современном интерьере"></div>

<h2>МЮНХЕН графит: готовый камин для деревянного интерьера</h2>
<p class="text text-body-1">Пристенно-угловая форма подходит для помещений, где важно сохранить проходы и получить обзор огня под углом. Графитовый корпус спокойно сочетается с древесиной и каменной панелью за камином.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/munich-corner-graphite-1.webp" alt="Камин ЭкоКамин МЮНХЕН графит в деревянном доме"></div>

<h2>ДЕЛЬТА 800 B: как выглядит топка на этапе монтажа</h2>
<p class="text text-body-1">Фото до чистовой отделки полезны не меньше готового интерьера: видны основание, положение трёхсторонней топки и объём, который потребуется под облицовку. Финальная конструкция должна соответствовать инструкции производителя и проекту дымохода.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio/delta-800-three-glass-1.webp" alt="Монтаж трёхсторонней топки ЭкоКамин ДЕЛЬТА 800 B"></div>

<h2>Как выбрать модель по реальным фотографиям</h2>
<ul>
    <li><strong>Определите точки обзора:</strong> фронтальная, угловая или трёхсторонняя топка должна быть видна из нужных зон.</li>
    <li><strong>Смотрите на общие пропорции:</strong> учитывайте не только стекло, но и основание, короб и трассу дымохода.</li>
    <li><strong>Не копируйте монтаж вслепую:</strong> расстояния и изоляция зависят от стен, перекрытий и конкретной модели.</li>
    <li><strong>Планируйте приток воздуха:</strong> современная герметичная комната требует отдельного решения для стабильного горения.</li>
</ul>

<p class="text text-body-1">KOTLOV.BY поможет сопоставить понравившийся интерьер с техническими характеристиками модели, подобрать дымоход и проверить условия монтажа. Бренд — ЭкоКамин; поставщик и импортёр в Беларуси — ООО «СанБизнесГруп».</p>
<p class="text text-body-1"><small>Оригинальные фотографии: <a href="https://www.ecokamin.ru/portfolio/" rel="nofollow noopener" target="_blank">официальное портфолио ЭкоКамин</a>. Изображения оптимизированы для быстрой загрузки сайта.</small></p>
HTML;
    }
};
