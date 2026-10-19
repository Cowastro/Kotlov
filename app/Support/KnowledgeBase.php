<?php

namespace App\Support;

use App\Support\Catalog\BelomoGasMeterDesignations;

/**
 * Раздел «База знаний»: справочные технические материалы по оборудованию.
 * Новый материал — добавить элемент в нужный раздел sections().
 */
final class KnowledgeBase
{
    public const URL = '/baza-znaniy';

    public static function sections(): array
    {
        return [
            [
                'title' => 'Техническая информация',
                'description' => 'Расшифровка обозначений, варианты исполнения и таблицы соответствия от производителей.',
                'items' => [
                    [
                        'title' => 'Обозначения счетчиков газа БелОМО КАТА и СКАТ',
                        'category' => 'Счетчики газа',
                        'description' => 'Расшифровка обозначения ультразвуковых счетчиков: типоразмер, цифровой выход, NB-IoT/GSM, клапан, датчик давления, резьба. Все 80 вариантов исполнения.',
                        'url' => BelomoGasMeterDesignations::PAGE_URL,
                        'downloads' => [
                            ['label' => 'PDF', 'url' => BelomoGasMeterDesignations::PDF_URL],
                            ['label' => 'CSV', 'url' => BelomoGasMeterDesignations::CSV_URL],
                        ],
                    ],
                ],
            ],
        ];
    }
}
