<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;

class FireplaceInstallationController extends Controller
{
    public function __invoke()
    {
        $title = 'Монтаж каминов под ключ в Минске и Беларуси | KOTLOV';
        $description = 'Подбор и монтаж каминов, печей-каминов и дымоходов по Беларуси. Каминная топка, противопожарные узлы, проходы перекрытий, запуск и гарантия.';
        $canonical = 'https://kotlov.by/montazh-kaminov';

        $caseSlugs = [
            'montazh-pechi-kamina-meta-bel-oka-6-kvt',
            'montazh-pechi-kamina-nordflam-palestro-8-kvt',
            'montazh-pechi-kamina-fireway-cooker',
        ];

        $cases = BlogPost::published()
            ->whereIn('slug', $caseSlugs)
            ->get()
            ->sortBy(fn (BlogPost $post) => array_search($post->slug, $caseSlugs, true))
            ->values();

        $faq = [
            'Что входит в монтаж камина под ключ?' => 'Состав работ зависит от объекта. Обычно это обследование, подбор печи или топки, расчёт и комплектация дымохода, устройство основания и защитных экранов, проходы перекрытий и кровли, сборка, пробная растопка и инструктаж.',
            'Можно ли установить печь-камин в готовом деревянном доме?' => 'Да, если есть возможность выполнить требуемые противопожарные расстояния и узлы. До подбора оборудования мы проверяем стены, пол, перекрытия, трассу дымохода и приток воздуха.',
            'Как подбирается диаметр и высота дымохода?' => 'По паспорту печи или топки, высоте здания, расположению конька и конфигурации трассы. Заужение канала, лишние повороты и недостаточная высота ухудшают тягу и безопасность.',
            'От чего зависит стоимость установки?' => 'От типа прибора, высоты и материала дымохода, количества перекрытий, кровельного прохода, основания, защитных экранов и готовности помещения. Точную смету составляем после исходных данных или осмотра.',
            'Сколько времени занимает монтаж?' => 'Простая установка печи-камина обычно быстрее монтажа каминной топки с облицовкой. Срок фиксируем после согласования схемы, комплектации и готовности объекта.',
        ];

        $schemaJson = json_encode([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => 'Монтаж каминов и печей-каминов под ключ',
                'url' => $canonical,
                'description' => $description,
                'areaServed' => ['@type' => 'Country', 'name' => 'Беларусь'],
                'provider' => ['@type' => 'Organization', 'name' => 'KOTLOV', 'url' => 'https://kotlov.by'],
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
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Монтаж каминов', 'item' => $canonical],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.fireplace-installation', compact('title', 'description', 'canonical', 'cases', 'faq', 'schemaJson'));
    }
}
