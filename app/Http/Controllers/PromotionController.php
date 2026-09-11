<?php

namespace App\Http\Controllers;

use App\Models\Product;

class PromotionController extends Controller
{
    private const XO_SLUG = 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt';

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

        $saleProducts = Product::query()
            ->active()
            ->notArchived()
            ->where('is_sale', true)
            ->with(['category', 'brand'])
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('pages.promotions', compact('title', 'description', 'canonical', 'ogImage', 'xoProduct', 'saleProducts'));
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
}
