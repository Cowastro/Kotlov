<!-- Topbar -->
<div class="tf-topbar bg-dark">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center text-white">

            <div class="d-flex align-items-center gap-2">
                <span class="icon icon-MapPin"></span>
                <a href="#citySelector" data-bs-toggle="offcanvas" class="text-white d-flex align-items-center gap-1">

                    <span class="icon icon-MapPin"></span>

                    <span>{{ $geo ?? 'Минск' }}</span>

                    <i class="icon icon-CaretDown"></i>

                </a>
            </div>

            <div class="d-none d-md-block">
                Пн–Пт: {{ $time1 ?? '9:00–18:00' }}
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="tel:{{ $pCall1 ?? '+375293544041' }}" class="text-white">
                    {{ $phone1 ?? '+375 (29) 354-40-41' }}
                </a>

                <a href="/partners" class="text-white">
                    Партнёрам
                </a>
            </div>

        </div>
    </div>
</div>
<!-- /Topbar -->

<!-- Header -->
<header class="tf-header header-s6 has-by-category">
    <div class="br-line fake-class bottom-0 d-xl-none "></div>

    <div class="header-inner_wrap">
        <div class="container">
            <div class="header-inner">
                <div class="box-open-menu-mobile d-xl-none">
                    <a href="#mobileMenu" data-bs-toggle="offcanvas" class="btn-open-menu">
                        <i class="icon icon-List"></i>
                    </a>
                </div>

                <div class="header-left">
                    <div class="box-open-header-bottom m-0">
                        <div class="btn-open-header-bottom cs-pointer">
                            <i class="icon icon-List fs-24"></i>
                        </div>
                    </div>

                    <a href="/" class="logo-site">
                        <img loading="lazy" width="150" height="30" src="{{ asset('img/logo.svg') }}" alt="KOTLOV">
                    </a>

                    <div class="d-none d-xl-block">
                        <nav class="box-navigation">
                            <ul class="box-nav-menu">
                                <li class="menu-item">
                                    <a href="/installers" class="item-link">
                                        <span class="text cus-text">Монтаж</span>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="/brands" class="item-link">
                                        <span class="text cus-text">Бренды</span>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="/akcii" class="item-link">
                                        <span class="text cus-text">Акции</span>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="/blog" class="item-link">
                                        <span class="text cus-text">Статьи</span>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="/dostavka" class="item-link">
                                        <span class="text cus-text">Доставка</span>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="/contacts" class="item-link">
                                        <span class="text cus-text">Контакты</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="header-right">
                    <ul class="nav-icon-list d-xl-none">
                        <li class="d-none d-sm-block">
                            <a href="#search" data-bs-toggle="modal" class="nav-icon-item link">
                                <i class="icon icon-MagnifyingGlass"></i>
                            </a>
                        </li>

                        <li>
                            <a href="#sign" data-bs-toggle="modal" class="nav-icon-item link">
                                <i class="icon icon-User"></i>
                            </a>
                        </li>

                        <li class="d-none d-sm-block">
                            <a href="/wishlist" class="nav-icon-item link">
                                <i class="icon icon-HeartStraight"></i>
                            </a>
                        </li>

                        <li>
                            <a href="#shoppingCart" data-bs-toggle="offcanvas" class="nav-icon-item link shop-cart">
                                <i class="icon icon-Handbag"></i>
                                <span class="count">
                                    {{ $cartCount ?? 0 }}
                                </span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="br-line d-none d-xl-flex"></div>
        </div>
    </div>

   <div class="header-bottom_wrap d-none d-xl-block">
    <div class="container">
        <div class="header-bottom">

            <div class="col-left">
                <div class="nav-category-wrap main-action-active">
                    <div class="btn-nav-drop btn-active text-nowrap radius-8">
                        <i class="icon icon-List fs-24"></i>
                        <span class="name-category fw-medium">Каталог товаров</span>
                    </div>

                    <ul class="box-nav-category active-item radius-12">

                        <li class="has-sub-nav-category">
                            <a href="/kotly" class="nav-category_link">
                                Котлы
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Тип котлов</div>
                                        <a href="/kotly/gazovye" class="sub-nav__link tf-btn-line">Газовые</a>
                                        <a href="/kotly/tverdotoplivnye" class="sub-nav__link tf-btn-line">Твердотопливные</a>
                                        <a href="/kotly/elektricheskie" class="sub-nav__link tf-btn-line">Электрические</a>
                                        <a href="/kotly/pelletnye" class="sub-nav__link tf-btn-line">Пеллетные</a>
                                        <a href="/kotly/kombinirovannye" class="sub-nav__link tf-btn-line">Комбинированные</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Популярное</div>
                                        <a href="/kotly/dlya-doma" class="sub-nav__link tf-btn-line">Для дома</a>
                                        <a href="/kotly/dlya-dachi" class="sub-nav__link tf-btn-line">Для дачи</a>
                                        <a href="/kotly/24-kvt" class="sub-nav__link tf-btn-line">Котлы 24 кВт</a>
                                        <a href="/kotly/dlitelnogo-goreniya" class="sub-nav__link tf-btn-line">Длительного горения</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Связанные разделы</div>
                                        <a href="/vodonagrevateli" class="sub-nav__link tf-btn-line">Водонагреватели</a>
                                        <a href="/teplovye-nasosy" class="sub-nav__link tf-btn-line">Тепловые насосы</a>
                                        <a href="/montazh-kotlov" class="sub-nav__link tf-btn-line">Монтаж котлов</a>
                                        <a href="/akcii/kotly" class="sub-nav__link tf-btn-line">Акции на котлы</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
    <a href="/teplovye-nasosy" class="nav-category_link">
        Тепловые насосы
        <i class="icon icon-CaretRightThin"></i>
    </a>

    <div class="sub-nav-category">
        <div class="tf-grid-layout xl-col-3">
            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Типы</div>
                <a href="/teplovye-nasosy/vozduh-voda" class="sub-nav__link tf-btn-line">Воздух-вода</a>
                <a href="/teplovye-nasosy/monoblok" class="sub-nav__link tf-btn-line">Моноблок</a>
                <a href="/teplovye-nasosy/split" class="sub-nav__link tf-btn-line">Сплит-системы</a>
                <a href="/teplovye-nasosy/invertornye" class="sub-nav__link tf-btn-line">Инверторные</a>
            </div>

            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Назначение</div>
                <a href="/teplovye-nasosy/dlya-doma" class="sub-nav__link tf-btn-line">Для дома</a>
                <a href="/teplovye-nasosy/dlya-otopleniya" class="sub-nav__link tf-btn-line">Для отопления</a>
                <a href="/teplovye-nasosy/dlya-gvs" class="sub-nav__link tf-btn-line">Для ГВС</a>
                <a href="/montazh-teplovyh-nasosov" class="sub-nav__link tf-btn-line">Монтаж</a>
            </div>

            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Популярное</div>
                <a href="/teplovye-nasosy/hotta" class="sub-nav__link tf-btn-line">Hotta</a>
                <a href="/teplovye-nasosy/akcii" class="sub-nav__link tf-btn-line">Акции</a>
                <a href="/teplovye-nasosy/podbor" class="sub-nav__link tf-btn-line">Подбор насоса</a>
            </div>
        </div>
    </div>
