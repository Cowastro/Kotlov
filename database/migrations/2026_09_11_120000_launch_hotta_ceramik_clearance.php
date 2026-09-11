<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'hotta-ceramik-20-30-kvt-rasprodazha-s-wifi-kontrollerom';
    private const MANUAL_URL = '/docs/kotlov-xo-controller-1-2os-ru.pdf';
    private const EXISTING_CAMPAIGN_SLUGS = [
        'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
        'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140',
    ];
    private const EXISTING_ARTICLE_SLUGS = [
        'pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro',
        'pelletnaya-gorelka-kotlov-xo-evo-26-kvt',
    ];

    public function up(): void
    {
        $now = now();
        $categoryId = DB::table('categories')->where('slug', 'pelletnye-gorelki')->value('id');
        $brandId = DB::table('brands')->where('slug', 'kotlov')->value('id')
            ?: DB::table('brands')->where('name', 'like', '%KOTLOV%')->value('id');

        if (! $categoryId) {
            return;
        }

        foreach ($this->products($categoryId, $brandId, $now) as $slug => $data) {
            DB::table('products')->updateOrInsert(['slug' => $slug], $data);
        }

        foreach (self::EXISTING_CAMPAIGN_SLUGS as $slug) {
            $product = DB::table('products')->where('slug', $slug)->first();
            if (! $product) {
                continue;
            }

            $documents = json_decode((string) ($product->documents ?? '[]'), true) ?: [];
            if (! collect($documents)->contains(fn ($document) => is_array($document) && ($document['url'] ?? null) === self::MANUAL_URL)) {
                $documents[] = ['label' => 'Инструкция контроллера XO 1.0S/2.0S (PDF)', 'url' => self::MANUAL_URL];
            }

            $content = (string) ($product->content ?? '');
            if (! str_contains($content, 'data-xo-controller-upgrade="1"')) {
                $content .= $this->controllerContentBlock();
            }

            $short = trim((string) ($product->short_description ?? ''));
            if ($short === '' || str_starts_with($short, '. ')) {
                $short = $slug === 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt'
                    ? 'Промышленная горелка 100 кВт: съёмная топка, самоочистка и шамотированная камера.'
                    : 'Акционная пеллетная горелка KOTLOV XO с инженерным подбором.';
            }
            if (! str_contains(mb_strtolower($short), 'wi‑fi') && ! str_contains(mb_strtolower($short), 'wi-fi')) {
                $short = rtrim($short, '. ') . '. Современный контроллер XO со встроенным Wi‑Fi и интернет-управлением.';
            }

            DB::table('products')->where('id', $product->id)->update([
                'content' => $content,
                'short_description' => $short,
                'documents' => json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);
        }

        foreach (self::EXISTING_ARTICLE_SLUGS as $slug) {
            $article = DB::table('blog_posts')->where('slug', $slug)->first();
            if ($article && ! str_contains((string) $article->content, 'data-xo-controller-upgrade="1"')) {
                DB::table('blog_posts')->where('id', $article->id)->update([
                    'content' => (string) $article->content . $this->controllerContentBlock(),
                    'updated_at' => $now,
                ]);
            }
        }

        $blogCategoryId = DB::table('blog_categories')->where('slug', 'kotly-i-otoplenie')->value('id');
        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::ARTICLE_SLUG],
            [
                'category_id' => $blogCategoryId,
                'author_id' => null,
                'title' => 'HOTTA Ceramik 20 и 30 кВт: распродажа комплектов с современным Wi‑Fi-контроллером',
                'excerpt' => 'Проверенная серия HOTTA Cyberia/Ceramik возвращается в готовых комплектах: самоочищающаяся горелка, шнек и современный контроллер XO с интернет-управлением.',
                'content' => $this->articleContent(),
                'cover_image' => 'img/promotions/hotta-ceramik/hotta-20-1.jpg',
                'images' => json_encode([
                    'img/promotions/hotta-ceramik/hotta-20-1.jpg',
                    'img/promotions/hotta-ceramik/hotta-20-2.jpg',
                    'img/promotions/hotta-ceramik/hotta-30-1.jpg',
                    'img/promotions/hotta-ceramik/xo-controller-cover.png',
                    'img/promotions/hotta-ceramik/oxi-2018-kazakhstan-2x100.jpg',
                    'img/promotions/hotta-ceramik/hotta-2018-minsk-mayak-100.jpg',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode(['пеллетные горелки', 'HOTTA Ceramik', 'Cyberia', 'Wi-Fi', '20 кВт', '30 кВт', 'распродажа'], JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'published_at' => $now,
                'views_count' => 0,
                'meta_title' => 'HOTTA Ceramik 20 и 30 кВт с Wi‑Fi — распродажа комплектов',
                'meta_description' => 'HOTTA Ceramik 20 кВт за 4 300 BYN и 30 кВт за 4 600 BYN. Самоочистка, шнек, контроллер XO со встроенным Wi‑Fi, гарантия и подбор.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->delete();
        DB::table('products')->whereIn('slug', array_keys($this->products(0, null, now())))->delete();

        foreach (self::EXISTING_CAMPAIGN_SLUGS as $slug) {
            $product = DB::table('products')->where('slug', $slug)->first();
            if (! $product) {
                continue;
            }

            $documents = collect(json_decode((string) ($product->documents ?? '[]'), true) ?: [])
                ->reject(fn ($document) => is_array($document) && ($document['url'] ?? null) === self::MANUAL_URL)
                ->values()->all();
            $content = str_replace($this->controllerContentBlock(), '', (string) ($product->content ?? ''));

            DB::table('products')->where('id', $product->id)->update([
                'content' => $content,
                'documents' => empty($documents) ? null : json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }


        foreach (self::EXISTING_ARTICLE_SLUGS as $slug) {
            $article = DB::table('blog_posts')->where('slug', $slug)->first();
            if ($article) {
                DB::table('blog_posts')->where('id', $article->id)->update([
                    'content' => str_replace($this->controllerContentBlock(), '', (string) $article->content),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function products(int $categoryId, ?int $brandId, $now): array
    {
        $common = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'currency' => 'BYN',
            'unit' => 'шт',
            'service_info' => json_encode([
                'Инженерная проверка' => 'Совместимость с котлом, дверцей и дымоходом до заказа',
                'Пусконаладка' => 'Настройка подачи, воздуха и циклов очистки под объект и топливо',
                'Гарантия' => 'Предоставляется KOTLOV; срок указывается в документах поставки',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'documents' => json_encode([
                ['label' => 'Инструкция контроллера XO 1.0S/2.0S (PDF)', 'url' => self::MANUAL_URL],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'promo_flags' => null,
            'video_url' => 'https://www.youtube.com/watch?v=WNXtdYdI0Lg',
            'weight' => null,
            'warranty' => null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => 'in_stock',
            'stock_qty' => null,
            'is_featured' => true,
            'is_new' => false,
            'is_sale' => true,
            'sort_order' => 0,
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [
            'pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1' => array_merge($common, [
                'name' => 'Пеллетная горелка HOTTA Ceramik 20 кВт с Wi‑Fi',
                'h1' => 'Пеллетная горелка HOTTA Ceramik 20 кВт с Wi‑Fi',
                'sku' => 'KTL-HOTTA-CERAMIK-20-K1',
                'price' => 4300,
                'price_old' => null,
                'short_description' => 'Распродажа складской серии: диапазон 8–20 кВт, самоочищающиеся подвижные колосники, шнек и контроллер XO со встроенным Wi‑Fi.',
                'content' => $this->productContent(20, 8, 4300),
                'stock_qty' => 1,
                'images' => json_encode([
                    'img/promotions/hotta-ceramik/hotta-20-1.jpg',
                    'img/promotions/hotta-ceramik/hotta-20-2.jpg',
                    'img/promotions/hotta-ceramik/hotta-20-3.jpg',
                    'img/promotions/hotta-ceramik/hotta-20-4.jpg',
                    'img/promotions/hotta-ceramik/xo-controller-cover.png',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'specs' => $this->specs(20, 8, 19.3, 75),
                'meta_title' => 'HOTTA Ceramik 20 кВт с Wi‑Fi — купить за 4 300 BYN',
                'meta_keywords' => 'пеллетная горелка 20 кВт, HOTTA Ceramik, Cyberia 20, пеллетная горелка с Wi-Fi',
                'meta_description' => 'HOTTA Ceramik 20 кВт: комплект с самоочисткой, шнеком и контроллером XO Wi‑Fi. Цена распродажи 4 300 BYN, гарантия и инженерный подбор.',
            ]),
            'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3' => array_merge($common, [
                'name' => 'Пеллетная горелка HOTTA Ceramik 30 кВт с Wi‑Fi',
                'h1' => 'Пеллетная горелка HOTTA Ceramik 30 кВт с Wi‑Fi',
                'sku' => 'KTL-HOTTA-CERAMIK-30-K3',
                'price' => 4600,
                'price_old' => null,
                'short_description' => 'Распродажа складской серии: диапазон 10–30 кВт, самоочищающиеся подвижные колосники, шнек и контроллер XO со встроенным Wi‑Fi.',
                'content' => $this->productContent(30, 10, 4600),
                'stock_qty' => 3,
                'images' => json_encode([
                    'img/promotions/hotta-ceramik/hotta-30-1.jpg',
                    'img/promotions/hotta-ceramik/hotta-30-2.jpg',
                    'img/promotions/hotta-ceramik/hotta-30-3.jpg',
                    'img/promotions/hotta-ceramik/hotta-30-4.jpg',
                    'img/promotions/hotta-ceramik/xo-controller-cover.png',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'specs' => $this->specs(30, 10, 19.7, 100),
                'meta_title' => 'HOTTA Ceramik 30 кВт с Wi‑Fi — купить за 4 600 BYN',
                'meta_keywords' => 'пеллетная горелка 30 кВт, HOTTA Ceramik, Cyberia 30, пеллетная горелка с Wi-Fi',
                'meta_description' => 'HOTTA Ceramik 30 кВт: комплект с самоочисткой, шнеком и контроллером XO Wi‑Fi. Цена распродажи 4 600 BYN, гарантия и инженерный подбор.',
            ]),
        ];
    }

    private function specs(int $maxPower, int $minPower, float $weight, int $flueFlow): string
    {
        return json_encode([
            ['name' => 'Диапазон мощности', 'value' => $minPower . '–' . $maxPower, 'unit' => 'кВт'],
            ['name' => 'Модуляция мощности', 'value' => 'Да'],
            ['name' => 'Автоматическая очистка колосников', 'value' => 'Да'],
            ['name' => 'Диаметр пеллет', 'value' => '6–8', 'unit' => 'мм'],
            ['name' => 'Допустимая длина пеллет', 'value' => '5–40', 'unit' => 'мм'],
            ['name' => 'Допустимая зольность', 'value' => 'до 5', 'unit' => '%'],
            ['name' => 'Минимальное разрежение в топке котла', 'value' => '20', 'unit' => 'Па'],
            ['name' => 'Максимальный расход дымовых газов при 200 °C', 'value' => (string) $flueFlow, 'unit' => 'м³/ч'],
            ['name' => 'Размер топочной части', 'value' => '204 × 192', 'unit' => 'мм'],
            ['name' => 'Габариты горелки', 'value' => '655 × 258 × 270', 'unit' => 'мм'],
            ['name' => 'Масса', 'value' => (string) $weight, 'unit' => 'кг'],
            ['name' => 'Питание', 'value' => '230 В, 50 Гц'],
            ['name' => 'Максимальная мощность при розжиге', 'value' => '800', 'unit' => 'Вт'],
            ['name' => 'Максимальная мощность в работе', 'value' => '100', 'unit' => 'Вт'],
            ['name' => 'Степень защиты', 'value' => 'IP20'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function productContent(int $maxPower, int $minPower, int $price): string
    {
        return <<<HTML
<h2>HOTTA Ceramik {$maxPower} кВт — проверенная механика с современной автоматикой</h2>
<p>Акционный комплект объединяет пеллетную горелку проверенной серии HOTTA Cyberia/Ceramik, шнек подачи и современный контроллер XO со встроенным Wi‑Fi. Рабочий диапазон составляет <strong>{$minPower}–{$maxPower} кВт</strong>, цена распродажи — <strong>{$price} BYN</strong>.</p>
<h2>Почему эта серия интересна для сложной пеллеты</h2>
<p>В топке применяются подвижные самоочищающиеся колосники. Автоматика позволяет отдельно настроить время подачи, паузы, подачу воздуха и циклы очистки. Это даёт возможность адаптировать горение к пеллетам с повышенной зольностью, включая некоторые виды агропеллеты и пеллеты из лузги. Совместимость конкретного топлива подтверждается пробным запуском: важны влажность, спекание золы и теплотворность.</p>
<h2>Современный контроллер XO в комплекте</h2>
<p>Контроллер управляет горелкой, шнеком, розжигом, вентиляторами, очисткой и насосами. Встроенный Wi‑Fi позволяет удалённо контролировать температуру котла, видеть состояние системы, включать и выключать котёл, менять заданную температуру и просматривать графики. Набор функций зависит от подключённых датчиков и конфигурации котельной.</p>
<p><a href="/docs/kotlov-xo-controller-1-2os-ru.pdf" target="_blank" rel="noopener"><strong>Открыть руководство контроллера XO 1.0S/2.0S (PDF)</strong></a></p>
<h2>Комплектация</h2>
<ul><li>пеллетная горелка HOTTA Ceramik {$maxPower} кВт;</li><li>современный контроллер XO со встроенным Wi‑Fi;</li><li>шнек подачи топлива;</li><li>плавкий гофрированный рукав;</li><li>датчики температуры котла и питателя;</li><li>кабели и эксплуатационная документация.</li></ul>
<h2>Что проверяем перед установкой</h2>
<p>Инженер сопоставляет мощность с теплопотерями, проверяет размеры и герметичность топки, дверцу, направление факела, разрежение и дымоход. При необходимости рассчитываются фланец, новая дверца, бункер и монтаж.</p>
<p>Горелка продаётся с гарантией KOTLOV. Точный срок и условия указываются в документах поставки.</p>
HTML;
    }

    private function controllerContentBlock(): string
    {
        return <<<'HTML'
<section data-xo-controller-upgrade="1">
<h2>Контроллер XO со встроенным Wi‑Fi</h2>
<p>В комплектации используется современный контроллер семейства XO 1.0S/2.0S. Он управляет подачей топлива, воздухом, розжигом, очисткой и насосами, а через интернет позволяет контролировать температуру и состояние котельной. Конкретная версия и набор подключений зависят от модели горелки.</p>
<p><a href="/docs/kotlov-xo-controller-1-2os-ru.pdf" target="_blank" rel="noopener"><strong>Инструкция контроллера XO 1.0S/2.0S (PDF)</strong></a></p>
</section>
HTML;
    }

    private function articleContent(): string
    {
        return <<<'HTML'
<p class="text text-body-1"><strong>HOTTA Ceramik 20 и 30 кВт</strong> — складские комплекты проверенной серии пеллетных горелок, которую в Беларуси хорошо знали под маркой HOTTA Cyberia. Мы обновили ключевую часть комплектации: теперь вместе с горелкой поставляется современный контроллер XO со встроенным Wi‑Fi и интернет-управлением.</p>
<blockquote>Цена распродажи: HOTTA Ceramik 20 кВт — <strong>4 300 BYN</strong>, в наличии 1 штука; HOTTA Ceramik 30 кВт — <strong>4 600 BYN</strong>, в наличии 3 штуки. Оба варианта продаются с гарантией KOTLOV.</blockquote>
<div class="blog-image-2"><img loading="lazy" width="1280" height="713" src="/img/promotions/hotta-ceramik/hotta-20-1.jpg" alt="Пеллетная горелка HOTTA Ceramik Cyberia 20 кВт"></div>

<h2>Не просто старая горелка, а обновлённый комплект</h2>
<p class="text text-body-1">Механическая часть HOTTA Cyberia/Ceramik известна самоочищающейся топкой с подвижными колосниками, модуляцией мощности и простой адаптацией к подходящему твердотопливному котлу. Горелка автоматически подаёт топливо, разжигается и поддерживает заданную температуру.</p>
<p class="text text-body-1">Главное обновление комплекта — контроллер XO 1.0S/2.0S. Он объединяет управление горелкой и котельной, поддерживает Wi‑Fi и позволяет точнее настроить алгоритм под конкретный котёл, тягу и топливо.</p>
<div class="blog-image-2"><img loading="lazy" width="994" height="1406" src="/img/promotions/hotta-ceramik/xo-controller-cover.png" alt="Современный контроллер XO 1.0S и 2.0S с Wi-Fi"></div>

<h2>Что можно контролировать</h2>
<ul><li>температуру котла и выбранную уставку;</li><li>шнек подачи топлива и подающий механизм;</li><li>розжиг, вентилятор или два вентилятора — в зависимости от версии;</li><li>механизм очистки колосника;</li><li>насос котла и программируемые выходы;</li><li>датчик пламени, температуру питателя и дымовых газов;</li><li>аварийный вход и сигнализацию.</li></ul>
<p class="text text-body-1">После подключения к Wi‑Fi пользователь может видеть температуру котла и графики, включать и выключать котёл и менять заданную температуру. Полный набор показаний зависит от фактически подключённых датчиков.</p>
<p class="text text-body-1"><a href="/docs/kotlov-xo-controller-1-2os-ru.pdf" target="_blank" rel="noopener"><strong>Скачать инструкцию контроллера XO 1.0S/2.0S</strong></a>.</p>

<h2>Почему серия подходит для пеллет повышенной зольности</h2>
<p class="text text-body-1">Подвижные самоочищающиеся колосники уменьшают накопление золы в зоне горения. Контроллер даёт возможность скорректировать подачу топлива, паузы, воздух и частоту очистки. Поэтому горелку можно точнее адаптировать к топливу, которое сложнее стандартной древесной пеллеты, — например к отдельным видам агропеллеты или пеллеты из лузги.</p>
<p class="text text-body-1">Это не означает, что подходит любое топливо. Перед постоянной эксплуатацией оценивают диаметр и длину гранулы, влажность, зольность, температуру плавления золы и поведение топлива при пробном сжигании. Для пеллет с повышенной зольностью может потребоваться снижение нагрузки и более частая очистка котла.</p>

<h2>Два готовых варианта</h2>
<h3>HOTTA Ceramik 20 кВт — в наличии 1 штука</h3>
<p class="text text-body-1">Диапазон модуляции 8–20 кВт. Подходит для небольших и средних объектов после расчёта теплопотерь. Цена комплекта — <strong>4 300 BYN</strong>.</p>
<h3>HOTTA Ceramik 30 кВт — в наличии 3 штуки</h3>
<p class="text text-body-1">Диапазон модуляции 10–30 кВт. Вариант для более высокой тепловой нагрузки или котла соответствующей мощности. Цена комплекта — <strong>4 600 BYN</strong>.</p>
<div class="blog-image-2"><img loading="lazy" width="1280" height="713" src="/img/promotions/hotta-ceramik/hotta-30-1.jpg" alt="Пеллетная горелка HOTTA Ceramik Cyberia 30 кВт"></div>

<h2>Проверенная история на реальных котельных</h2>
<p class="text text-body-1">На фотографии ниже — две горелки OXI Ceramik 100 кВт в котлах Kronas на объекте в Казахстане, 2018 год. Это не продаваемые сейчас модели 20 и 30 кВт, а пример эксплуатации старшей мощности той же конструктивной школы.</p>
<div class="blog-image-2"><img loading="lazy" width="3968" height="2976" src="/img/promotions/hotta-ceramik/oxi-2018-kazakhstan-2x100.jpg" alt="Две горелки OXI Ceramik 100 кВт в котлах Kronas, Казахстан, 2018 год"></div>
<p class="text text-body-1">Ещё один архивный объект — Минск, 2018 год: горелка OXI/HOTTA 100 кВт установлена в котёл «Маяк». Эти фотографии показывают реальный опыт работы с серией задолго до нынешней комплектации с новым контроллером.</p>
<div class="blog-image-2"><img loading="lazy" width="3968" height="2976" src="/img/promotions/hotta-ceramik/hotta-2018-minsk-mayak-100.jpg" alt="Горелка HOTTA OXI 100 кВт в котле Маяк, Минск, 2018 год"></div>

<h2>Совместимость с котлом важнее площади дома</h2>
<p class="text text-body-1">До заказа проверяются мощность и объём топки, размеры дверцы, направление факела, герметичность, разрежение и дымоход. Горелка должна работать в своём диапазоне без постоянных остановок и без упора в максимальную мощность в мороз.</p>
<p class="text text-body-1">KOTLOV может рассчитать переходной фланец или новую дверцу, подобрать бункер, проверить дымоход и выполнить монтаж с пусконаладкой. Настройки топлива выполняются уже на конкретном котле по пламени, температуре дымовых газов и качеству золы.</p>

<h2>Условия распродажи</h2>
<p class="text text-body-1">Предложение действует на складские комплекты HOTTA Ceramik 20 и 30 кВт до их продажи. Посмотреть модели и оставить заявку можно на <a href="/akcii/hotta-ceramik-20-30"><strong>странице распродажи</strong></a> или непосредственно в карточках <a href="/pelletnye-gorelki/pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1"><strong>20 кВт</strong></a> и <a href="/pelletnye-gorelki/pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3"><strong>30 кВт</strong></a>.</p>
HTML;
    }
};
