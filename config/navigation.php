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

    // Группы подразделов крупных разделов: заголовки колонок мегаменю и блоки на странице раздела.
    // Подраздел, не попавший ни в одну группу, показывается в группе «Ещё».
    'groups' => [
        'kotly' => [
            ['title' => 'Котлы', 'icon' => 'icon-Lightning', 'description' => 'Газовые, электрические, твердотопливные и пеллетные котлы.',
                'slugs' => ['gazovye', 'elektricheskie', 'tverdotoplivnye', 'kotly-na-pelletah']],
            ['title' => 'Горелки и запчасти', 'icon' => 'icon-Sparkle', 'description' => 'Пеллетные горелки, бункеры, шнеки и запчасти для котлов.',
                'slugs' => ['pelletnye-gorelki', 'zapchasti-dlya-kotlov']],
            ['title' => 'Автоматика и электропитание', 'icon' => 'icon-Devices', 'description' => 'Термостаты, датчики, стабилизаторы и ИБП для котла.',
                'slugs' => ['regulyatory', 'datchiki', 'stabilizatory-napryazheniya', 'istochniki-besperebojnogo-pitaniya', 'avtotransformatory-latr', 'komplektuyushchie-stabilizatorov']],
            ['title' => 'Газовое оборудование', 'icon' => 'icon-ShieldCheck', 'description' => 'Сигнализаторы загазованности, газовые шланги, регуляторы и счётчики газа.',
                'slugs' => ['signalizatory-zagazovannosti', 'gazovye-shlangi', 'regulyatoryi-davleniya-gaza', 'schetchiki-gaza']],
        ],
        'teplyj-pol' => [
            ['title' => 'Водяной', 'icon' => 'icon-Leaf', 'description' => 'Трубы, коллекторы и узлы для водяного тёплого пола.',
                'slugs' => ['vodyanoy-teplyy-pol']],
            ['title' => 'Электрический', 'icon' => 'icon-Lightning', 'description' => 'Маты, кабели, плёнка и терморегуляторы.',
                'slugs' => ['nagrevatelynye-maty', 'nagrevatelynye-kabeli', 'ik-plenochnyj-pol', 'termoregulyatory-dlya-teplogo-pola']],
        ],
        'bani-i-sauny' => [
            // Slugs here are historical: «kupeli» is water tanks, «pechi-dlya-bani» is steam room accessories.
            ['title' => 'Печи и каменки', 'icon' => 'icon-Sparkle', 'description' => 'Дровяные печи, электрокаменки, баки и всё для печи.',
                'slugs' => ['drovianye-peci-bannye', 'elektrokamenki', 'aksessuary-dlya-bani', 'kupeli']],
            ['title' => 'Двери и окна', 'icon' => 'icon-HouseLine', 'description' => 'Стеклянные двери и окна для парной.',
                'slugs' => ['dveri-dlya-ban-i-saun', 'okna-dlya-bani-i-sauny']],
            ['title' => 'Парная и отдых', 'icon' => 'icon-Package', 'description' => 'Аксессуары для парной, купели, мангалы и казаны.',
                'slugs' => ['pechi-dlya-bani', 'kupeli-2', 'mangalyi']],
        ],
        'dymohody' => [
            ['title' => 'Системы', 'icon' => 'icon-ArrowFatUp', 'description' => 'Одностенные, утеплённые сэндвич и коаксиальные дымоходы.',
                'slugs' => ['dymohody-mono', 'dymohody-sendvich', 'koaxial-dymoxod']],
            ['title' => 'Элементы и монтаж', 'icon' => 'icon-PuzzlePiece', 'description' => 'Переходы, крепления, зонты, конденсатоотводы, шиберы.',
                'slugs' => ['perehody-adaptery-dymohod', 'krepleniya-dymohod', 'zonty-deflektory', 'kondensatootvody', 'zaglushki-dymohod', 'shibery-dymohod', 'teplosyomniki']],
        ],
        'truby-i-fitingi' => [
            ['title' => 'Трубы', 'icon' => 'icon-GearSix', 'description' => 'ПП, PEX, металлопластик, ПЭ, НПВХ, металлические и гофрированные.',
                'slugs' => ['polipropilenovye-truby', 'truby-iz-sshitogo-polietilena', 'metalloplastikovye-truby', 'polietilenovye-truby', 'truby-npvh-napornye',
                    'metallicheskie-truby', 'gofrirovannye-truby-iz-nerzhaveyushchej-stali', 'gofrirovanye-truby', 'komplektuyushchie-dlya-gofrirovannoj-truby']],
            ['title' => 'Фитинги', 'icon' => 'icon-PuzzlePiece', 'description' => 'Пресс, резьбовые, компрессионные, для ПП, PEX, ПЭ и металлопластика.',
                'slugs' => ['press-fitingi', 'rezbovye-fitingi', 'fitingi-dlya-polipropilenovykh-trub', 'fitingi-dlya-trub-pex', 'fitingi-dlya-pe-trub',
                    'fitingi-dlya-metalloplastikovykh-trub', 'stalnye-detali-truboprovodov', 'flantsy', 'kompressionnye-fitingi', 'fitingi-dlya-gofrirovannoj-truby']],
            ['title' => 'Канализация', 'icon' => 'icon-Leaf', 'description' => 'Внутренняя и наружная канализация, чугун, люки и дождеприёмники.',
                'slugs' => ['vnutrennyaya-kanalizatsiya', 'naruzhnaya-kanalizatsiya', 'chugunnye-truby-i-fasonnye-chasti', 'lyuki-i-dozhdepriemniki']],
            ['title' => 'Изоляция и крепление', 'icon' => 'icon-Package', 'description' => 'Теплоизоляция и крепления для труб.',
                'slugs' => ['teploizolyatsiya-dlya-trub', 'krepleniya-dlya-trub']],
        ],
        'krany-i-zapornaya-armatura' => [
            ['title' => 'Запорная', 'icon' => 'icon-ShieldCheck', 'description' => 'Шаровые краны, вентили, задвижки, затворы, обратные клапаны и фильтры.',
                'slugs' => ['sharovye-krany', 'ventili-zapornye', 'zadvizhki', 'zatvory-diskovye', 'obratnye-klapany', 'filtry']],
            ['title' => 'Регулирующая и защитная', 'icon' => 'icon-GitDiff', 'description' => 'Предохранительные и смесительные клапаны, группы безопасности.',
                'slugs' => ['predokhranitelnaya-i-reguliruyushchaya-armatura', 'smesitelnye-klapany-i-uzly', 'smesitelnaya-armatura']],
            ['title' => 'Приборы учёта и контроля', 'icon' => 'icon-Devices', 'description' => 'Манометры, термометры, счётчики воды и тепла.',
                'slugs' => ['manometry-i-termometry', 'schetchiki-vody-i-tepla']],
        ],
        'komplektuyushhie-dlya-otopleniya' => [
            ['title' => 'Гидравлика котельной', 'icon' => 'icon-GitDiff', 'description' => 'Гребёнки, гидрострелки, группы быстрого монтажа и коллекторные шкафы.',
                'slugs' => ['grebenki', 'gidravlicheskie-razdeliteli-i-kollektory', 'gruppy-bystrogo-montazha-kotelnyx', 'kollektornye-shkafy', 'komplekty-podklyucheniya', 'montajnyie-komplektyi']],
            ['title' => 'Баки, нагрев, теплоносители', 'icon' => 'icon-Package', 'description' => 'Мембранные баки, ТЭНы и жидкости для систем отопления.',
                'slugs' => ['membrannye-baki', 'elektricheskie-teny-dlya-otopleniya', 'teplonositeli']],
        ],
        'vodosnabzhenie' => [
            ['title' => 'Насосы', 'icon' => 'icon-Leaf', 'description' => 'Циркуляционные, скважинные, поверхностные, дренажные и промышленные.',
                'slugs' => ['tsirkulyatsionnyie', 'skvajinnye-nasosy', 'poverhnostnyie', 'nasosnyie-stantsii', 'drenajnyie', 'promyshlennye-nasosy', 'komplektuyushchie-dlya-nasosov']],
            ['title' => 'Защита от протечек', 'icon' => 'icon-ShieldCheck', 'description' => 'Датчики, модули управления и краны с электроприводом.',
                'slugs' => ['sistemy-zashchity-ot-protechek']],
        ],
        'klimat' => [
            ['title' => 'Кондиционирование', 'icon' => 'icon-Wind', 'description' => 'Сплит-системы и мобильные кондиционеры.',
                'slugs' => ['kondicionery', 'mobilnye-kondicionery']],
            ['title' => 'Обогрев', 'icon' => 'icon-Lightning', 'description' => 'Обогреватели, электроконвекторы, пушки, завесы и наружный обогрев.',
                'slugs' => ['obogrevateli', 'elektricheskie-konvektoryi', 'maslyanye-obrevateli', 'infrakrasnye-obrevateli', 'teploventilyatory', 'teplovye-pushki', 'teplovye-zavesy', 'naruzhnyy-obogrev']],
            ['title' => 'Воздух', 'icon' => 'icon-Leaf', 'description' => 'Увлажнители, мойки воздуха, вентиляторы и воздуховоды.',
                'slugs' => ['uvlazhniteli-vozdukha', 'mojki-vozdukha', 'ventilyatory', 'gibkie-vozduhovody']],
        ],
    ],

    // Маппинг slug категории → иконка
    'icons' => [
        'kotly'                              => 'heater.svg',
        'kotly-otopleniya'                   => 'heater.svg',
        'teplovyie-nasosyi'                  => 'fan.svg',
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
        'krany-i-zapornaya-armatura'         => 'icon-GitDiff',
        'santehnika'                         => 'icon-Leaf',
        'montazhnye-materialy'               => 'icon-PuzzlePiece',
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
                'title' => 'Как выбрать',
                'links' => [
                    ['name' => 'Для тёплого пола',  'url' => '/teplovyie-nasosyi#heat-pump-guide-title'],
                    ['name' => 'Для радиаторов',    'url' => '/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye'],
                    ['name' => 'Как выбрать насос', 'url' => '/blog/kak-vybrat-teplovoy-nasos'],
                ],
            ],
            [
                'title' => 'Монтаж и сервис',
                'links' => [
                    ['name' => 'Монтаж насосов',     'url' => '/montazh-teplovyh-nasosov'],
                    ['name' => 'Подбор оборудования','url' => '/montazh-teplovyh-nasosov#heat-pump-request'],
                    ['name' => 'Реальные объекты',  'url' => '/teplovyie-nasosyi#heat-pump-cases'],
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
                    ['name' => 'Для бани',  'url' => '/drovianye-peci-bannye'],
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
                    ['name' => 'Дымоходы',             'url' => '/dymohody'],
                    ['name' => 'Печи',                 'url' => '/pechki'],
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
                    ['name' => 'Калькулятор: собрать дымоход', 'url' => '/podbor-dymohoda'],
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
                    ['name' => 'Промышленные',       'url' => '/promyshlennye-nasosy'],
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
            'brands' => ['KOTLOV GE', 'Galmet', 'Kospel'],
            'banner' => ['img' => 'banners/banner_pump.jpg', 'url' => '/teplovyie-nasosyi', 'title' => 'Тепловые насосы'],
            'cta' => [
                ['name' => 'Подбор насоса →',  'url' => '/montazh-teplovyh-nasosov#heat-pump-request'],
                ['name' => 'Реальные объекты →','url' => '/teplovyie-nasosyi#heat-pump-cases'],
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
                ['name' => 'Буферные ёмкости →',     'url' => '/bufernye-emkosti'],
                ['name' => 'Все водонагреватели →',  'url' => '/vodonagrevateli'],
            ],
        ],

        'pechki' => [
            'brands' => ['Gefest', 'Термофор', 'TMF', 'Везувий'],
            'banner' => ['img' => 'banners/banner-fireplace1.jpg', 'url' => '/pechki', 'title' => 'Печи и камины'],
            'cta' => [
                ['name' => 'Камины →',        'url' => '/kaminy'],
                ['name' => 'Дымоходы →',      'url' => '/dymohody'],
                ['name' => 'Для бани →',      'url' => '/drovianye-peci-bannye'],
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
