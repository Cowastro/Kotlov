<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;

class HeatPumpInstallationController extends Controller
{
    public function __invoke()
    {
        $title = 'Монтаж теплового насоса под ключ в Минске и Беларуси | KOTLOV';
        $description = 'Расчёт, подбор и монтаж тепловых насосов воздух-вода KOTLOV GE. Тёплый пол, радиаторы, ГВС, резервный котёл, пусконаладка и гарантия.';
        $canonical = 'https://kotlov.by/montazh-teplovyh-nasosov';

        $caseSlugs = [
            'teplovoy-nasos-kotlov-ge-24-kvt-r32-nareyki',
            'teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok',
            'montazh-teplovogo-nasosa-hotta-30-kvt-i-rezervnogo-pelletnogo-kotla-biotep-25',
            'shef-montazh-dvuh-teplovyh-nasosov-115-kvt-marina-gorka',
        ];

        $cases = BlogPost::published()
            ->whereIn('slug', $caseSlugs)
            ->get()
            ->sortBy(fn (BlogPost $post) => array_search($post->slug, $caseSlugs, true))
            ->values();

        $faq = [
            'Что входит в монтаж теплового насоса под ключ?' => 'Состав работ определяется проектом. Обычно это расчёт и подбор, размещение наружного блока, гидравлическая обвязка, подключение к отоплению и ГВС, электромонтаж, настройка автоматики, пуск и проверка режимов.',
            'Можно ли подключить насос к существующим радиаторам?' => 'Да, после проверки теплопотерь и теплоотдачи радиаторов при требуемой температуре подачи. Иногда нужны увеличенные радиаторы, высокотемпературная модель или бивалентная схема.',
            'Нужна ли буферная ёмкость?' => 'Не во всех схемах. Решение зависит от минимального объёма системы, зонального регулирования, требований производителя и необходимости стабильного протока во время разморозки.',
            'Как выбрать место для наружного блока?' => 'Учитывают свободный воздухообмен, шум, снег, отвод конденсата, расстояние до окон и удобство обслуживания. Блок нельзя зажимать в нише или направлять поток воздуха на проход.',
            'Сколько времени занимает монтаж?' => 'Срок зависит от готовности котельной и сложности схемы. После изучения объекта мы фиксируем состав работ, этапы и ориентировочный график.',
        ];

        $schemaJson = json_encode([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => 'Монтаж тепловых насосов под ключ',
                'url' => $canonical,
                'description' => $description,
                'areaServed' => ['@type' => 'Country', 'name' => 'Беларусь'],
                'provider' => [
                    '@type' => 'Organization',
                    'name' => 'KOTLOV',
                    'url' => 'https://kotlov.by',
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
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Монтаж тепловых насосов', 'item' => $canonical],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.heat-pump-installation', compact(
            'title',
            'description',
            'canonical',
            'cases',
            'faq',
            'schemaJson'
        ));
    }
}
