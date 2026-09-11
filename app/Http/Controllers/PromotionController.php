<?php

namespace App\Http\Controllers;

use App\Models\Product;

class PromotionController extends Controller
{
    private const XO_SLUG = 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt';
    private const XO_EVO_SLUG = 'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140';

    public function index()
    {
        $title = 'Акции на отопительное оборудование | KOTLOV';
        $description = 'Действующие акции и специальные предложения KOTLOV на отопительное оборудование с доставкой и инженерным подбором по Беларуси.';
        $canonical = 'https://kotlov.by/akcii';
        $ogImage = asset('img/promotions/kotlov-xo-ceramic-pro-sale-cover.jpg');

        $xoProduct = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand'])
            ->where('slug', self::XO_SLUG)
            ->first();

        $evoProduct = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand'])
            ->where('slug', self::XO_EVO_SLUG)
            ->first();

        $saleProducts = Product::query()
            ->active()
            ->notArchived()
            ->where('is_sale', true)
            ->whereIn('slug', Product::PUBLIC_SALE_SLUGS)
            ->whereNotIn('slug', [self::XO_SLUG, self::XO_EVO_SLUG])
            ->with(['category', 'brand'])
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('pages.promotions', compact('title', 'description', 'canonical', 'ogImage', 'xoProduct', 'evoProduct', 'saleProducts'));
    }

