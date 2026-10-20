<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RETAIL_RUB = 219992.0;

    private const OLD_RETAIL_RUB = 274990.0;

    private const PURCHASE_RUB = 164994.0;

    private const VIDEO_URL = 'https://www.youtube.com/watch?v=FJdwJ7EbNlo';

    private const ARTICLE_SLUG = 'kaminy-ecokamin-rio-obzor-treh-modeley';

    private const PRODUCTS = [
        [
            'bitrix_id' => '17944',
            'article' => 'KR206',
            'slug' => 'kamin-ecokamin-rio-kr206',
            'name' => 'Камин ЭкоКамин РИО KR206',
            'variant' => 'Без дровника',
            'color' => 'Чёрный',
            'source_url' => 'https://www.ecokamin.ru/catalog/kaminy/kaminy_rio/17944/',
            'images' => [
                'img/products/ecokamin/rio/rio-kr206-1.jpg',
                'img/products/ecokamin/rio/rio-kr206-2.jpg',
                'img/products/ecokamin/rio/rio-kr206-3.jpg',
                'img/products/ecokamin/rio/rio-kr206-4.jpg',
                'img/products/ecokamin/rio/rio-kr206-5.jpg',
                'img/products/ecokamin/rio/rio-kr206-6.jpg',
                'img/products/ecokamin/rio/rio-kr206-7.jpg',
                'img/products/ecokamin/rio/rio-kr206-8.jpg',
            ],
            'dimensions_pdf' => 'docs/ecokamin/rio/rio-kr206-dimensions.pdf',
        ],
        [
            'bitrix_id' => '19383',
            'article' => 'KR221',
            'slug' => 'kamin-ecokamin-rio-na-drovnike-kr221',
            'name' => 'Камин ЭкоКамин РИО на дровнике KR221',
            'variant' => 'Со встроенным дровником',
            'color' => 'Чёрный',
            'source_url' => 'https://www.ecokamin.ru/catalog/kaminy/kaminy_rio/19383/',
            'images' => [
                'img/products/ecokamin/rio/rio-kr221-1.jpg',
                'img/products/ecokamin/rio/rio-kr221-2.jpg',
                'img/products/ecokamin/rio/rio-kr221-3.jpg',
            ],
            'dimensions_pdf' => 'docs/ecokamin/rio/rio-kr221-dimensions.pdf',
        ],
        [
            'bitrix_id' => '19407',
            'article' => 'KR224G',
            'slug' => 'kamin-ecokamin-rio-grafit-na-drovnike-kr224g',
            'name' => 'Камин ЭкоКамин РИО графит на дровнике KR224G',
            'variant' => 'Со встроенным дровником',
            'color' => 'Графит',
            'source_url' => 'https://www.ecokamin.ru/catalog/kaminy/kaminy_rio/19407/',
            'images' => [
                'img/products/ecokamin/rio/rio-kr224g-1.jpg',
                'img/products/ecokamin/rio/rio-kr224g-2.jpg',
                'img/products/ecokamin/rio/rio-kr224g-3.jpg',
            ],
            'dimensions_pdf' => 'docs/ecokamin/rio/rio-kr224g-dimensions.pdf',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('suppliers')) {
            return;
        }

        $now = now();
        $supplier = DB::table('suppliers')->where('code', 'ecokamin')->first();
        $supplierId = $supplier?->id;
        $rate = (float) ($supplier?->currency_rate ?? 0);
        if ($rate <= 0 || $rate >= 0.2) {
            $rate = 0.0361;
        }

        $brandId = DB::table('brands')->where('slug', 'ecokamin')->value('id')
            ?: DB::table('brands')->where('name', 'ЭкоКамин')->value('id');
        $categoryId = DB::table('categories')->where('id', 61)->value('id')
            ?: DB::table('categories')->where('slug', 'pechi-kaminy')->value('id');

        if (! $brandId || ! $categoryId) {
            return;
        }

        $syncId = Schema::hasTable('supplier_syncs')
            ? DB::table('supplier_syncs')->where('key', 'ecokamin_stoves')->value('id')
            : null;

        foreach (self::PRODUCTS as $item) {
            $productId = $this->findProductId($item, $supplierId);
            $payload = $this->productPayload($item, (int) $brandId, (int) $categoryId, $rate, $now);

            if ($productId) {
                $slugOwner = DB::table('products')->where('slug', $item['slug'])->value('id');
                if (! $slugOwner || (int) $slugOwner === $productId) {
                    $payload['slug'] = $item['slug'];
                }
                DB::table('products')->where('id', $productId)->update($payload);
            } else {
                $payload['sku'] = $this->nextSku();
                $payload['slug'] = $item['slug'];
                $payload['created_at'] = $now;
                $productId = (int) DB::table('products')->insertGetId($payload);
            }

            if ($supplierId && Schema::hasTable('supplier_products')) {
                $sku = DB::table('products')->where('id', $productId)->value('sku');
                DB::table('supplier_products')->updateOrInsert(
                    ['supplier_id' => $supplierId, 'supplier_article' => $item['article']],
                    [
                        'supplier_sync_id' => $syncId,
                        'product_id' => $productId,
                        'product_sku' => $sku,
                        'supplier_article_normalized' => $item['article'],
                        'supplier_name' => $item['name'],
                        'source_url' => $item['source_url'],
                        'source_wp_id' => $item['bitrix_id'],
                        'price' => self::PURCHASE_RUB,
                        'currency' => 'RUB',
                        'currency_rate' => $rate,
                        'price_byn' => round(self::PURCHASE_RUB * $rate, 2),
                        'in_stock' => true,
                        'stock_status' => 'in_stock',
                        'stock_text' => 'В наличии у поставщика',
                        'last_stock_synced_at' => $now,
                        'match_status' => 'matched',
                        'match_confidence' => 'manual',
                        'raw' => json_encode([
                            'bitrix_id' => $item['bitrix_id'],
                            'retail_price' => self::RETAIL_RUB,
                            'dealer_discount_percent' => 25,
                            'source' => 'official_ecokamin',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'last_synced_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        $this->publishArticle($now);
        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Published catalog data is intentionally durable.
    }

    private function productPayload(array $item, int $brandId, int $categoryId, float $rate, $now): array
    {
        $withWoodStore = $item['variant'] === 'Со встроенным дровником';
        $baseText = $withWoodStore ? 'со встроенным дровником' : 'на компактном основании без дровника';
        $specs = [
            'Артикул производителя' => $item['article'],
            'Производитель' => 'ЭкоКамин, Россия',
            'Импортер в РБ' => 'ООО «СанБизнесГруп»',
            'Тип' => 'Отопительный камин',
            'Вид топлива' => 'Дрова',
            'Тепловая мощность' => '7 кВт',
            'Форма остекления' => 'Трёхстороннее',
            'Остекление' => 'Термостойкая стеклокерамика до 750 °C',
            'Подключение дымохода' => 'Верхнее',
            'Диаметр дымоходного патрубка' => '180 мм',
            'Подача воздуха извне' => '1 × 150 мм',
            'Система чистое стекло' => 'Да',
            'Система вторичного дожига' => 'Да',
            'Открывание дверцы' => 'Боковое',
            'Вид горения' => 'Подовое',
            'Футеровка' => 'Чугун',
            'Ширина' => '577 мм',
            'Высота' => '1331 мм',
            'Глубина' => '468 мм',
            'Вес' => '154 кг',
            'Цвет' => $item['color'],
            'Исполнение' => $item['variant'],
        ];

        return [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => $item['name'],
            'price' => round(self::RETAIL_RUB * $rate, 2),
            'price_old' => round(self::OLD_RETAIL_RUB * $rate, 2),
            'currency' => 'BYN',
            'short_description' => "{$item['name']} — дровяной камин 7 кВт с высоким трёхсторонним остеклением, подовым горением и чугунной футеровкой. Исполнение {$baseText}.",
            'content' => $this->productContent($item, $baseText),
            'images' => json_encode($item['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'service_info' => json_encode([
                'Производитель' => 'ЭкоКамин, Россия',
                'Импортер в РБ' => 'ООО СанБизнесГруп',
                'Сервисный центр' => 'ООО СанБизнесГруп',
                'Гарантия' => '1 год',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'documents' => json_encode([
                ['label' => 'Габаритный чертёж '.$item['article'].' (PDF)', 'url' => '/'.$item['dimensions_pdf']],
                ['label' => 'Инструкция для печей и каминов ЭкоКамин (PDF)', 'url' => '/docs/ecokamin/rio/ecokamin-stoves-manual.pdf'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'video_url' => self::VIDEO_URL,
            'content_source_url' => $item['source_url'],
            'weight' => 154,
            'unit' => 'шт',
            'warranty' => '1 год',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => true,
            'is_sale' => true,
            'sort_order' => 0,
            'meta_title' => $item['name'].' 7 кВт купить в %city% | KOTLOV.BY',
            'meta_keywords' => 'камин ЭкоКамин РИО, '.$item['article'].', камин три стекла, камин 7 кВт',
            'meta_description' => 'Купить '.$item['name'].' в Беларуси. 7 кВт, трёхстороннее стекло, чугунная футеровка, дымоход 180 мм. Фото, видео, характеристики и доставка.',
            'updated_at' => $now,
        ];
    }

    private function productContent(array $item, string $baseText): string
    {
        $image = '/'.$item['images'][0];

        return <<<HTML
<p><strong>{$item['name']}</strong> — современный отопительный камин с высоким трёхсторонним остеклением и панорамным обзором пламени. Модель выполнена {$baseText}; цвет — {$item['color']}.</p>

<figure><img loading="eager" src="{$image}" alt="{$item['name']} — официальный вид"><figcaption>Официальное фото модели {$item['article']}.</figcaption></figure>

<p>Номинальная мощность — 7 кВт. Топочная камера имеет чугунную футеровку, подовое горение, систему вторичного дожига и подачу воздуха на стекло. Воздух для горения можно подвести извне через патрубок диаметром 150 мм.</p>

<h3>Технические характеристики</h3>
<ul>
    <li>трёхсторонняя стеклокерамика с рабочей температурой до 750 °C;</li>
    <li>верхнее подключение дымохода диаметром 180 мм;</li>
    <li>система «чистое стекло» и вторичный дожиг;</li>
    <li>боковое открывание дверцы;</li>
    <li>габариты 577 × 1331 × 468 мм;</li>
    <li>масса 154 кг.</li>
</ul>

<p>Перед покупкой специалисты KOTLOV.BY помогут проверить тепловую нагрузку помещения, параметры дымохода, приток воздуха, основание и безопасные расстояния до горючих конструкций.</p>
HTML;
    }

    private function findProductId(array $item, ?int $supplierId): ?int
    {
        if ($supplierId && Schema::hasTable('supplier_products')) {
            $linked = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where(function ($query) use ($item): void {
                    $query->where('supplier_article', $item['article'])
                        ->orWhere('supplier_article_normalized', $item['article'])
                        ->orWhere('source_wp_id', $item['bitrix_id']);
                })
                ->whereNotNull('product_id')
                ->value('product_id');
            if ($linked) {
                return (int) $linked;
            }
        }

        $bySlug = DB::table('products')->where('slug', $item['slug'])->value('id');
        if ($bySlug) {
            return (int) $bySlug;
        }

        $query = DB::table('products')->where('name', 'like', '%РИО%');
        if ($item['article'] === 'KR224G') {
            $query->where('name', 'like', '%графит%')->where('name', 'like', '%дровник%');
        } elseif ($item['article'] === 'KR221') {
            $query->where('name', 'like', '%дровник%')->where('name', 'not like', '%графит%');
        } else {
            $query->where('name', 'not like', '%дровник%');
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    private function nextSku(): string
    {
        $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        do {
            $sku = sprintf('KOTLOV-%06d', ++$max);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function publishArticle($now): void
    {
        if (! Schema::hasTable('blog_posts') || ! Schema::hasTable('blog_categories')) {
            return;
        }

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
                'title' => 'ЭкоКамин РИО: обзор трёх панорамных каминов 7 кВт',
                'excerpt' => 'Сравниваем три модели серии ЭкоКамин РИО: KR206, KR221 и графитовый KR224G. Конструкция, размеры, дровник, фото, видео и требования к монтажу.',
                'content' => $this->articleContent(),
                'cover_image' => 'img/products/ecokamin/rio/rio-kr221-1.jpg',
                'images' => json_encode([
                    'img/products/ecokamin/rio/rio-kr206-1.jpg',
                    'img/products/ecokamin/rio/rio-kr221-1.jpg',
                    'img/products/ecokamin/rio/rio-kr224g-1.jpg',
                    'img/products/ecokamin/rio/rio-kr206-7.jpg',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['ЭкоКамин', 'РИО', 'KR206', 'KR221', 'KR224G', 'камин 7 кВт', 'трёхсторонний камин'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => '2026-10-20 18:00:00',
                'meta_title' => 'Камины ЭкоКамин РИО 7 кВт: KR206, KR221 и KR224G — обзор',
                'meta_description' => 'Подробный обзор серии ЭкоКамин РИО: три модели 7 кВт, трёхстороннее стекло, размеры, отличия дровника и цвета, фото, видео и монтаж.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>Серия ЭкоКамин РИО — это три готовых дровяных камина мощностью 7 кВт с высоким трёхсторонним стеклом.</strong> Они построены на общей топочной части, но отличаются основанием и цветом: компактный KR206, чёрный KR221 со встроенным дровником и графитовый KR224G со встроенным дровником.</p>

<div class="blog-image-2"><img loading="eager" src="/img/products/ecokamin/rio/rio-kr221-1.jpg" alt="Камин ЭкоКамин РИО KR221 со встроенным дровником"></div>

<div class="kotlov-article-note"><strong>Общее для серии</strong> 7 кВт · трёхстороннее остекление · чугунная футеровка · подовое горение · вторичный дожиг · дымоход 180 мм · 577 × 1331 × 468 мм · 154 кг.</div>

<h2>Три модели РИО: в чём разница</h2>

<h3>ЭкоКамин РИО KR206</h3>
<p class="text text-body-1">Базовая модель без встроенного дровника. Камин установлен на компактной центральной опоре: визуально он выглядит легче и оставляет больше свободного пространства вокруг корпуса. Это вариант для интерьера, где дрова хранят отдельно.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/rio/rio-kr206-1.jpg" alt="Камин ЭкоКамин РИО KR206 без дровника"></div>
<p class="text text-body-1"><a href="/pechi-kaminy/kamin-ecokamin-rio-kr206"><strong>Карточка ЭкоКамин РИО KR206 →</strong></a></p>

<h3>ЭкоКамин РИО на дровнике KR221</h3>
<p class="text text-body-1">Чёрное исполнение с полноценной нижней нишей для поленьев. Дровник делает силуэт более монолитным и позволяет держать небольшой запас сухих дров рядом с топкой.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/rio/rio-kr221-2.jpg" alt="Чёрный камин ЭкоКамин РИО KR221 на дровнике"></div>
<p class="text text-body-1"><a href="/pechi-kaminy/kamin-ecokamin-rio-na-drovnike-kr221"><strong>Карточка ЭкоКамин РИО KR221 →</strong></a></p>

<h3>ЭкоКамин РИО графит на дровнике KR224G</h3>
<p class="text text-body-1">По компоновке модель близка к KR221, но выполнена в графитовом оттенке. Она подходит для спокойных серых, каменных и бетонных интерьеров, где глубокий чёрный корпус был бы слишком контрастным.</p>
<div class="blog-image-2"><img loading="lazy" src="/img/products/ecokamin/rio/rio-kr224g-2.jpg" alt="Графитовый камин ЭкоКамин РИО KR224G на дровнике"></div>
<p class="text text-body-1"><a href="/pechi-kaminy/kamin-ecokamin-rio-grafit-na-drovnike-kr224g"><strong>Карточка ЭкоКамин РИО KR224G →</strong></a></p>

<h2>Панорамное трёхстороннее остекление</h2>
<p class="text text-body-1">Высокая фронтальная стеклокерамика дополнена боковыми стёклами, поэтому пламя видно не только прямо перед камином. Производитель указывает термостойкость стеклокерамики до 750 °C. Воздушный поток вдоль стекла уменьшает оседание сажи, но качество дров и правильная тяга всё равно остаются ключевыми.</p>

<h2>Топочная часть и горение</h2>
<p class="text text-body-1">Внутри используется чугунная футеровка. Подовое горение формирует аккуратный очаг без классической колосниковой решётки, а вторичный дожиг подаёт дополнительный воздух в верхнюю зону пламени. Для стабильной работы нужны сухие дрова и правильно рассчитанный дымоход.</p>

<h2>Технические характеристики серии</h2>
<ul>
    <li><strong>топливо:</strong> дрова;</li>
    <li><strong>тепловая мощность:</strong> 7 кВт;</li>
    <li><strong>габариты:</strong> 577 × 1331 × 468 мм;</li>
    <li><strong>масса:</strong> 154 кг;</li>
    <li><strong>дымоход:</strong> верхнее подключение, 180 мм;</li>
    <li><strong>подача воздуха извне:</strong> 1 × 150 мм;</li>
    <li><strong>дверца:</strong> боковое открывание;</li>
    <li><strong>футеровка:</strong> чугун;</li>
    <li><strong>системы:</strong> чистое стекло и вторичный дожиг.</li>
</ul>

<h2>Что проверить перед монтажом</h2>
<p class="text text-body-1">Мощность 7 кВт нельзя переводить в площадь одной универсальной цифрой: результат зависит от утепления, высоты потолка, остекления и расчётной температуры зимой. До заказа нужно проверить теплопотери, негорючее основание под массу 154 кг, защиту стен и пола, приточный воздух и трассу дымохода диаметром 180 мм.</p>

<p class="text text-body-1">Для каждой карточки мы добавили официальный габаритный чертёж, общую инструкцию производителя, фотографии и видеообзор. Поставщик и импортёр в Беларуси — ООО «СанБизнесГруп».</p>
HTML;
    }
};
