<?php

/**
 * config/navigation.php
 *
 * Редакционные блоки для мегаменю.
 * Первый уровень (корневые категории и их дочерние) — из БД автоматически.
 * Здесь только то что нельзя сгенерировать из БД:
 *   - Иконки категорий
 *   - Блок "Популярное" (фильтры, теги, популярные запросы)
 *   - Блок "Связанные разделы" (кросс-ссылки)
 */

return [

    // Маппинг slug категории → иконка
    'icons' => [
        'kotly'            => 'heater--b.svg',
        'teplovye-nasosy'  => 'hvac.svg',
        'pelletnye-gorelki'=> 'fire_p.svg',
        'vodonagrevateli'  => 'temperature--water.svg',
        'otoplenie'        => 'heater.svg',
        'pechki'           => 'campfire.svg',
        'dlya-bani'        => 'sauna.svg',
        'kaminy'           => 'fireplace.svg',
        'dymohody'         => 'chimney.svg',
        'vodosnabzhenie'   => 'droplet.svg',
        'klimat'           => 'air.svg',
        'nasosy'           => 'hvac.svg',
    ],

    // Редакционные блоки мегаменю по slug корневой категории
    // Каждый блок: [ 'title' => '...', 'links' => [ ['name'=>'...', 'url'=>'...'], ... ] ]
    'editorial' => [

        'kotly' => [
            [
                'title' => 'Популярное',
                'links' => [
                    ['name' => 'Для дома',              'url' => '/kotly?filter=dom'],
                    ['name' => 'Для дачи',              'url' => '/kotly?filter=dacha'],
                    ['name' => 'Длительного горения',   'url' => '/kotly/tverdotoplivnye?filter=dlitelnoe'],
                    ['name' => 'Конденсационные',       'url' => '/kotly/gazovye?filter=kondensacionnye'],
                ],
            ],
            [
                'title' => 'Связанные разделы',
                'links' => [
                    ['name' => 'Водонагреватели',  'url' => '/vodonagrevateli'],
                    ['name' => 'Тепловые насосы',  'url' => '/teplovye-nasosy'],
                    ['name' => 'Монтаж котлов',    'url' => '/installers'],
                    ['name' => 'Дымоходы',         'url' => '/dymohody'],
                ],
            ],
        ],

        'teplovye-nasosy' => [
            [
                'title' => 'Назначение',
                'links' => [
                    ['name' => 'Для дома',       'url' => '/teplovye-nasosy?filter=dom'],
                    ['name' => 'Для отопления',  'url' => '/teplovye-nasosy?filter=otoplenie'],
                    ['name' => 'Для ГВС',        'url' => '/teplovye-nasosy?filter=gvs'],
                    ['name' => 'Монтаж',         'url' => '/installers'],
                ],
            ],
            [
                'title' => 'Популярное',
                'links' => [
                    ['name' => 'Hotta',         'url' => '/teplovye-nasosy?brand=hotta'],
                    ['name' => 'Акции',         'url' => '/akcii'],
                    ['name' => 'Подбор насоса', 'url' => '/installers'],
                ],
            ],
        ],

        'pelletnye-gorelki' => [
            [
                'title' => 'По мощности',
                'links' => [
                    ['name' => 'До 25 кВт',      'url' => '/pelletnye-gorelki?filter=25kvt'],
                    ['name' => 'До 50 кВт',      'url' => '/pelletnye-gorelki?filter=50kvt'],
                    ['name' => 'До 100 кВт',     'url' => '/pelletnye-gorelki?filter=100kvt'],
                    ['name' => 'Промышленные',   'url' => '/pelletnye-gorelki?filter=prom'],
                ],
            ],
            [
                'title' => 'Комплектующие',
                'links' => [
                    ['name' => 'Контроллеры',  'url' => '/pelletnye-gorelki?filter=controller'],
                    ['name' => 'Шнеки',        'url' => '/pelletnye-gorelki?filter=shnek'],
                    ['name' => 'Бункеры',      'url' => '/pelletnye-gorelki?filter=bunker'],
                    ['name' => 'Автоматика',   'url' => '/pelletnye-gorelki?filter=auto'],
                ],
            ],
        ],

        'vodonagrevateli' => [
            [
                'title' => 'Назначение',
                'links' => [
                    ['name' => 'Для дома',     'url' => '/vodonagrevateli?filter=dom'],
                    ['name' => 'Для квартиры', 'url' => '/vodonagrevateli?filter=kvartira'],
                    ['name' => 'Проточные',    'url' => '/vodonagrevateli?filter=protochnye'],
                    ['name' => 'Накопительные','url' => '/vodonagrevateli?filter=nakopitelnye'],
                ],
            ],
            [
                'title' => 'Дополнительно',
                'links' => [
                    ['name' => 'Монтаж',  'url' => '/installers'],
                    ['name' => 'Акции',   'url' => '/akcii'],
                ],
            ],
        ],

        'pechki' => [
            [
                'title' => 'По конструкции',
                'links' => [
                    ['name' => 'Чугунные',        'url' => '/pechki?filter=chugun'],
                    ['name' => 'Стальные',        'url' => '/pechki?filter=stal'],
                    ['name' => 'С водяным контуром','url' => '/pechki?filter=voda'],
                    ['name' => 'С плитой',        'url' => '/pechki?filter=plita'],
                ],
            ],
            [
                'title' => 'Связанные разделы',
                'links' => [
                    ['name' => 'Камины',    'url' => '/kaminy'],
                    ['name' => 'Дымоходы', 'url' => '/dymohody'],
                    ['name' => 'Для бани',  'url' => '/dlya-bani'],
                ],
            ],
        ],

        'kaminy' => [
            [
                'title' => 'По форме',
                'links' => [
                    ['name' => 'Прямое стекло',     'url' => '/kaminy?filter=pryamoe'],
                    ['name' => 'Боковое остекление', 'url' => '/kaminy?filter=bokovoe'],
                    ['name' => 'Трёхсторонние',     'url' => '/kaminy?filter=3storon'],
                    ['name' => 'Угловые',           'url' => '/kaminy?filter=uglovye'],
                ],
            ],
            [
                'title' => 'Дополнительно',
                'links' => [
                    ['name' => 'Аксессуары',           'url' => '/kaminy/aksessuary-kaminy'],
                    ['name' => 'Материалы для монтажа', 'url' => '/otoplenie'],
                    ['name' => 'Дымоходы',             'url' => '/dymohody'],
                ],
            ],
        ],

        'dymohody' => [
            [
                'title' => 'По типу',
                'links' => [
                    ['name' => 'Нержавеющие',  'url' => '/dymohody?filter=nerj'],
                    ['name' => 'Керамические',  'url' => '/dymohody?filter=keramika'],
                    ['name' => 'Коаксиальные',  'url' => '/dymohody?filter=koaks'],
                ],
            ],
            [
                'title' => 'Применение',
                'links' => [
                    ['name' => 'Для котлов',  'url' => '/dymohody?filter=kotly'],
                    ['name' => 'Для каминов', 'url' => '/dymohody?filter=kaminy'],
                    ['name' => 'Для бани',    'url' => '/dymohody?filter=banya'],
                    ['name' => 'Монтаж',      'url' => '/installers'],
                ],
            ],
        ],
    ],
];