    public function xoCeramicPro()
    {
        $product = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand'])
            ->where('slug', self::XO_SLUG)
            ->firstOrFail();

        $seriesProducts = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand'])
            ->where('name', 'like', '%KOTLOV XO Ceramic PRO%')
            ->where('id', '!=', $product->id)
            ->orderBy('id')
            ->limit(6)
            ->get();

        $discountPercent = $product->discount_percent ?: 10;
        $saving = max(0, (float) ($product->price_old ?: 14400) - (float) $product->price);
        $title = 'Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт с Wi‑Fi и скидкой 10%';
        $description = 'KOTLOV XO Ceramic PRO 100 кВт со встроенным Wi‑Fi в наличии со скидкой 10%. Самоочистка, инженерный подбор, адаптация котла и монтаж по Беларуси.';
        $canonical = 'https://kotlov.by/akcii/kotlov-xo-ceramic-pro';
        $ogImage = asset('img/promotions/kotlov-xo-ceramic-pro-sale-cover.jpg');
        $ogImageSecure = $ogImage;
        $ogImageWidth = 1600;
        $ogImageHeight = 900;
        $ogImageType = 'image/jpeg';

        $faq = [
            'Для каких объектов подходит горелка 100 кВт?' => 'Модель рассматривают для производственных помещений, складов, СТО, теплиц, коммерческих зданий и других объектов с расчётной нагрузкой в её рабочем диапазоне. Подбор выполняется по теплопотерям, котлу и режиму эксплуатации, а не только по площади.',
            'Можно ли установить горелку в существующий котёл?' => 'Во многих случаях да. До заказа инженер проверяет мощность и геометрию топки, размеры дверцы, противодавление, дымоход, автоматику и возможность безопасно разместить шнек и бункер.',
            'Что входит в комплект?' => 'Точный состав подтверждается перед заказом. Базовая конфигурация включает горелку, контроллер, датчик температуры котла, соединительные и силовые кабели, кабель шнека, гофрированный плавкий рукав, колено и шнек подачи топлива.',
            'Сколько пеллет потребляет горелка?' => 'Расход зависит от фактической нагрузки, качества пеллет, режима модуляции, котла и теплопотерь объекта. Ориентир из технической документации — около 0,2 кг пеллет на 1 кВт тепловой мощности.',
            'Чем эта комплектация отличается от других горелок 100 кВт?' => 'В акционную цену уже входят встроенное Wi‑Fi‑управление, шамотированная камера, два керамических розжигателя и каскадная самоочистка. У ряда сопоставимых предложений интернет‑модуль указан как дополнительная опция.',
            'Зачем нужна съёмная топка?' => 'Камера сгорания — самая теплонагруженная часть горелки. В Ceramic PRO она выполнена съёмным единым узлом. При естественном износе через много лет эксплуатации можно заменить топку целиком и вернуть горелке рабочую часть в состоянии нового узла.',
            'Как получить скидку 10%?' => 'Оставьте заявку на этой странице или оформите заказ в карточке товара. Предложение распространяется на KOTLOV XO Ceramic PRO 100 кВт и действует до продажи выделенного акционного остатка.',
        ];

        $schemaJson = json_encode([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'image' => [$product->image_url],
                'description' => $description,
                'sku' => $product->sku,
                'brand' => ['@type' => 'Brand', 'name' => 'KOTLOV'],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $canonical,
                    'priceCurrency' => 'BYN',
                    'price' => (string) $product->price,
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faq)->map(fn (string $answer, string $question) => [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ])->values()->all(),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://kotlov.by'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Акции', 'item' => 'https://kotlov.by/akcii'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => 'KOTLOV XO Ceramic PRO', 'item' => $canonical],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.promotion-xo-ceramic-pro', compact(
            'product', 'seriesProducts', 'discountPercent', 'saving',
            'title', 'description', 'canonical', 'ogImage', 'ogImageSecure',
            'ogImageWidth', 'ogImageHeight', 'ogImageType', 'faq', 'schemaJson'
        ));
    }

    public function xoEvo26()
    {
        $product = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand'])
            ->where('slug', self::XO_EVO_SLUG)
            ->firstOrFail();

        $discountPercent = $product->discount_percent ?: 20;
        $saving = max(0, (float) ($product->price_old ?: 6400) - (float) $product->price);
        $stockCount = (int) ($product->stock_qty ?: 2);
        $title = 'Распродажа KOTLOV XO EVO 26 кВт — скидка 20%, 2 штуки в наличии';
        $description = 'KOTLOV XO EVO 26 кВт EB140 со скидкой 20%: две горелки в наличии, сменная топка AISI 310S, самоочистка, керамический розжиг и инженерный подбор.';
        $canonical = 'https://kotlov.by/akcii/kotlov-xo-evo-26';
        $ogImage = asset('img/promotions/kotlov-xo-evo-26-stock-cover.jpg');
        $ogImageSecure = $ogImage;
        $ogImageWidth = 2048;
        $ogImageHeight = 1536;
        $ogImageType = 'image/jpeg';

        $faq = [
            'Сколько горелок участвует в распродаже?' => 'В наличии две KOTLOV XO EVO 26 кВт EB140. Это фактический складской остаток, а не предварительный заказ. Акция завершается после продажи этих двух горелок.',
            'Для какой площади подходит EVO 26 кВт?' => 'Производитель указывает ориентир 100–300 м². Окончательно мощность подбирают по теплопотерям здания, режиму отопления и параметрам существующего котла.',
            'Можно ли поставить горелку в существующий котёл?' => 'Да, если подходят топка, дверца, противодавление и дымоход. Инженер проверит совместимость и при необходимости рассчитает переходной фланец или новую дверцу.',
            'Как работает самоочистка?' => 'Подпружиненный верхний колосник очищает основной колосник и помогает сохранять проход воздуха. Система уменьшает ручное обслуживание, но не отменяет очистку котла и зольника.',
            'Что входит в комплект?' => 'Горелка EVO 26 кВт EB140, контроллер XO-1.0S, шнек подачи топлива, поворотное колено, плавкий гофрированный рукав с хомутами, кабели и эксплуатационная документация.',
            'Почему топка называется сменной?' => 'Камера сгорания выполнена отдельным узлом из жаропрочной стали AISI 310S. При естественном износе её можно заменить без покупки новой горелки целиком.',
        ];

        $schemaJson = json_encode([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'image' => [$ogImage, $product->image_url],
                'description' => $description,
                'sku' => $product->sku,
                'brand' => ['@type' => 'Brand', 'name' => 'KOTLOV'],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $canonical,
                    'priceCurrency' => 'BYN',
                    'price' => (string) $product->price,
                    'availability' => 'https://schema.org/InStock',
                    'inventoryLevel' => ['@type' => 'QuantitativeValue', 'value' => $stockCount],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => 'KOTLOV XO EVO — пеллетная горелка нового поколения',
                'description' => 'Видеообзор конструкции и работы пеллетной горелки KOTLOV XO EVO.',
                'thumbnailUrl' => ['https://i.ytimg.com/vi/v7n7WoDPUTA/maxresdefault.jpg'],
                'uploadDate' => '2025-11-20T05:03:21-08:00',
                'embedUrl' => 'https://www.youtube.com/embed/v7n7WoDPUTA?start=67',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faq)->map(fn (string $answer, string $question) => [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ])->values()->all(),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://kotlov.by'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Акции', 'item' => 'https://kotlov.by/akcii'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => 'KOTLOV XO EVO 26 кВт', 'item' => $canonical],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.promotion-xo-evo-26', compact(
            'product', 'discountPercent', 'saving', 'stockCount',
            'title', 'description', 'canonical', 'ogImage', 'ogImageSecure',
            'ogImageWidth', 'ogImageHeight', 'ogImageType', 'faq', 'schemaJson'
        ));
    }
}