</li>

<li class="has-sub-nav-category">
    <a href="/pelletnye-gorelki" class="nav-category_link">
        Пеллетные горелки
        <i class="icon icon-CaretRightThin"></i>
    </a>

    <div class="sub-nav-category">
        <div class="tf-grid-layout xl-col-3">
            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Мощность</div>
                <a href="/pelletnye-gorelki/25-kvt" class="sub-nav__link tf-btn-line">До 25 кВт</a>
                <a href="/pelletnye-gorelki/50-kvt" class="sub-nav__link tf-btn-line">До 50 кВт</a>
                <a href="/pelletnye-gorelki/100-kvt" class="sub-nav__link tf-btn-line">До 100 кВт</a>
                <a href="/pelletnye-gorelki/promyshlennye" class="sub-nav__link tf-btn-line">Промышленные</a>
            </div>

            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Серия</div>
                <a href="/pelletnye-gorelki/kotlov-xo" class="sub-nav__link tf-btn-line">KOTLOV XO</a>
                <a href="/pelletnye-gorelki/ceramic-pro" class="sub-nav__link tf-btn-line">Ceramic PRO</a>
                <a href="/pelletnye-gorelki/avtomaticheskie" class="sub-nav__link tf-btn-line">Автоматические</a>
                <a href="/pelletnye-gorelki/dlya-kotlov" class="sub-nav__link tf-btn-line">Для котлов</a>
            </div>

            <div class="sub-nav-category_list">
                <div class="sub-nav__title fw-semibold">Комплектующие</div>
                <a href="/pelletnye-gorelki/kontrollery" class="sub-nav__link tf-btn-line">Контроллеры</a>
                <a href="/pelletnye-gorelki/shneki" class="sub-nav__link tf-btn-line">Шнеки</a>
                <a href="/pelletnye-gorelki/bunkery" class="sub-nav__link tf-btn-line">Бункеры</a>
                <a href="/pelletnye-gorelki/avtomatika" class="sub-nav__link tf-btn-line">Автоматика</a>
            </div>
        </div>
    </div>
