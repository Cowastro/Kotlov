<!-- Topbar -->
<div class="tf-topbar bg-dark">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center text-white">

            <div class="d-flex align-items-center gap-2">
                <span class="icon icon-MapPin"></span>
                <a href="#citySelector" data-bs-toggle="offcanvas" class="text-white d-flex align-items-center gap-1">


                    <span>{{ $cityName ?? $geo ?? 'Минск' }}</span>

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
    <div class="box-open-header-bottom m-0 d-none d-xl-flex">
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

                <!-- MAIN MENU -->
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

                        @foreach ($navCategories as $rootCat)
                            @php
                                $icons = config('navigation.icons', []);
                                $iconClasses = config('navigation.icon_classes', []);
                                $editorial = config('navigation.editorial.' . $rootCat->slug, []);
                                $liveCategoryUrls = $navCategoryUrls ?? [];
                                $isLiveCategoryUrl = function (?string $url) use ($liveCategoryUrls) {
                                    $path = parse_url((string) $url, PHP_URL_PATH);

                                    if (! is_string($path) || $path === '') {
                                        return true;
                                    }

                                    $servicePaths = ['/akcii', '/brands', '/catalog', '/contacts', '/dostavka', '/installers', '/blog'];

                                    if (in_array($path, $servicePaths, true)) {
                                        return true;
                                    }

                                    return isset($liveCategoryUrls[$path]);
                                };
                                $editorial = collect($editorial)
                                    ->map(function ($block) use ($isLiveCategoryUrl) {
                                        $block['links'] = collect($block['links'] ?? [])
                                            ->filter(fn ($link) => $isLiveCategoryUrl($link['url'] ?? null))
                                            ->values()
                                            ->all();

                                        return $block;
                                    })
                                    ->filter(fn ($block) => ! empty($block['links']))
                                    ->values()
                                    ->all();
                                $icon = $icons[$rootCat->slug] ?? null;
                                $iconClass = $iconClasses[$rootCat->slug] ?? null;
                                $children = $rootCat->children->where('is_active', true);
                                $hasChildren = $children->count() > 0 || count($editorial) > 0;
                                $cols = config('navigation.columns.' . $rootCat->slug, 2);
                            @endphp

                            <li class="{{ $hasChildren ? 'has-sub-nav-category' : '' }}">
                                <a href="/{{ $rootCat->slug }}" class="nav-category_link">
                                    <span class="d-flex align-items-center gap-2">
                                        @if ($iconClass)
                                            <i class="icon {{ $iconClass }} cat-icon cat-icon--font"></i>
                                        @elseif ($icon)
                                            <img src="{{ asset('icons/' . $icon) }}"
                                                alt="{{ $rootCat->name }}"
                                                class="cat-icon">
                                        @endif
                                        <span>{{ $rootCat->name }}</span>
                                    </span>
                                    @if ($hasChildren)
                                        <i class="icon icon-CaretRightThin"></i>
                                    @endif
                                </a>

                                @if ($hasChildren)
                                    @php $promo = config('navigation.promo.' . $rootCat->slug, null); @endphp
                                    <div class="sub-nav-category">
                                        <div class="mega-inner">

                                            {{-- Левая/центральная зона: подкатегории + editorial --}}
                                            <div class="mega-links">
                                                <div class="tf-grid-layout xl-col-{{ $cols }}">
                                                    @if ($children->count() > 0)
                                                        <div class="sub-nav-category_list">
                                                            <div class="sub-nav__title fw-semibold">{{ $rootCat->name }}</div>
                                                            @foreach ($children->take(10) as $child)
                                                                <a href="/{{ $child->slug }}" class="sub-nav__link tf-btn-line">
                                                                    {{ $child->name }}
                                                                </a>
                                                            @endforeach
                                                            @if ($children->count() > 10)
                                                                <a href="/{{ $rootCat->slug }}" class="sub-nav__link tf-btn-line cl-text-2">
                                                                    Все разделы →
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @foreach ($editorial as $block)
                                                        <div class="sub-nav-category_list">
                                                            <div class="sub-nav__title fw-semibold">{{ $block['title'] }}</div>
                                                            @foreach ($block['links'] as $link)
                                                                <a href="{{ $link['url'] }}" class="sub-nav__link tf-btn-line">
                                                                    {{ $link['name'] }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- Правая promo-колонка --}}
                                            <div class="mega-promo">
                                                @if ($promo)
                                                    @php
                                                        $promoCta = collect($promo['cta'] ?? [])
                                                            ->filter(fn ($link) => $isLiveCategoryUrl($link['url'] ?? null))
                                                            ->values();
                                                        $promoBanner = $promo['banner'] ?? null;
                                                    @endphp
                                                    @if (!empty($promo['brands']))
                                                        @php
                                                            $validBrands = collect($promo['brands'])
                                                                ->map(fn($name) => $navBrands[Str::slug($name)] ?? null)
                                                                ->filter();
                                                        @endphp
                                                        @if($validBrands->isNotEmpty())
                                                            <div class="mega-promo__section">
                                                                <div class="mega-promo__label">Популярные бренды</div>
                                                                <div class="mega-promo__brands">
                                                                    @foreach ($validBrands as $brand)
                                                                        <a href="/brands/{{ $brand->slug }}" class="mega-brand-chip">{{ $brand->name }}</a>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endif

                                                    @if (!empty($promoBanner) && $isLiveCategoryUrl($promoBanner['url'] ?? null))
                                                        <a href="{{ $promo['banner']['url'] }}" class="mega-promo__banner">
                                                            <img src="{{ asset('img/' . $promo['banner']['img']) }}"
                                                                 alt="{{ $promo['banner']['title'] }}"
                                                                 loading="lazy">
                                                            <span class="mega-promo__banner-label">{{ $promo['banner']['title'] }}</span>
                                                        </a>
                                                    @endif

                                                    @if ($promoCta->isNotEmpty())
                                                        <div class="mega-promo__cta">
                                                            @foreach ($promoCta as $link)
                                                                <a href="{{ $link['url'] }}" class="mega-promo__cta-link">
                                                                    {{ $link['name'] }} →
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @else
                                                    {{-- Дефолтная promo-панель --}}
                                                    <div class="mega-promo__section">
                                                        <div class="mega-promo__label">Быстрые ссылки</div>
                                                        <div class="mega-promo__cta">
                                                            <a href="/akcii" class="mega-promo__cta-link">Акции и скидки →</a>
                                                            <a href="/installers" class="mega-promo__cta-link">Монтаж →</a>
                                                            <a href="/brands" class="mega-promo__cta-link">Все бренды →</a>
                                                        </div>
                                                    </div>
                                                    <a href="/akcii" class="mega-promo__banner">
                                                        <img src="{{ asset('img/banners/banner-sale.jpg') }}"
                                                             alt="Акции" loading="lazy">
                                                        <span class="mega-promo__banner-label">Акции и спецпредложения</span>
                                                    </a>
                                                @endif

                                                {{-- Общая ссылка «Монтаж» всегда внизу --}}
                                                <a href="/installers" class="mega-promo__install-cta">
                                                    <span>Монтаж и установка</span>
                                                    <span class="mega-promo__install-sub">Профессиональный монтаж по Беларуси</span>
                                                </a>
                                            </div>

                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach

                        <li>
                            <a href="/catalog" class="nav-category_link">
                                Весь каталог
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-center">
                <form action="/search" method="get" class="form_search-product style-2 radius-8" autocomplete="off">
                    <div class="select-category">
                        <select name="category" id="product_cate" class="dropdown_product_cate">
                            <option value="" selected="selected">Все категории</option>
                            @foreach ($navCategories as $cat)
                                <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="br-line type-vertical"></span>
                    <fieldset class="fieldset-search" style="position:relative;flex:1;">
                        <input class="ipt" type="text" name="q" id="header-search-input"
                            placeholder="Поиск по каталогу" autocomplete="off">
                        <button type="submit" class="btn-action">
                            <i class="icon icon-MagnifyingGlass"></i>
                        </button>
                        <div id="search-suggest" class="search-suggest-dropdown"></div>
                    </fieldset>
                </form>
            </div>

            <div class="col-right">
                <ul class="nav-icon-list">
                    <li class="position-relative">
                        @auth
                            {{-- Залогинен: дропдаун с кабинетом и выходом --}}
                            <div class="dropdown">
                                <a href="#" class="nav-icon-item link has-text dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    style="text-decoration:none;">
                                    <i class="icon icon-User"></i>
                                    <span class="d-none d-md-block" style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ Str::limit(Auth::user()->name, 12) }}
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" style="min-width:180px;">
                                    <li>
                                        <a class="dropdown-item" href="/account">
                                            <i class="icon icon-User me-2"></i> Личный кабинет
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="/wishlist">
                                            <i class="icon icon-HeartStraight me-2"></i> Избранное
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="/logout" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="icon icon-SignOut me-2"></i> Выйти
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        @else
                            {{-- Не залогинен: открываем модалку входа --}}
                            <a href="#sign" data-bs-toggle="modal" class="nav-icon-item link has-text">
                                <i class="icon icon-User"></i>
                                <span class="d-none d-md-block">Войти</span>
                            </a>
                        @endauth
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
