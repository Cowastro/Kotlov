<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'kaminnye-topki-ecokamin-realnye-proekty-i-vybor';

    private const PORTFOLIO_URL = 'https://www.ecokamin.ru/portfolio/';

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('brands')) {
            return;
        }

        $brand = DB::table('brands')
            ->where('slug', 'ecokamin')
            ->orWhere('name', 'ЭкоКамин')
            ->first();

        if (! $brand) {
            return;
        }

        $manifest = $this->manifest();
        $portfolioProductIds = [];

        foreach ($manifest as $sourceId => $entry) {
            $productIds = $this->productIdsForSource((string) $sourceId);
            foreach ($productIds as $productId) {
                $portfolioProductIds[(int) $productId] = true;
                $this->enrichProductWithPortfolio((int) $productId, $entry);
            }
        }

        DB::table('products')
            ->where('brand_id', $brand->id)
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($portfolioProductIds): void {
                foreach ($products as $product) {
                    $this->appendRepresentativeBlock(
                        (int) $product->id,
                        isset($portfolioProductIds[(int) $product->id]),
                    );
                }
            }, 'id');

        $this->updateBrandPage((int) $brand->id);
        $this->publishFireboxArticle();
        $this->linkPortfolioArticles();

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // Published editorial galleries are intentionally durable.
    }

    private function manifest(): array
    {
        $path = resource_path('data/ecokamin-portfolio.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function productIdsForSource(string $sourceId): array
    {
        $ids = collect();
        $pattern = '%/'.$sourceId.'/%';

        if (Schema::hasColumn('products', 'content_source_url')) {
            $ids = $ids->merge(
                DB::table('products')
                    ->where('content_source_url', 'like', $pattern)
                    ->pluck('id'),
            );
        }

        if (Schema::hasTable('supplier_products')) {
            $ids = $ids->merge(
                DB::table('supplier_products')
                    ->whereNotNull('product_id')
                    ->where('source_url', 'like', $pattern)
                    ->pluck('product_id'),
            );
        }

        return $ids->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
    }

    private function enrichProductWithPortfolio(int $productId, array $entry): void
    {
        $product = DB::table('products')->where('id', $productId)->first(['id', 'name', 'images', 'content']);
        if (! $product) {
            return;
        }

        $portfolioImages = collect($entry['images'] ?? [])->filter(fn ($image): bool => is_string($image))->values()->all();
        if ($portfolioImages === []) {
            return;
        }

        $decoded = is_string($product->images) ? json_decode($product->images, true) : $product->images;
        $images = collect(is_array($decoded) ? $decoded : [])
            ->reject(fn ($image): bool => is_string($image) && str_contains($image, 'img/products/ecokamin/portfolio-full/'))
            ->merge($portfolioImages)
            ->filter(fn ($image): bool => is_string($image) && trim($image) !== '')
            ->unique()
            ->values()
            ->all();

        $content = (string) ($product->content ?? '');
        $marker = 'data-ecokamin-full-portfolio="'.e((string) $entry['source_id']).'"';
        if (! str_contains($content, $marker)
            && ! str_contains($content, 'data-ecokamin-portfolio=')) {
            $content = rtrim($content)."\n\n".$this->portfolioBlock($entry, (string) $product->name);
        }

        DB::table('products')->where('id', $productId)->update([
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    private function portfolioBlock(array $entry, string $productName): string
    {
        $sourceId = e((string) $entry['source_id']);
        $sourceName = e((string) ($entry['name'] ?? $productName));
        $copy = e($this->seriesCopy((string) ($entry['name'] ?? $productName)));
        $projectCount = max(1, (int) ($entry['project_count'] ?? 1));
        $figures = collect($entry['images'] ?? [])
            ->map(function (string $image, int $index) use ($productName): string {
                $number = $index + 1;

                return '<figure><img loading="lazy" src="/'.e($image).'" alt="'.e($productName).' в интерьере — реальный проект, фото '.$number.'"><figcaption>Реальный объект из портфолио производителя ЭкоКамин.</figcaption></figure>';
            })
            ->implode("\n");

        return <<<HTML
<section data-ecokamin-full-portfolio="{$sourceId}">
<h2>{$sourceName}: реальные проекты</h2>
<p>{$copy}</p>
<p>В галерее собраны реальные установки этой модели: проектов в официальном портфолио — {$projectCount}. Фотографии помогают оценить масштаб топки, обзор огня и варианты облицовки до заказа.</p>
{$figures}
<p><small>Источник: <a href="https://www.ecokamin.ru/portfolio/" rel="nofollow noopener" target="_blank">официальное портфолио ЭкоКамин</a>. Облицовка, основание, изоляция и дымоход проектируются индивидуально.</small></p>
<p><a href="/blog/kaminnye-topki-ecokamin-realnye-proekty-i-vybor"><strong>Как выбрать топку ЭкоКамин по реальным проектам →</strong></a></p>
</section>
HTML;
    }

    private function seriesCopy(string $name): string
    {
        $upper = mb_strtoupper($name);

        return match (true) {
            str_contains($upper, 'АЛЬФА') => 'Серия АЛЬФА сочетает лаконичную геометрию и разные варианты остекления. На готовых объектах особенно хорошо видны пропорции портала и расположение конвекционных решёток.',
            str_contains($upper, 'ВЕГА') => 'Серия ВЕГА рассчитана на выразительные широкоформатные композиции. Реальные объекты показывают, как горизонтальное стекло работает в современных минималистичных интерьерах.',
            str_contains($upper, 'ДЕЛЬТА') => 'Серия ДЕЛЬТА предлагает угловые и трёхсторонние решения. По фотографиям можно заранее оценить боковой обзор пламени и место, необходимое под облицовку.',
            str_contains($upper, 'БАВАРИЯ') => 'БАВАРИЯ — практичные печи-камины и готовые камины для дома и дачи. Реальные установки показывают варианты подключения и сочетание корпуса с деревянной отделкой.',
            str_contains($upper, 'РИО') => 'РИО выделяется вертикальным силуэтом и трёхсторонним обзором огня. Реальные фотографии позволяют оценить компактное основание и восприятие модели с разных точек комнаты.',
            str_contains($upper, 'МЮНХЕН') => 'МЮНХЕН — готовый камин с выразительным дымосборником. Примеры демонстрируют пристенное и угловое размещение в деревянных и современных интерьерах.',
            str_contains($upper, 'ЭКЛИПС') => 'ЭКЛИПС создаёт самостоятельный архитектурный акцент и открывает огонь сразу нескольким зонам помещения. Особенно важны грамотное основание и проект дымохода.',
            default => 'Реальные объекты позволяют оценить модель точнее каталожного рендера: видны пропорции, обзор огня и сочетание оборудования с готовой отделкой.',
        };
    }

    private function appendRepresentativeBlock(int $productId, bool $hasPortfolio): void
    {
        $product = DB::table('products')->where('id', $productId)->first(['content']);
        if (! $product) {
            return;
        }

        $content = (string) ($product->content ?? '');
        if (str_contains($content, 'data-ecokamin-belarus-showcase')) {
            return;
        }

        $portfolioSentence = $hasPortfolio
            ? 'Для этой модели в карточке опубликованы реальные примеры установок.'
            : 'Если для конкретной модели ещё нет опубликованного объекта, менеджер покажет близкие решения той же серии и уточнит комплектацию.';

        $content = rtrim($content)."\n\n".<<<HTML
<section data-ecokamin-belarus-showcase>
<h2>ЭкоКамин в Беларуси — подбор и комплектация KOTLOV.BY</h2>
<p><strong>KOTLOV.BY представляет продукцию ЭкоКамин в Беларуси.</strong> Мы помогаем сравнить серии, подобрать топку или готовый камин под интерьер, проверить технические характеристики, совместимость с дымоходом и состав поставки.</p>
<p>{$portfolioSentence} Бренд — ЭкоКамин; поставщик и импортёр — ООО «СанБизнесГруп».</p>
<ul>
<li>подбор по мощности, формату и количеству стёкол;</li>
<li>проверка документации и актуальной комплектации;</li>
<li>подбор дымохода и консультация по условиям монтажа;</li>
<li>доставка по Минску и всей Беларуси.</li>
</ul>
<p><a href="/brands/ecokamin"><strong>Вся витрина ЭкоКамин в Беларуси →</strong></a></p>
</section>
HTML;

        DB::table('products')->where('id', $productId)->update([
            'content' => $content,
            'updated_at' => now(),
        ]);
    }

    private function updateBrandPage(int $brandId): void
    {
        $content = <<<'HTML'
<section data-ecokamin-brand-showcase>
<figure><img loading="eager" src="/img/products/ecokamin/portfolio-full/ecokamin-firebox-real-hero-retouched.webp" alt="Трёхсторонняя каминная топка ЭкоКамин в реализованном интерьере"><figcaption>Реальный объект с трёхсторонней топкой ЭкоКамин. Кадр подготовлен для сайта без изменения конструкции изделия.</figcaption></figure>

<h2>ЭкоКамин в Беларуси: топки и камины с реальными примерами</h2>
<p><strong>KOTLOV.BY представляет продукцию ЭкоКамин в Беларуси.</strong> Наша задача — показать покупателю не только каталожный товар, но и то, как топка или камин выглядит после монтажа. В карточках собраны технические характеристики, документы, видео и фотографии реализованных объектов.</p>
<p>Поставщик и импортёр продукции — ООО «СанБизнесГруп». Мы проверяем актуальность моделей и комплектаций, помогаем выбрать оборудование и подобрать совместимую дымоходную систему.</p>

<h2>Каминные топки АЛЬФА, ВЕГА и ДЕЛЬТА</h2>
<p><strong>АЛЬФА</strong> — универсальная серия с прямым, угловым и трёхсторонним остеклением. <strong>ВЕГА</strong> подходит для широких современных порталов. <strong>ДЕЛЬТА</strong> ориентирована на панорамные и угловые решения, где огонь должен быть виден из нескольких зон.</p>
<figure><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5488-19146-1.webp" alt="Угловая топка ЭкоКамин АЛЬФА 1000 LB в реальном интерьере"></figure>
<figure><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5513-18507-3.webp" alt="Широкоформатная топка ЭкоКамин ВЕГА в готовом камине"></figure>
<figure><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5544-18656-1.webp" alt="Трёхсторонняя топка ЭкоКамин ДЕЛЬТА 800 B в интерьере"></figure>

<h2>Готовые камины и печи-камины</h2>
<p>В каталоге представлены серии РИО, МЮНХЕН, МАДРИД, РОДОС, ПРАГА, ЭКЛИПС, ШАМАН и БАВАРИЯ. Для выбора важны не только мощность и КПД, но и точки обзора, габариты, материал корпуса, подключение дымохода и безопасные расстояния.</p>
<figure><img loading="lazy" src="/img/products/ecokamin/portfolio-full/17944-19441-1.webp" alt="Камин ЭкоКамин РИО с огнём — реальный объект"></figure>
<figure><img loading="lazy" src="/img/products/ecokamin/portfolio-full/6012-18518-1.webp" alt="Печь-камин ЭкоКамин БАВАРИЯ ОПТИМА в деревянном доме"></figure>

<h2>Почему реальное портфолио важно при выборе</h2>
<ul>
<li>показывает истинные пропорции камина относительно помещения;</li>
<li>помогает выбрать фронтальное, угловое или трёхстороннее стекло;</li>
<li>даёт идеи для облицовки, основания и размещения дров;</li>
<li>позволяет заранее обсудить дымоход, приточный воздух и теплоизоляцию.</li>
</ul>
<p><a href="/blog/kaminnye-topki-ecokamin-realnye-proekty-i-vybor"><strong>Большой гид по топкам ЭкоКамин и реальным проектам →</strong></a></p>
<p><a href="/blog/realnye-kaminy-ecokamin-v-interere-portfolio"><strong>Посмотреть портфолио готовых каминов →</strong></a></p>
<p><small>Фотографии реализованных объектов получены из официального портфолио производителя ЭкоКамин и оптимизированы для быстрой загрузки.</small></p>
</section>
HTML;

        DB::table('brands')->where('id', $brandId)->update([
            'h1' => 'ЭкоКамин в Беларуси — топки, печи и готовые камины',
            'producer' => '<p><strong>KOTLOV.BY — представитель продукции ЭкоКамин в Беларуси.</strong> Реальные проекты, технический подбор, дымоходы и доставка.</p>',
            'content' => $content,
            'meta_title' => 'ЭкоКамин в Беларуси — топки и камины, реальные фото | KOTLOV.BY',
            'meta_keywords' => 'ЭкоКамин Беларусь, каминные топки ЭкоКамин, камины ЭкоКамин, Альфа, Вега, Дельта, Рио, Бавария',
            'meta_description' => 'Витрина ЭкоКамин в Беларуси: топки АЛЬФА, ВЕГА, ДЕЛЬТА, готовые камины и печи. Реальные фото проектов, характеристики, подбор и доставка KOTLOV.BY.',
            'is_featured' => true,
            'updated_at' => now(),
        ]);
    }

    private function publishFireboxArticle(): void
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
                'title' => 'Каминные топки ЭкоКамин: реальные проекты АЛЬФА, ВЕГА и ДЕЛЬТА',
                'excerpt' => 'Большой гид по каминным топкам ЭкоКамин: чем отличаются серии АЛЬФА, ВЕГА и ДЕЛЬТА, какое остекление выбрать и как модели выглядят после монтажа.',
                'content' => $this->articleContent(),
                'cover_image' => 'img/products/ecokamin/portfolio-full/ecokamin-firebox-real-hero-retouched.webp',
                'images' => json_encode($this->articleImages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['ЭкоКамин', 'каминные топки', 'АЛЬФА', 'ВЕГА', 'ДЕЛЬТА', 'реальные проекты', 'камины в интерьере'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now->copy()->subMinute(),
                'meta_title' => 'Топки ЭкоКамин АЛЬФА, ВЕГА, ДЕЛЬТА — реальные фото и выбор',
                'meta_description' => 'Сравнение топок ЭкоКамин АЛЬФА, ВЕГА и ДЕЛЬТА: остекление, формат, монтаж и реальные фотографии готовых проектов. Каталог и подбор в Беларуси.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function articleImages(): array
    {
        return [
            'img/products/ecokamin/portfolio-full/ecokamin-firebox-real-hero-retouched.webp',
            'img/products/ecokamin/portfolio-full/5488-19146-1.webp',
            'img/products/ecokamin/portfolio-full/5502-18661-1.webp',
            'img/products/ecokamin/portfolio-full/5513-18507-3.webp',
            'img/products/ecokamin/portfolio-full/5522-18750-1.webp',
            'img/products/ecokamin/portfolio-full/5544-18656-1.webp',
            'img/products/ecokamin/portfolio-full/5547-19208-2.webp',
        ];
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>Каминную топку лучше выбирать не только по таблице характеристик.</strong> Реальные объекты показывают, как выглядит огонь с разных точек, сколько места занимает облицовка и какие пропорции получает готовый камин. Мы собрали проекты из официального портфолио ЭкоКамин и сопоставили их с моделями нашего каталога.</p>

<div class="blog-image-2"><img loading="eager" src="/img/products/ecokamin/portfolio-full/ecokamin-firebox-real-hero-retouched.webp" alt="Трёхсторонняя каминная топка ЭкоКамин в современном интерьере"><p><small>Реальная установка топки ЭкоКамин. При подготовке кадра удалена только дата съёмки; конструкция изделия не изменялась.</small></p></div>

<div class="kotlov-article-note"><strong>KOTLOV.BY представляет продукцию ЭкоКамин в Беларуси.</strong> Поставщик и импортёр — ООО «СанБизнесГруп». Мы помогаем проверить комплектацию, подобрать дымоход и сопоставить выбранную модель с условиями объекта.</div>

<h2>Три основные серии: АЛЬФА, ВЕГА и ДЕЛЬТА</h2>
<p class="text text-body-1"><strong>АЛЬФА</strong> — наиболее универсальная линейка. В ней есть прямые, угловые и трёхсторонние версии, модели с разной шириной стекла и варианты футеровки. Это хороший выбор, когда нужен аккуратный современный портал без избыточной массивности.</p>
<p class="text text-body-1"><strong>ВЕГА</strong> ориентирована на широкоформатный огонь. Такие топки особенно выразительны в горизонтальных композициях, просторных гостиных и минималистичных интерьерах.</p>
<p class="text text-body-1"><strong>ДЕЛЬТА</strong> предлагает панорамные решения с угловым и трёхсторонним остеклением. Серия подходит, когда камин должен быть виден из нескольких функциональных зон.</p>

<h2>АЛЬФА: прямое, угловое и трёхстороннее стекло</h2>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5488-19146-1.webp" alt="Угловая топка ЭкоКамин АЛЬФА 1000 LB в каменной облицовке"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5502-18661-1.webp" alt="Топка ЭкоКамин АЛЬФА 700 LB в современном интерьере"></div>
<p class="text text-body-1">Буквы L и R обычно указывают сторону бокового стекла. До заказа важно смотреть на камин из основной зоны отдыха: левое и правое исполнение нельзя выбирать только по фотографии товара. Трёхсторонняя версия открывает огонь шире, но требует особенно точной привязки облицовки и конвекционных каналов.</p>

<h2>ВЕГА: широкий огонь и строгая архитектура</h2>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5513-18507-3.webp" alt="Широкая топка ЭкоКамин ВЕГА 1000 в готовом портале"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5522-18750-1.webp" alt="Топка ЭкоКамин ВЕГА 800 B в облицовке из тёмного камня"></div>
<p class="text text-body-1">Широкое стекло хорошо работает в вытянутой композиции, под телевизионной зоной или в отдельном каминном простенке. При проектировании учитывают не только ширину дверцы, но и полный корпус, точки обслуживания, высоту основания и расположение дымохода.</p>

<h2>ДЕЛЬТА: панорамные угловые решения</h2>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5544-18656-1.webp" alt="Трёхсторонняя топка ЭкоКамин ДЕЛЬТА 800 B — реальный интерьер"></div>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/portfolio-full/5547-19208-2.webp" alt="Угловая топка ЭкоКамин ДЕЛЬТА 800 LB с огнём"></div>
<p class="text text-body-1">Угловое и трёхстороннее остекление делает огонь частью сразу нескольких зон комнаты. Но прозрачные боковые плоскости требуют аккуратной облицовки: все примыкания и пропорции заметнее, чем у классической фронтальной топки.</p>

<h2>Что проверить до покупки</h2>
<ul>
<li><strong>Тепловую мощность:</strong> её сопоставляют с теплопотерями дома, а не только с площадью помещения.</li>
<li><strong>Дымоход:</strong> диаметр, высоту, трассу, проходы через перекрытия и возможность обслуживания.</li>
<li><strong>Приточный воздух:</strong> в герметичном доме отдельная подача воздуха часто критически важна.</li>
<li><strong>Остекление:</strong> фронтальное, левое, правое или трёхстороннее выбирают по реальным точкам обзора.</li>
<li><strong>Облицовку:</strong> заранее определяют негорючие материалы, вентиляционные решётки и ревизионный доступ.</li>
</ul>

<h2>Почему карточки KOTLOV.BY полезнее обычного каталога</h2>
<p class="text text-body-1">Мы добавили реальные проекты непосредственно в карточки соответствующих моделей. Каталожное изображение помогает узнать товар, а фотографии установок — понять масштаб, варианты отделки и характер живого огня. При этом реальный объект не является универсальной монтажной схемой: окончательное решение рассчитывается для конкретного дома.</p>
<p class="text text-body-1"><a href="/brands/ecokamin"><strong>Перейти в полную витрину ЭкоКамин в Беларуси →</strong></a></p>
<p class="text text-body-1"><a href="/blog/realnye-kaminy-ecokamin-v-interere-portfolio"><strong>Посмотреть готовые камины ЭкоКамин в интерьерах →</strong></a></p>
<p class="text text-body-1"><small>Оригинальные фотографии: официальное портфолио производителя ЭкоКамин. Изображения оптимизированы для быстрой загрузки; кадры с крупными водяными знаками сторонних монтажных организаций не использованы.</small></p>
HTML;
    }

    private function linkPortfolioArticles(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        $post = DB::table('blog_posts')->where('slug', 'realnye-kaminy-ecokamin-v-interere-portfolio')->first(['id', 'content']);
        if (! $post || str_contains((string) $post->content, 'data-ecokamin-firebox-guide-link')) {
            return;
        }

        $content = rtrim((string) $post->content).<<<'HTML'

<p data-ecokamin-firebox-guide-link><a href="/blog/kaminnye-topki-ecokamin-realnye-proekty-i-vybor"><strong>Читайте также: большой гид по топкам ЭкоКамин АЛЬФА, ВЕГА и ДЕЛЬТА →</strong></a></p>
HTML;

        DB::table('blog_posts')->where('id', $post->id)->update([
            'content' => $content,
            'updated_at' => now(),
        ]);
    }
};
