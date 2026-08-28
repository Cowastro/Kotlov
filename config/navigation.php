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
 *   - Promo-колонка мегаменю (бренды, баннер, CTA)
 */

return [

    // Количество колонок в левой зоне мегаменю (xl-col-N). По умолчанию 2.
    'columns' => [
        'kotly'            => 3,
        'dymohody'         => 3,
        'otoplenie'        => 3,
        'bani-i-sauny'     => 3,
        'vodosnabzhenie'   => 3,
    ],

    // Маппинг slug категории → иконка
    'icons' => [
        'kotly'                              => 'heater.svg',
        'teplovyie-nasosyi'                  => 'hvac.svg',
        'pelletnye-gorelki'                 => 'fire_p.svg',
        'vodonagrevateli'                    => 'temperature--water.svg',
        'pechki'                             => 'campfire.svg',
        'bani-i-sauny'                       => 'sauna.svg',
        'kaminy'                             => 'fireplace.svg',
        'dymohody'                           => 'chimney.svg',
        'vodosnabzhenie'                     => 'droplet.svg',
        'klimat'                             => 'air.svg',
        'radiatory'                          => 'radiator.svg',
        'truby-i-fitingi'                    => 'pipe.svg',
        'teplyj-pol'                         => 'floor-heating.svg',
        'elektricheskie-konvektoryi'         => 'convector.svg',
        'filtry'                             => 'filter.svg',
        'komplektuyushhie-dlya-otopleniya'   => 'wrench.svg',
    ],

    // Маппинг slug категории -> CSS-иконка из icomoon. Используется в хедере,
    // чтобы меню не показывало битые <img>, если серверный /icons недоступен.
    'icon_classes' => [
        'kotly'                              => 'icon-Lightning',
        'teplovyie-nasosyi'                  => 'icon-Wind',
        'pelletnye-gorelki'                 => 'icon-Sparkle',
        'vodonagrevateli'                    => 'icon-Leaf',
        'pechki'                             => 'icon-Sparkle',
        'bani-i-sauny'                       => 'icon-HouseLine',
        'kaminy'                             => 'icon-Sparkle',
        'dymohody'                           => 'icon-ArrowFatUp',
        'vodosnabzhenie'                     => 'icon-Leaf',
        'klimat'                             => 'icon-Wind',
        'radiatory'                          => 'icon-Layout',
        'truby-i-fitingi'                    => 'icon-GearSix',
        'teplyj-pol'                         => 'icon-HouseLine',
        'elektricheskie-konvektoryi'         => 'icon-Lightning',
        'filtry'                             => 'icon-filter',
        'komplektuyushhie-dlya-otopleniya'   => 'icon-GearSix',
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
                    ['name' => 'Длительного горения',   'url' => '/tverdotoplivnye?filter=dlitelnoe'],
                    ['name' => 'Конденсационные',       'url' => '/gazovye?filter=kondensacionnye'],
                ],
            ],
            [
                'title' => 'Связанные разделы',
                'links' => [
                    ['name' => 'Водонагреватели',  'url' => '/vodonagrevateli'],
                    ['name' => 'Тепловые насосы',  'url' => '/teplovyie-nasosyi'],
                    ['name' => 'Монтаж котлов',    'url' => '/installers'],
                    ['name' => 'Дымоходы',         'url' => '/dymohody'],
                ],
            ],
        ],

        'teplovyie-nasosyi' => [
            [
                'title' => 'По типу',
                'links' => [
                    ['name' => 'Воздух-вода',       'url' => '/teplovyie-nasosyi?filter=air-water'],
                    ['name' => 'Для отопления',      'url' => '/teplovyie-nasosyi?filter=otoplenie'],
                    ['name' => 'Для ГВС',            'url' => '/teplovyie-nasosyi?filter=gvs'],
                    ['name' => 'Для дома',           'url' => '/teplovyie-nasosyi?filter=dom'],
                ],
            ],
            [
                'title' => 'Монтаж и сервис',
                'links' => [
                    ['name' => 'Монтаж насосов',     'url' => '/installers'],
                    ['name' => 'Подбор оборудования','url' => '/installers'],
                    ['name' => 'Пеллетные горелки',  'url' => '/pelletnye-gorelki'],
                ],
            ],
        ],

        'pelletnye-gorelki' => [
            [
                'title' => 'По мощности',
                'links' => [
                    ['name' => 'До 25 кВт',      'url' => '/pelletnye-gorelki?filter=25kvt'],
                    ['name' => 'До 50 кВт',       'url' => '/pelletnye-gorelki?filter=50kvt'],
                    ['name' => 'До 100 кВт',      'url' => '/pelletnye-gorelki?filter=100kvt'],
                    ['name' => 'Промышленные',    'url' => '/pelletnye-gorelki?filter=prom'],
                ],
            ],
            [
                'title' => 'Монтаж и сервис',
                'links' => [
                    ['name' => 'Монтаж горелок',      'url' => '/installers'],
                    ['name' => 'Сервис и ТО',         'url' => '/installers'],
                    ['name' => 'Подбор горелки',      'url' => '/installers'],
                    ['name' => 'Автоматика',          'url' => '/pelletnye-gorelki?filter=auto'],
                    ['name' => 'Комплектующие',       'url' => '/pelletnye-gorelki?filter=controller'],
                ],
            ],
        ],

        'vodonagrevateli' => [
            [
                'title' => 'По типу нагрева',
                'links' => [
                    ['name' => 'Газовые',          'url' => '/gas'],
                    ['name' => 'Электрические',    'url' => '/electric'],
                    ['name' => 'Косвенного нагрева','url' => '/kosvennye'],
                    ['name' => 'Комбинированные',  'url' => '/kombinirovannye'],
                    ['name' => 'Газовые колонки',  'url' => '/vodogreynaya-kolonka'],
                ],
            ],
            [
                'title' => 'Связанные разделы',
                'links' => [
                    ['name' => 'Котлы',            'url' => '/kotly'],
                    ['name' => 'Тепловые насосы',  'url' => '/teplovyie-nasosyi'],
                    ['name' => 'Монтаж',           'url' => '/installers'],
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
                    ['name' => 'Для бани',  'url' => '/pechi-dlya-bani'],
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
                    ['name' => 'Аксессуары',           'url' => '/aksessuary-kaminy'],
                    ['name' => 'Комплектующие',         'url' => '/komplektuyushhie-dlya-otopleniya'],
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

        'vodosnabzhenie' => [
            [
                'title' => 'По назначению',
                'links' => [
                    ['name' => 'Для дома и дачи',   'url' => '/poverhnostnyie'],
                    ['name' => 'Скважинные',         'url' => '/skvajinnye-nasosy'],
                    ['name' => 'Для отопления',      'url' => '/tsirkulyatsionnyie'],
                    ['name' => 'Насосные станции',   'url' => '/nasosnyie-stantsii'],
                ],
            ],
            [
                'title' => 'Монтаж и сервис',
                'links' => [
                    ['name' => 'Монтаж насосов',     'url' => '/installers'],
                    ['name' => 'Подбор насоса',      'url' => '/installers'],
                ],
            ],
        ],
    ],

    // -------------------------------------------------------
    // Правая promo-колонка мегаменю по slug корневой категории
    // brands  — чипы брендов (текстовые)
    // banner  — img + url + title (баннер)
    // cta     — список быстрых ссылок
    // -------------------------------------------------------
    'promo' => [

        'kotly' => [
            'brands' => ['Viessmann', 'Baxi', 'Bosch', 'Vaillant', 'Hotta'],
            'banner' => ['img' => 'banners/baner_boiler.jpg', 'url' => '/kotly', 'title' => 'Котлы — весь ассортимент'],
            'cta' => [
                ['name' => 'Монтаж котлов',   'url' => '/installers'],
                ['name' => 'Акции на котлы',  'url' => '/akcii'],
                ['name' => 'Все котлы',        'url' => '/kotly'],
            ],
        ],

        'teplovyie-nasosyi' => [
            'brands' => ['Hotta', 'Daikin', 'Mitsubishi', 'LG', 'Haier'],
            'banner' => ['img' => 'banners/banner_pump.jpg', 'url' => '/teplovyie-nasosyi', 'title' => 'Тепловые насосы'],
            'cta' => [
                ['name' => 'Подбор насоса →',  'url' => '/installers'],
                ['name' => 'Акции →',          'url' => '/akcii'],
                ['name' => 'Все насосы →',     'url' => '/teplovyie-nasosyi'],
            ],
        ],

        'pelletnye-gorelki' => [
            'brands' => ['Hargassner', 'BioTec', 'Eko-Vimar', 'Пеллетрон'],
            'banner' => ['img' => 'banners/banner-sale.jpg', 'url' => '/akcii', 'title' => 'Акции и спецпредложения'],
            'cta' => [
                ['name' => 'Монтаж горелок →', 'url' => '/installers'],
                ['name' => 'Все горелки →',    'url' => '/pelletnye-gorelki'],
            ],
        ],

        'vodonagrevateli' => [
            'brands' => ['Ariston', 'Bosch', 'Thermex', 'Electrolux', 'Gorenje', 'Baxi'],
            'banner' => ['img' => 'banners/baner_boiler1.jpg', 'url' => '/vodonagrevateli', 'title' => 'Водонагреватели и бойлеры'],
            'cta' => [
                ['name' => 'Электрические →',        'url' => '/electric'],
                ['name' => 'Косвенного нагрева →',   'url' => '/kosvennye'],
                ['name' => 'Все водонагреватели →',  'url' => '/vodonagrevateli'],
            ],
        ],

        'pechki' => [
            'brands' => ['Gefest', 'Термофор', 'TMF', 'Везувий'],
            'banner' => ['img' => 'banners/banner-fireplace1.jpg', 'url' => '/pechki', 'title' => 'Печи и камины'],
            'cta' => [
                ['name' => 'Камины →',        'url' => '/kaminy'],
                ['name' => 'Дымоходы →',      'url' => '/dymohody'],
                ['name' => 'Для бани →',      'url' => '/pechi-dlya-bani'],
            ],
        ],

        'kaminy' => [
            'brands' => ['Spartherm', 'Romotop', 'Heta', 'Keddy'],
            'banner' => ['img' => 'banners/banner-fireplace1.jpg', 'url' => '/kaminy', 'title' => 'Каминные топки и вставки'],
            'cta' => [
                ['name' => 'Аксессуары →',    'url' => '/aksessuary-kaminy'],
                ['name' => 'Дымоходы →',      'url' => '/dymohody'],
                ['name' => 'Монтаж →',        'url' => '/installers'],
            ],
        ],

        'dymohody' => [
            'brands' => ['Schiedel', 'Вулкан', 'Fenix', 'Ferrum'],
            'banner' => ['img' => 'banners/banner-sale.jpg', 'url' => '/akcii', 'title' => 'Акции на дымоходы'],
            'cta' => [
                ['name' => 'Монтаж дымоходов', 'url' => '/installers'],
                ['name' => 'Комплектующие',    'url' => '/dymohody'],
                ['name' => 'Все дымоходы',     'url' => '/dymohody'],
            ],
        ],

        'bani-i-sauny' => [
            'brands' => ['TMF', 'Термофор', 'Везувий', 'Русич'],
            'banner' => ['img' => 'banners/banner-sale.jpg', 'url' => '/akcii', 'title' => 'Акции на банные товары'],
            'cta' => [
                ['name' => 'Дымоходы для бани →', 'url' => '/dymohody'],
                ['name' => 'Монтаж →',            'url' => '/installers'],
                ['name' => 'Все товары →',        'url' => '/bani-i-sauny'],
            ],
        ],

        'klimat' => [
            'brands' => ['Daikin', 'Mitsubishi', 'LG', 'Samsung', 'Haier'],
            'banner' => ['img' => 'banners/banner-sale.jpg', 'url' => '/akcii', 'title' => 'Акции на климатику'],
            'cta' => [
                ['name' => 'Монтаж кондиционеров', 'url' => '/installers'],
                ['name' => 'Акции',               'url' => '/akcii'],
                ['name' => 'Все кондиционеры',    'url' => '/klimat'],
            ],
        ],

        'vodosnabzhenie' => [
            'brands' => ['Grundfos', 'DAB', 'Wilo', 'Unipump', 'Pedrollo'],
            'banner' => ['img' => 'banners/banner-sale.jpg', 'url' => '/akcii', 'title' => 'Насосы и водоснабжение'],
            'cta' => [
                ['name' => 'Монтаж →',        'url' => '/installers'],
                ['name' => 'Акции →',         'url' => '/akcii'],
                ['name' => 'Все насосы →',    'url' => '/vodosnabzhenie'],
            ],
        ],

    ],
];
