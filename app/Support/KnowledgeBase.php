<?php

namespace App\Support;

use App\Support\Catalog\BelomoGasMeterDesignations;
use App\Support\Catalog\GasMeterSelectionGuide;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Раздел «База знаний»: справочные технические материалы по оборудованию.
 * Новый материал — добавить элемент в items(); рубрика — в categories().
 */
final class KnowledgeBase
{
    public const URL = '/baza-znaniy';

    public const FALLBACK_IMAGE = '/img/blog/blog-boiler.jpg';

    public static function categories(): array
    {
        return [
            'tekhnicheskaya-informatsiya' => 'Техническая информация',
        ];
    }

    public static function items(): Collection
    {
        return collect([
            [
                'slug' => 'kak-podobrat-schetchik-gaza',
                'url' => GasMeterSelectionGuide::PAGE_URL,
                'title' => 'Как подобрать газовый счётчик: простая инструкция и подборщик',
                'category' => 'tekhnicheskaya-informatsiya',
                'date' => '2026-10-21',
                'image' => '/img/products/elicon/8072-23-1.png',
                'excerpt' => 'Понятный подбор по расходу приборов, направлению газа, монтажной длине и месту установки. Калькулятор предложит подходящий типоразмер и модели.',
                'tags' => ['Счетчики газа', 'Подбор оборудования', 'БелОМО', 'Для дома'],
                'downloads' => [],
            ],
            [
                'slug' => 'belomo-kata-skat-oboznacheniya',
                'url' => BelomoGasMeterDesignations::PAGE_URL,
                'title' => 'Обозначения счетчиков газа БелОМО КАТА и СКАТ',
                'category' => 'tekhnicheskaya-informatsiya',
                'date' => '2026-10-19',
                'image' => '/img/products/elicon/8348000000020-1.png',
                'excerpt' => 'Расшифровка обозначения ультразвуковых счетчиков: типоразмер, цифровой выход, NB-IoT/GSM, клапан, датчик давления, резьба. Все 80 вариантов исполнения с поиском.',
                'tags' => ['Счетчики газа', 'БелОМО', 'КАТА', 'СКАТ'],
                'downloads' => [
                    ['label' => 'PDF производителя', 'url' => BelomoGasMeterDesignations::PDF_URL],
                    ['label' => 'Таблица CSV', 'url' => BelomoGasMeterDesignations::CSV_URL],
                ],
            ],
        ])->map(fn (array $item) => $item + ['category_name' => self::categories()[$item['category']]])
            ->sortByDesc('date')
            ->values();
    }

    public static function filter(?string $category, ?string $tag, ?string $query): Collection
    {
        $query = $query !== null ? Str::lower(trim($query)) : '';

        return self::items()
            ->when($category, fn ($items) => $items->where('category', $category))
            ->when($tag, fn ($items) => $items->filter(fn ($item) => in_array($tag, $item['tags'], true)))
            ->when($query !== '', fn ($items) => $items->filter(function ($item) use ($query) {
                $haystack = Str::lower($item['title'] . ' ' . $item['excerpt'] . ' ' . implode(' ', $item['tags']));

                return collect(preg_split('/\s+/u', $query))->every(fn ($term) => str_contains($haystack, $term));
            }))
            ->values();
    }

    public static function tags(): Collection
    {
        return self::items()->pluck('tags')->flatten()->unique()->values();
    }
}
