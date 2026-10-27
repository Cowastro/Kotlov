@extends('layouts.amerce')

@section('title', 'B2B-каталог KOTLOV')
@section('robots', 'noindex, nofollow')
@section('body-class', 'b2b-body')

@section('content')
@php
    $cartCount = collect(session('cart', []))->sum('quantity');
    $preview = request()->boolean('b2b-preview');
    $resetUrl = route('b2b.catalog', array_filter(['mode' => $mode, 'b2b-preview' => $preview ? 1 : null]));
    $mobileCategoryTiles = $categoryTree->take(11);
@endphp

<div class="b2b-portal">
    <header class="b2b-topbar">
        <div class="b2b-topbar__inner">
            <a href="{{ route('b2b.catalog', array_filter(['b2b-preview' => $preview ? 1 : null])) }}" class="b2b-brand" aria-label="B2B KOTLOV">
                <img src="{{ asset('img/logo.svg') }}" width="126" height="26" alt="KOTLOV">
                <span>B2B</span>
            </a>
            <div class="b2b-topbar__company">
                <small>Оптовый кабинет</small>
                <strong>{{ auth()->user()->company_name ?: auth()->user()->name }}</strong>
            </div>
            <nav class="b2b-topbar__actions" aria-label="B2B-навигация">
                <a href="{{ route('account') }}" class="b2b-topbar__link"><i class="icon icon-User"></i><span>Заказы</span></a>
                <a href="{{ route('cart') }}" class="b2b-topbar__link b2b-topbar__cart">
                    <i class="icon icon-Handbag"></i><span>Корзина</span><b class="cart-count">{{ $cartCount }}</b>
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="b2b-topbar__logout">Выйти</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="b2b-workspace">
        <section class="b2b-page-heading">
            <div><p>B2B · рабочий каталог</p><h1>Быстрый заказ товаров</h1></div>
            <button type="button" class="b2b-mobile-catalog-button" data-catalog-open><span>☰</span> Категории</button>
        </section>

        <form method="GET" action="{{ route('b2b.catalog') }}" class="b2b-command-bar" id="b2b-filter-form" autocomplete="off">
            <input type="hidden" name="mode" value="{{ $mode }}">
            @if ($categoryId > 0)<input type="hidden" name="category" value="{{ $categoryId }}">@endif
            @if ($preview)<input type="hidden" name="b2b-preview" value="1">@endif

            <div class="b2b-search" data-suggest-url="{{ route('b2b.catalog.suggest', array_filter(['mode' => $mode, 'b2b-preview' => $preview ? 1 : null])) }}">
                <i class="icon icon-MagnifyingGlass" aria-hidden="true"></i>
                <input id="b2b-search-input" type="search" name="q" value="{{ $query }}"
                    placeholder="Введите название, артикул, бренд или категорию" aria-label="Поиск по B2B-каталогу"
                    aria-autocomplete="list" aria-controls="b2b-search-suggest" aria-expanded="false">
                @if ($query !== '')
                    <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" class="b2b-search__clear" aria-label="Очистить поиск">×</a>
                @else
                    <kbd>/</kbd>
                @endif
                <button type="submit">Найти</button>
                <div class="b2b-search-suggest" id="b2b-search-suggest" role="listbox" hidden></div>
            </div>

            <div class="b2b-command-bar__filters">
                <div class="b2b-mode-switch" aria-label="Режим каталога">
                    <a href="{{ request()->fullUrlWithQuery(['mode' => 'orderable', 'page' => null]) }}" class="{{ $mode === 'orderable' ? 'active' : '' }}">В наличии и под заказ</a>
                    <a href="{{ request()->fullUrlWithQuery(['mode' => 'reference', 'page' => null, 'source' => null]) }}" class="{{ $mode === 'reference' ? 'active' : '' }}">Справочный каталог</a>
                </div>
                <div class="b2b-filter-selects">
                    <label><span>Бренд</span><select name="brand" data-auto-submit>
                        <option value="">Все бренды</option>
                        @foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected($brandId === (int) $brand->id)>{{ $brand->name }}</option>@endforeach
                    </select></label>
                    @if ($mode === 'orderable' && $sourceGroups->count() > 1)
                        <label><span>Поставщик</span><select name="source" data-auto-submit>
                            <option value="">Все поставщики</option>
                            @foreach ($sourceGroups as $sourceGroup)<option value="{{ $sourceGroup['key'] }}" @selected($sourceKey === $sourceGroup['key'])>{{ $sourceGroup['label'] }}</option>@endforeach
                        </select></label>
                    @endif
                    @if ($mode === 'orderable')
                        <label><span>Сортировка</span><select name="sort" data-auto-submit>
                            <option value="name" @selected($sort === 'name')>По названию</option>
                            <option value="stock" @selected($sort === 'stock')>Сначала в наличии</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Сначала дешевле</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Сначала дороже</option>
                        </select></label>
                    @endif
                    @if ($query !== '' || $categoryId > 0 || $brandId > 0 || $sourceKey !== '' || $sort !== 'name')<a href="{{ $resetUrl }}" class="b2b-reset-filters">Сбросить</a>@endif
                </div>
            </div>
        </form>

        <section class="b2b-mobile-category-showcase" aria-label="Основные категории">
            <div class="b2b-mobile-category-showcase__heading">
                <strong>Каталог товаров</strong>
                <button type="button" data-catalog-open>Все разделы <span>→</span></button>
            </div>
            <div class="b2b-mobile-category-grid">
                @foreach ($mobileCategoryTiles as $category)
                    <a href="{{ request()->fullUrlWithQuery(['category' => $category['id'], 'page' => null]) }}" class="b2b-mobile-category-tile {{ $category['selected'] ? 'active' : '' }}">
                        <span class="b2b-mobile-category-tile__image">
                            <img src="{{ $category['image'] }}" alt="" loading="lazy" onerror="this.src='{{ asset('img/popular/catalog.jpg') }}'">
                            <b>{{ $category['count'] }}</b>
                        </span>
                        <span>{{ $category['name'] }}</span>
                    </a>
                @endforeach
                <button type="button" class="b2b-mobile-category-tile b2b-mobile-category-tile--all" data-catalog-open>
                    <span class="b2b-mobile-category-tile__image"><i class="icon icon-List"></i></span>
                    <span>Все категории</span>
                </button>
            </div>
        </section>

        <div class="b2b-catalog-layout">
            <div class="b2b-catalog-overlay" data-catalog-close></div>
            <aside class="b2b-category-panel" id="b2b-category-panel">
                <div class="b2b-category-panel__heading">
                    <div><small>Навигация</small><strong>Категории</strong></div>
                    <button type="button" class="b2b-category-panel__close" data-catalog-close aria-label="Закрыть">×</button>
                </div>
                <label class="b2b-category-search"><i class="icon icon-MagnifyingGlass"></i><input type="search" id="b2b-category-search" placeholder="Найти раздел" aria-label="Поиск категории"></label>
                <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}" class="b2b-category-all {{ $categoryId === 0 ? 'active' : '' }}">
                    <span>Все товары</span><span class="b2b-category-count">{{ $categoryTree->sum('count') }}</span>
                </a>
                <div class="b2b-category-tree" id="b2b-category-tree">@include('partials.b2b-category-tree', ['nodes' => $categoryTree, 'level' => 0])</div>
                <p class="b2b-category-empty" hidden>Раздел не найден</p>
            </aside>

            <section class="b2b-catalog-results">
                <div class="b2b-results-toolbar">
                    <div><strong>{{ number_format($products->total(), 0, ',', ' ') }}</strong><span>товаров</span>@if ($query !== '')<em>по запросу «{{ $query }}»</em>@endif</div>
                    @if ($mode === 'reference')
                        <p>Нет оптовой цены? Отправьте запрос менеджеру из строки товара.</p>
                    @else
                        <p><span class="b2b-live-dot"></span> Цены и остатки из разрешённых источников</p>
                    @endif
                </div>

                @if ($products->count())
                    <div class="b2b-product-table {{ $mode === 'reference' ? 'is-reference' : '' }}">
                        <div class="b2b-product-table__head" aria-hidden="true">
                            <span>Товар</span>
                            @if ($mode === 'orderable')<span>Цена</span><span>Наличие</span><span>Количество</span>@else<span>Розничная цена</span><span>Запрос оптовой цены</span>@endif
                        </div>
                        @foreach ($products as $item)
                            @php
                                $product = $mode === 'orderable' ? $item['product'] : $item;
                                $productUrl = '/'.($product->category?->slug ?? 'catalog').'/'.$product->slug;
                                $priceRequest = $priceRequests->get($product->id);
                                $priceRequestIsOpen = $priceRequest && in_array($priceRequest->status, \App\Models\ContactRequest::OPEN_STATUSES, true);
                            @endphp
                            <article class="b2b-product-row">
                                <div class="b2b-product-main">
                                    <a href="{{ $productUrl }}" class="b2b-product-image" target="_blank" rel="noopener"><img src="{{ $product->image_url }}" alt="" loading="lazy" onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'"></a>
                                    <div class="b2b-product-copy">
                                        <div class="b2b-product-meta">@if($product->sku)<span class="b2b-sku">{{ $product->sku }}</span>@endif<span>{{ $product->category?->name }}</span>@if($product->brand)<span>{{ $product->brand->name }}</span>@endif</div>
                                        <a href="{{ $productUrl }}" target="_blank" rel="noopener" class="b2b-product-name">{{ $product->name }}</a>
                                    </div>
                                </div>
                                @if ($mode === 'orderable')
                                    <div class="b2b-price-cell"><strong>{{ number_format($item['price'], 2, ',', ' ') }} <small>BYN</small></strong>@if ($item['retail_price'] > 0)<span>Розница {{ number_format($item['retail_price'], 2, ',', ' ') }}</span>@endif</div>
                                    <div class="b2b-stock-cell"><strong><span class="b2b-live-dot"></span>{{ $item['stock_label'] }}</strong><span>{{ $item['offer']->source?->partnerName() }}</span></div>
                                    <div class="b2b-buy-cell">
                                        <input id="b2b-qty-{{ $product->id }}" type="number" value="1" min="1" max="999" inputmode="numeric" aria-label="Количество">
                                        <button type="button" class="b2b-add-button b2b-add-to-cart" data-product-id="{{ $product->id }}" data-qty-input="#b2b-qty-{{ $product->id }}"><i class="icon icon-Handbag"></i><span>Добавить</span></button>
                                    </div>
                                @else
                                    <div class="b2b-price-cell"><strong>{{ (float) $product->price > 0 ? number_format((float) $product->price, 2, ',', ' ') : '—' }} <small>BYN</small></strong><span>Справочная цена</span></div>
                                    <div class="b2b-request-cell">
                                        @if ($priceRequest)<div class="b2b-price-request-state" data-price-request-state="{{ $product->id }}"><strong>{{ \App\Models\ContactRequest::$statuses[$priceRequest->status] ?? $priceRequest->status }}</strong>@if ($priceRequest->client_response)<p>{{ $priceRequest->client_response }}</p>@elseif ($priceRequestIsOpen)<span>Менеджер проверяет цену и срок</span>@endif</div>@endif
                                        @if (! $priceRequestIsOpen)<form class="b2b-price-request-form" data-product-id="{{ $product->id }}"><input name="quantity" type="number" value="1" min="1" max="999" inputmode="numeric" aria-label="Количество"><button type="submit">Запросить оптовую цену</button></form>@endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    @include('partials.b2b-pagination', ['paginator' => $products])
                @else
                    <div class="b2b-empty-state"><i class="icon icon-MagnifyingGlass"></i><h2>Ничего не найдено</h2><p>Измените запрос или выберите другой раздел каталога.</p><a href="{{ $resetUrl }}">Показать все товары</a></div>
                @endif
            </section>
        </div>
    </main>
    <div class="b2b-toast" role="status" aria-live="polite" hidden></div>
</div>

@push('styles')
    @include('pages.b2b.styles')
@endpush
@push('scripts')
    @include('pages.b2b.scripts')
@endpush
@endsection