</li>

                        <li class="has-sub-nav-category">
                            <a href="/vodonagrevateli" class="nav-category_link">
                                Водонагреватели
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Тип</div>
                                        <a href="/vodonagrevateli/gazovye" class="sub-nav__link tf-btn-line">Газовые</a>
                                        <a href="/vodonagrevateli/elektricheskie" class="sub-nav__link tf-btn-line">Электрические</a>
                                        <a href="/vodonagrevateli/kosvennye" class="sub-nav__link tf-btn-line">Косвенные</a>
                                        <a href="/vodonagrevateli/kombinirovannye" class="sub-nav__link tf-btn-line">Комбинированные</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Назначение</div>
                                        <a href="/vodonagrevateli/dlya-doma" class="sub-nav__link tf-btn-line">Для дома</a>
                                        <a href="/vodonagrevateli/dlya-kvartiry" class="sub-nav__link tf-btn-line">Для квартиры</a>
                                        <a href="/vodonagrevateli/protochnye" class="sub-nav__link tf-btn-line">Проточные</a>
                                        <a href="/vodonagrevateli/nakopitelnye" class="sub-nav__link tf-btn-line">Накопительные</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Дополнительно</div>
                                        <a href="/bojlery" class="sub-nav__link tf-btn-line">Бойлеры</a>
                                        <a href="/montazh-vodonagrevatelej" class="sub-nav__link tf-btn-line">Монтаж</a>
                                        <a href="/akcii/vodonagrevateli" class="sub-nav__link tf-btn-line">Акции</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
                            <a href="/otoplenie" class="nav-category_link">
                                Отопление
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Оборудование</div>
                                        <a href="/radiatory" class="sub-nav__link tf-btn-line">Радиаторы</a>
                                        <a href="/teplyj-pol" class="sub-nav__link tf-btn-line">Тёплый пол</a>
                                        <a href="/nasosy/cirkulyacionnye" class="sub-nav__link tf-btn-line">Циркуляционные насосы</a>
                                        <a href="/konvektory" class="sub-nav__link tf-btn-line">Конвекторы</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Комплектующие</div>
                                        <a href="/truby-i-fitingi" class="sub-nav__link tf-btn-line">Трубы и фитинги</a>
                                        <a href="/kollektory" class="sub-nav__link tf-btn-line">Коллекторы и гребёнки</a>
                                        <a href="/rasshiritelnye-baki" class="sub-nav__link tf-btn-line">Расширительные баки</a>
                                        <a href="/bufernye-emkosti" class="sub-nav__link tf-btn-line">Буферные ёмкости</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Управление</div>
                                        <a href="/avtomatika" class="sub-nav__link tf-btn-line">Автоматика</a>
                                        <a href="/termoregulyatory" class="sub-nav__link tf-btn-line">Терморегуляторы</a>
                                        <a href="/gruppy-bezopasnosti" class="sub-nav__link tf-btn-line">Группы безопасности</a>
                                        <a href="/teplonositel" class="sub-nav__link tf-btn-line">Теплоноситель</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
                            <a href="/pechki" class="nav-category_link">
                                Печи
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Печи</div>
                                        <a href="/pechki/burzhujki" class="sub-nav__link tf-btn-line">Буржуйки</a>
                                        <a href="/pechki/dlya-dachi" class="sub-nav__link tf-btn-line">Для дачи</a>
                                        <a href="/pechki/drovyanie" class="sub-nav__link tf-btn-line">Дровяные</a>
                                        <a href="/pechki/s-plitoj" class="sub-nav__link tf-btn-line">С плитой</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">По конструкции</div>
                                        <a href="/pechki/chugunnye" class="sub-nav__link tf-btn-line">Чугунные</a>
                                        <a href="/pechki/stalnye" class="sub-nav__link tf-btn-line">Стальные</a>
                                        <a href="/pechki/uglovye" class="sub-nav__link tf-btn-line">Угловые</a>
                                        <a href="/pechki/s-vodyanym-konturom" class="sub-nav__link tf-btn-line">С водяным контуром</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Связанные разделы</div>
                                        <a href="/kaminy" class="sub-nav__link tf-btn-line">Камины</a>
                                        <a href="/dymohody" class="sub-nav__link tf-btn-line">Дымоходы</a>
                                        <a href="/aksessuary-dlya-pechej" class="sub-nav__link tf-btn-line">Аксессуары</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
                            <a href="/dlya-bani" class="nav-category_link">
                                Для бани
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Печи для бани</div>
                                        <a href="/dlya-bani/pechi" class="sub-nav__link tf-btn-line">Печи для бани</a>
                                        <a href="/dlya-bani/elektrokamenki" class="sub-nav__link tf-btn-line">Электрокаменки</a>
                                        <a href="/dlya-bani/s-bakom" class="sub-nav__link tf-btn-line">Печи с баком</a>
                                        <a href="/dlya-bani/s-vynosnoj-topkoj" class="sub-nav__link tf-btn-line">С выносной топкой</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Товары для бани</div>
                                        <a href="/dlya-bani/kamni" class="sub-nav__link tf-btn-line">Камни для бани</a>
                                        <a href="/dlya-bani/dveri" class="sub-nav__link tf-btn-line">Двери</a>
                                        <a href="/dlya-bani/baki" class="sub-nav__link tf-btn-line">Баки</a>
                                        <a href="/dlya-bani/kupeli" class="sub-nav__link tf-btn-line">Купели</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Комплектующие</div>
                                        <a href="/dlya-bani/dymohody" class="sub-nav__link tf-btn-line">Дымоходы для бани</a>
                                        <a href="/dlya-bani/zaparniki" class="sub-nav__link tf-btn-line">Запарники</a>
                                        <a href="/dlya-bani/aksessuary" class="sub-nav__link tf-btn-line">Аксессуары</a>
                                        <a href="/dlya-bani/otdelka" class="sub-nav__link tf-btn-line">Отделка</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
                            <a href="/kaminy" class="nav-category_link">
                                Камины
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Камины</div>
                                        <a href="/kaminy/topki" class="sub-nav__link tf-btn-line">Топки</a>
                                        <a href="/kaminy/pechi-kaminy" class="sub-nav__link tf-btn-line">Печи-камины</a>
                                        <a href="/kaminy/portaly" class="sub-nav__link tf-btn-line">Порталы</a>
                                        <a href="/kaminy/elektrokaminy" class="sub-nav__link tf-btn-line">Электрокамины</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">По форме</div>
                                        <a href="/kaminy/pryamoe-steklo" class="sub-nav__link tf-btn-line">Прямое стекло</a>
                                        <a href="/kaminy/bokovoe-osteklenie" class="sub-nav__link tf-btn-line">Боковое остекление</a>
                                        <a href="/kaminy/trehstoronnie" class="sub-nav__link tf-btn-line">Трёхсторонние</a>
                                        <a href="/kaminy/uglovye" class="sub-nav__link tf-btn-line">Угловые</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Дополнительно</div>
                                        <a href="/kaminy/aksessuary" class="sub-nav__link tf-btn-line">Аксессуары</a>
                                        <a href="/kaminy/reshetki" class="sub-nav__link tf-btn-line">Решётки</a>
                                        <a href="/kaminy/materialy-dlya-montazha" class="sub-nav__link tf-btn-line">Материалы для монтажа</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="has-sub-nav-category">
                            <a href="/dymohody" class="nav-category_link">
                                Дымоходы
                                <i class="icon icon-CaretRightThin"></i>
                            </a>

                            <div class="sub-nav-category">
                                <div class="tf-grid-layout xl-col-3">
                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Тип дымохода</div>
                                        <a href="/dymohody/nerzhaveyushchie" class="sub-nav__link tf-btn-line">Нержавеющие</a>
                                        <a href="/dymohody/keramicheskie" class="sub-nav__link tf-btn-line">Керамические</a>
                                        <a href="/dymohody/koaksialnye" class="sub-nav__link tf-btn-line">Коаксиальные</a>
                                        <a href="/dymohody/ovalnye" class="sub-nav__link tf-btn-line">Овальные</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Комплектующие</div>
                                        <a href="/dymohody/truby" class="sub-nav__link tf-btn-line">Трубы</a>
                                        <a href="/dymohody/kolena" class="sub-nav__link tf-btn-line">Колена</a>
                                        <a href="/dymohody/troyniki" class="sub-nav__link tf-btn-line">Тройники</a>
                                        <a href="/dymohody/krepleniya" class="sub-nav__link tf-btn-line">Крепления</a>
                                    </div>

                                    <div class="sub-nav-category_list">
                                        <div class="sub-nav__title fw-semibold">Применение</div>
                                        <a href="/dymohody/dlya-kotlov" class="sub-nav__link tf-btn-line">Для котлов</a>
                                        <a href="/dymohody/dlya-kaminov" class="sub-nav__link tf-btn-line">Для каминов</a>
                                        <a href="/dymohody/dlya-bani" class="sub-nav__link tf-btn-line">Для бани</a>
                                        <a href="/montazh-dymohodov" class="sub-nav__link tf-btn-line">Монтаж дымоходов</a>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li>
                            <a href="/vodоснабжение" class="nav-category_link">
                                Водоснабжение
                                <i class="icon icon-CaretRightThin"></i>
                            </a>
                        </li>

                        <li>
                            <a href="/klimat" class="nav-category_link">
                                Климат
                                <i class="icon icon-CaretRightThin"></i>
                            </a>
                        </li>

                        <li>
                            <a href="/catalog" class="nav-category_link">
                                Весь каталог
                            </a>
                        </li>

                    </ul>
                </div>
            </div>

            <div class="col-center">
                <form action="/search" method="get" class="form_search-product style-2 radius-8">
                    <div class="select-category">
                        <select name="category" id="product_cate" class="dropdown_product_cate">
                            <option value="" selected="selected">Все категории</option>
                            <option class="level-0" value="kotly">Котлы</option>
                            <option class="level-0" value="teplovye-nasosy">Тепловые насосы</option>
                            <option class="level-0" value="pelletnye-gorelki">Пеллетные горелки</option>
                            <option class="level-0" value="vodonagrevateli">Водонагреватели</option>
                            <option class="level-0" value="otoplenie">Отопление</option>
                            <option class="level-0" value="pechki">Печи</option>
                            <option class="level-0" value="dlya-bani">Для бани</option>
                            <option class="level-0" value="kaminy">Камины</option>
                            <option class="level-0" value="dymohody">Дымоходы</option>
                            <option class="level-0" value="vodosnabzhenie">Водоснабжение</option>
                            <option class="level-0" value="klimat">Климат</option>
                        </select>
                    </div>

                    <span class="br-line type-vertical"></span>

                    <fieldset class="fieldset-search">
                        <input
                            class="ipt"
                            type="text"
                            name="q"
                            placeholder="Поиск по каталогу"
                            required
                        >

                        <button type="submit" class="btn-action">
                            <i class="icon icon-MagnifyingGlass"></i>
                        </button>
                    </fieldset>
                </form>
            </div>

            <div class="col-right">
                <ul class="nav-icon-list">
                    <li>
                        <a href="#sign" data-bs-toggle="modal" class="nav-icon-item link has-text">
                            <i class="icon icon-User"></i>
                            <span class="d-none d-md-block">Войти</span>
                        </a>
                    </li>

                    <li class="d-none d-sm-block">
                        <a href="/wishlist" class="nav-icon-item link">
                            <i class="icon icon-HeartStraight"></i>
                        </a>
                    </li>

                    <li>
                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="nav-icon-item link shop-cart">
                            <i class="icon icon-Handbag"></i>
                            <span class="count">{{ $cartCount ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</div>
</header>
<!-- /Header -->