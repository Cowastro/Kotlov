@extends('layouts.amerce')

@section('body-class', 'product-detail-page')

@if($product->is_archived)
@section('robots', 'noindex, follow')
@endif

@section('content')
<main id="wrapper" class="product-page-shell">

    <div class="mobile-product-topbar d-md-none">
        <a href="{{ $product->category ? '/' . $product->category->slug : '/catalog' }}"
            class="mobile-product-topbar__action" aria-label="Вернуться в категорию">
            <i class="icon icon-CaretLeft"></i>
        </a>
        <p class="mobile-product-topbar__title">{{ Str::limit($product->h1 ?: $product->name, 38) }}</p>
        <a href="#search" data-bs-toggle="modal" class="mobile-product-topbar__action" aria-label="Поиск">
            <i class="icon icon-MagnifyingGlass"></i>
        </a>
    </div>

    @if (session('success'))
    <div class="container mt-16">
        <div class="alert alert-success mb-20">{{ session('success') }}</div>
    </div>
    @endif

    @if ($errors->any())
    <div class="container mt-16">
        <div class="alert alert-danger mb-20">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    @if($product->is_archived)
    <div class="container mt-16">
        <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 20px;color:#856404;">
            <strong>Товар снят с продажи.</strong> Он больше не доступен для заказа. Посмотрите похожие товары в разделе ниже.
        </div>
    </div>
    @endif

    {{-- Хлебные крошки --}}
    <div class="section-page-title-single flat-spacing-3">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if ($product->category)
                        @if ($product->category->parent_id && $product->category->parent)
                            <a href="/{{ $product->category->parent->slug }}"
                                class="text-caption-01 cl-text-3 link">
                                {{ $product->category->parent->name }}
                            </a>
                            <i class="icon icon-CaretRightThin cl-text-3"></i>
                        @endif
                        <a href="/{{ $product->category->slug }}" class="text-caption-01 cl-text-3 link">
                            {{ $product->category->name }}
                        </a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @endif
                    <p class="text-caption-01">{{ Str::limit($product->name, 50) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Основной блок товара --}}
    <section class="section-product-single tf-main-product section-image-zoom {{ str_starts_with($product->slug, 'pelletnaya-gorelka-hotta-ceramik-') ? 'hotta-product-gallery' : '' }}">
        <div class="container">
            <div class="row">

                {{-- Галерея --}}
                <div class="col-md-6">
                    <div class="tf-product-media-wrap sticky-top">
                        @php
                            $isWishlisted = in_array((int) $product->id, $wishlistProductIds ?? [], true);
                        @endphp
                        <div class="product-thumbs-slider style-row row_left">
                            <div class="flat-wrap-media-product">
                                <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started"
                                    data-spacing="0">
                                    <div class="swiper-wrapper">
                                        @php
                                            $images = $product->imagePaths();
                                            $placeholder = asset('img/products/product-placeholder.jpg');
                                        @endphp
                                        @forelse ($images as $index => $img)
                                            @php $imgUrl = $product->imageUrl($index); @endphp
                                            <div class="swiper-slide">
                                                <a href="{{ $imgUrl }}" target="_blank"
                                                    class="item" data-pswp-width="576px" data-pswp-height="768px">
                                                    <img loading="lazy" width="576" height="768"
                                                        class="tf-image-zoom"
                                                        data-zoom="{{ $imgUrl }}"
                                                        src="{{ $imgUrl }}"
                                                        alt="{{ $product->name }}"
                                                        onerror="this.src='{{ $placeholder }}'">
                                                </a>
                                            </div>
                                        @empty
                                            <div class="swiper-slide">
                                                <a href="{{ $placeholder }}" class="item">
                                                    <img loading="lazy" width="576" height="768"
                                                        class="tf-image-zoom"
                                                        src="{{ $placeholder }}"
                                                        alt="{{ $product->name }}">
                                                </a>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            {{-- Миниатюры --}}
                            @if (count($images) > 1)
                                <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom"
                                    data-direction="vertical" data-preview="7">
                                    <div class="swiper-wrapper stagger-wrap">
                                        @foreach ($images as $index => $img)
                                            @php $imgUrl = $product->imageUrl($index); @endphp
                                            <div class="swiper-slide stagger-item">
                                                <div class="item">
                                                    <img loading="lazy" width="82" height="110"
                                                        src="{{ $imgUrl }}"
                                                        alt="{{ $product->name }}"
                                                        onerror="this.src='{{ $placeholder }}'">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="mobile-product-media-actions d-md-none" aria-label="Действия с товаром">
                            <a href="#share" data-bs-toggle="modal" class="mobile-product-media-action" aria-label="Поделиться товаром">
                                <i class="icon icon-ShareNetwork"></i>
                            </a>
                            <a href="#" data-product-id="{{ $product->id }}"
                                class="mobile-product-media-action wishlist-toggle{{ $isWishlisted ? ' is-active' : '' }}"
                                aria-label="{{ $isWishlisted ? 'Удалить товар из избранного' : 'Добавить товар в избранное' }}"
                                aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}">
                                <i class="icon {{ $isWishlisted ? 'icon-Heart' : 'icon-heart' }}" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Информация --}}
                <div class="col-md-6">
                    <div class="tf-product-info-wrap position-relative mt-md-0">
                        <div class="tf-zoom-main sticky-top"></div>
                        <div class="tf-product-info-list other-image-zoom">

                            <div class="tf-product-info-heading">

                                @if ($heatPumpProfile ?? null)
                                    <p class="kotlov-hp-line-badge">Наша линейка · KOTLOV GE</p>
                                @endif

                                {{-- Категория --}}
                                @if ($product->category)
                                    <p class="product-infor-cate text-caption-01 mb-4">
                                        <a href="/{{ $product->category->slug }}" class="cl-text-2 link">
                                            {{ $product->category->name }}
                                        </a>
                                    </p>
                                @endif

                                {{-- Название --}}
                                <h1 class="product-infor-name mb-12 h3">
                                    {{ $product->h1 ?: $product->name }}
                                </h1>

                                {{-- Мета --}}
                                <div class="product-infor-meta mb-20">
                                    @if ($product->rating > 0)
                                        <div class="meta_rate">
                                            <div class="star-wrap normal d-flex align-items-center">
                                                @for ($s = 1; $s <= 5; $s++)
                                                    <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-caption-01 cl-text-2">
                                                ({{ $product->reviews_count }} отзывов)
                                            </span>
                                        </div>
                                        <div class="br-line type-vertical"></div>
                                    @endif

                                    @if ($product->sku)
                                        <div class="meta_prd_code text-caption-01">
                                            <span class="cl-text-2">Артикул:</span>
                                            <span>{{ $product->sku }}</span>
                                        </div>
                                    @endif

                                    @if ($product->brand)
                                        <div class="br-line type-vertical"></div>
                                        <div class="meta_prd_code text-caption-01">
                                            <span class="cl-text-2">Бренд:</span>
                                            <a href="/brands/{{ $product->brand->slug }}" class="link fw-medium">
                                                {{ $product->brand->name }}
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                @if ($product->brand && mb_strtolower(trim($product->brand->name)) === 'dab')
                                    <a href="https://dna.dabpumps.com/Region.aspx?ReturnUrl=%2fStartMain.aspx"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="link fw-medium d-inline-flex align-items-center gap-1 mt-8 mb-4">
                                        Подбор насоса DAB
                                        <i class="icon icon-ArrowUpRight"></i>
                                    </a>
                                @endif

                                @php
                                    $b2bComparison = app(\App\Services\B2bCatalogOfferResolver::class)->comparison($product, auth()->user());
                                    $b2bOffer = $b2bComparison['offer'] ?? null;
                                    $isProcurementOffer = $b2bOffer && $b2bOffer->source?->isProcurementMode();
                                    $hasAssignedSupplier = $product->hasAssignedSupplier();
                                    if (!$product->is_active || $product->is_archived) {
                                        $availabilityStatus = 'out_of_stock';
                                    } elseif ($b2bOffer && (float) $b2bOffer->stock_quantity > 0) {
                                        $availabilityStatus = 'in_stock';
                                    } else {
                                        $availabilityStatus = method_exists($product, 'effectiveAvailabilityStatus')
                                            ? $product->publicAvailabilityStatus()
                                            : 'check';
                                    }
                                    $canBuy = $b2bOffer
                                        ? $hasAssignedSupplier && $product->is_active && ! $product->is_archived && app(\App\Services\B2bCatalogOfferResolver::class)->canOrder($b2bOffer)
                                        : $product->canBeAddedToCart();
                                    $availabilityLabel = $b2bOffer
                                        ? (\App\Models\Product::availabilityStatusOptions()[$availabilityStatus] ?? 'Уточняйте наличие')
                                        : $product->publicAvailabilityLabel();
                                    $displayAvailabilityStatus = $b2bOffer ? $availabilityStatus : $product->publicAvailabilityStatus();
                                    $displayPrice = $b2bComparison['wholesale_price'] ?? (float) $product->price;
                                    $onlineDiscountPercent = (float) config('shop.online_order_discount.percent', 3);
                                    $onlinePrice = ! $b2bOffer && $canBuy && $product->qualifiesForOnlineOrderDiscount() && (float) $product->price > 0
                                        ? round((float) $product->price * (1 - $onlineDiscountPercent / 100), 2)
                                        : null;
                                    $onlineSaving = $onlinePrice !== null ? round((float) $product->price - $onlinePrice, 2) : null;
                                    $showPreviewPrice = request()->boolean('admin-preview')
                                        && auth()->user()?->isManager()
                                        && (float) $product->price > 0;
                                    $b2bWarehouseStocks = $b2bOffer
                                        ? $product->warehouseStocks()->available()->orderBy('delivery_days')->get()
                                        : collect();
                                    $showMinskB2bWarehouse = $b2bOffer
                                        && app(\App\Services\B2bCatalogOfferResolver::class)->hasFreshStock($b2bOffer);
                                @endphp

                                {{-- Цена --}}
                                <div class="product-infor-price mb-12">
                                    @if ($product->is_archived)
                                        <h4 class="price-on-sale text-muted">Снят с продажи</h4>
                                    @elseif ($b2bOffer)
                                        <div>
                                            @if (auth()->user()?->isAdmin() && request()->boolean('b2b-preview'))
                                                <div class="alert alert-warning py-2 mb-12">
                                                    Предпросмотр карточки для одобренного B2B-партнёра
                                                </div>
                                            @endif
                                            <p class="text-caption-01 cl-text-2 mb-4">
                                                Партнёрская цена от {{ $b2bOffer->source?->partnerName() }}
                                            </p>
                                            <h4 class="price-on-sale">
                                                {{ number_format($b2bComparison['wholesale_price'], 2, '.', ' ') }} BYN
                                            </h4>
                                            @if ($b2bComparison['retail_price'] > 0)
                                                <p class="text-caption-01 cl-text-2 mb-0">
                                                    Розничная цена: {{ number_format($b2bComparison['retail_price'], 2, '.', ' ') }} BYN
                                                </p>
                                            @endif
                                            @if ($b2bComparison['difference'] > 0)
                                                <p class="text-caption-01 text-success mb-0">
                                                    Ваша скидка к рознице:
                                                    {{ number_format($b2bComparison['difference'], 2, '.', ' ') }} BYN
                                                    @if ($b2bComparison['difference_percent'] !== null)
                                                        ({{ number_format($b2bComparison['difference_percent'], 1, '.', ' ') }}%)
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @elseif (((float) $product->price > 0 && ! $product->requires_price_confirmation && $hasAssignedSupplier) || $showPreviewPrice)
                                        <h4 class="price-on-sale">
                                            {{ number_format($product->price, 2, '.', ' ') }} BYN
                                        </h4>
                                        @if ($product->isPublicSale() && $product->price_old > $product->price)
                                            <div class="br-line type-vertical"></div>
                                            <p class="cl-text-3 text-decoration-line-through">
                                                {{ number_format($product->price_old, 2, '.', ' ') }} BYN
                                            </p>
                                            @php
                                                $discount = round((1 - $product->price / $product->price_old) * 100);
                                            @endphp
                                            <span class="badge-sale text-white fw-semibold text-caption-02">
                                                -{{ $discount }}%
                                            </span>
                                        @endif
                                    @else
                                        <h4 class="price-on-sale text-primary">Цену уточняйте</h4>
                                    @endif
                                </div>

                                @if ($onlinePrice !== null)
                                    <div class="online-order-price mb-16">
                                        <strong>Онлайн-цена: {{ number_format($onlinePrice, 2, '.', ' ') }} BYN</strong>
                                        <span>Экономия {{ number_format($onlineSaving, 2, '.', ' ') }} BYN. Скидка применится в корзине автоматически.</span>
                                    </div>
                                @endif

                                @include('partials.manager-product-context', ['product' => $product])

                                @if ($b2bOffer && ($showMinskB2bWarehouse || $b2bWarehouseStocks->isNotEmpty()))
                                    <div class="mb-16">
                                        <label for="b2b-warehouse-{{ $product->id }}" class="fw-semibold mb-6 d-block">
                                            Склад отгрузки
                                        </label>
                                        <select id="b2b-warehouse-{{ $product->id }}" class="form-select b2b-warehouse-select">
                                            @if ($showMinskB2bWarehouse)
                                                <option value="">
                                                    Склад Минск — {{ $b2bOffer->formattedStockQuantity() }}
                                                </option>
                                            @endif
                                            @foreach ($b2bWarehouseStocks as $warehouseStock)
                                                <option value="{{ $warehouseStock->id }}">
                                                    {{ $warehouseStock->warehouse_name }} — {{ $warehouseStock->formattedAvailableQuantity() }}
                                                    · доставка до {{ $warehouseStock->delivery_days }} дней
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($b2bWarehouseStocks->isNotEmpty())
                                            <p class="text-caption-01 cl-text-2 mt-4 mb-0">
                                                Московский остаток показан за {{ optional($b2bWarehouseStocks->first()->as_of_date)->format('d.m.Y') }}.
                                                Срок доставки в Минск — до 7 дней.
                                            </p>
                                        @endif
                                    </div>
                                @endif

                                @if (!empty($product->promo_flags))
                                    <div class="d-flex flex-wrap gap-2 mb-12">
                                        @foreach ($product->promo_flags as $flag)
                                            @php
                                                $flagLabel = is_array($flag) ? ($flag['label'] ?? null) : $flag;
                                            @endphp
                                            @if ($flagLabel)
                                                <span class="badge-sale text-white fw-semibold text-caption-02">
                                                    {{ $flagLabel }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                @if ($product->slug === 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt' && $product->is_sale)
                                    <x-xo-promo-note />
                                @endif

                                @if ($product->slug === 'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140' && $product->is_sale)
                                    <x-xo-evo-promo-note />
                                @endif

                                @if (in_array($product->slug, ['pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1', 'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3'], true) && $product->is_sale)
                                    <x-hotta-promo-note />
                                @endif

                                {{-- Краткое описание --}}
                                @if ($product->short_description)
                                    <p class="product-infor-desc cl-text-2 mb-12">
                                        {!! $product->short_description !!}
                                    </p>
                                @endif

                                @if ($gasMeterGuide)
                                    <aside class="gas-meter-fit mb-16" aria-label="Подсказка по выбору газового счётчика">
                                        <p class="gas-meter-fit__eyebrow">Подойдёт ли вам</p>
                                        <p class="gas-meter-fit__title">{{ $gasMeterGuide['size'] }} — {{ $gasMeterGuide['purpose'] }}</p>
                                        <ul>
                                            @foreach ($gasMeterGuide['facts'] as $fact)
                                                <li>{{ $fact }}</li>
                                            @endforeach
                                        </ul>
                                        <a href="{{ $gasMeterGuide['article_url'] }}" class="link fw-semibold">
                                            Подобрать счётчик в простой программе →
                                        </a>
                                    </aside>
                                @endif

                                @if ($variantSelector)
                                    <div class="product-model-selector mb-16" aria-label="{{ $variantSelector['title'] }}">
                                        <p class="product-model-selector__title">{{ $variantSelector['title'] }}</p>
                                        <div class="product-model-selector__options">
                                            @foreach ($variantSelector['variants'] as $variant)
                                                <a
                                                    href="{{ $variant['url'] }}"
                                                    class="product-model-selector__option {{ $variant['is_current'] ? 'is-current' : '' }}"
                                                    @if ($variant['is_current']) aria-current="page" @endif
                                                    title="{{ $variant['label'] }} — {{ $variant['availability_label'] }}"
                                                >
                                                    <span class="product-model-selector__status is-{{ $variant['availability_status'] }}" aria-hidden="true"></span>
                                                    {{ $variant['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                        <p class="product-model-selector__hint">Зелёная точка — в наличии, жёлтая — доступно к заказу.</p>
                                    </div>
                                @endif

                                {{-- Наличие --}}
                                <div class="product-stock mb-12">
                                    @if ($product->is_archived)
                                        <span class="stock out-stock fw-medium text-danger">
                                            <i class="icon icon-XCircle"></i> Снят с продажи
                                        </span>
                                    @elseif (!$product->is_active)
                                        <span class="stock out-stock fw-medium text-danger">
                                            <i class="icon icon-XCircle"></i> Нет в наличии
                                        </span>
                                    @elseif ($b2bOffer)
                                        <span class="stock in-stock fw-medium text-success">
                                            <i class="icon icon-CheckCircle"></i>
                                            @if ($isProcurementOffer)
                                                {{ data_get($b2bOffer->source?->settings, 'warehouse_label', 'Под заказ') }}
                                            @elseif (!$showMinskB2bWarehouse && $b2bWarehouseStocks->isNotEmpty())
                                                {{ $b2bWarehouseStocks->first()->warehouse_name }}:
                                                {{ $b2bWarehouseStocks->first()->formattedAvailableQuantity() }}
                                            @else
                                                {{ $product->brand?->slug === 'ecokamin'
                                                    ? 'Склад Минск'
                                                    : data_get($b2bOffer->source?->settings, 'warehouse_label', 'Основной') }}:
                                                {{ $b2bOffer->formattedStockQuantity() }}
                                            @endif
                                        </span>
                                    @elseif ($displayAvailabilityStatus === 'in_stock')
                                        <span class="stock in-stock fw-medium text-success">
                                            <i class="icon icon-CheckCircle"></i> {{ $availabilityLabel }}
                                        </span>
                                    @elseif ($displayAvailabilityStatus === 'check')
                                        <span class="stock check-stock fw-medium" style="color:#b45309;">
                                            <i class="icon icon-CheckCircle" style="color:#b45309;"></i> {{ $availabilityLabel }}
                                        </span>
                                        <p class="text-caption-01 cl-text-2 mt-4 mb-0">
                                            {{ $hasAssignedSupplier
                                                ? 'Менеджер подтвердит наличие и срок поставки после оформления.'
                                                : 'Поставщик пока не назначен. Оставьте запрос — менеджер уточнит наличие и возможность поставки.' }}
                                        </p>
                                    @else
                                        <span class="stock out-stock fw-medium text-danger">
                                            <i class="icon icon-XCircle"></i> Нет в наличии
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="br-line"></div>

                            @if ($product->is_archived)
                                {{-- Снят с продажи — только консультация --}}
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100">
                                    Заказать консультацию
                                </a>
                            @elseif (!$product->is_active)
                                {{-- Отключённая карточка доступна только в предпросмотре менеджера. --}}
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100">
                                    Заказать консультацию
                                </a>
                            @elseif (! $hasAssignedSupplier)
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100 btn-product-availability-request"
                                    data-product-id="{{ $product->id }}"
                                    data-product-name="{{ $product->name }}"
                                    data-product-url="{{ url()->current() }}">
                                    Уточнить наличие
                                </a>
                            @elseif ($canBuy)
                                {{-- Количество и кнопки — есть в наличии --}}
                                <div class="tf-product-variant">
                                    <div class="tf-product-total-quantity">
                                        <p>Количество:</p>
                                        <div class="group-action">
                                            <div class="wg-quantity">
                                                <button class="btn-quantity btn-decrease">
                                                    <i class="icon icon-minus"></i>
                                                </button>
                                                <input class="quantity-product product-main-qty" type="text" name="number" value="1">
                                                <button class="btn-quantity btn-increase">
                                                    <i class="icon icon-plus"></i>
                                                </button>
                                            </div>
                                            <a href="#shoppingCart"
                                                class="tf-btn type-xl animate-btn w-100 btn-add-to-cart"
                                                data-product-id="{{ $product->id }}"
                                                data-qty-input=".product-main-qty"
                                                @if ($b2bOffer) data-warehouse-select="#b2b-warehouse-{{ $product->id }}" @endif>
                                                {{ $availabilityStatus === 'check' && ! $b2bOffer ? 'Заказать' : 'В корзину' }}
                                                @if ($displayPrice > 0)
                                                    <span class="d-none d-sm-block d-md-none d-lg-block">&nbsp;—&nbsp;</span>
                                                    <span class="d-none d-sm-block d-md-none d-lg-block">
                                                        {{ number_format($displayPrice, 2, '.', ' ') }} BYN
                                                    </span>
                                                @endif
                                            </a>
                                        </div>
                                        @if (! $b2bOffer && in_array('installment_6', config('shop.checkout_payment_methods', []), true))
                                            @php
                                                $installmentInfo = config('shop.payment_methods.installment_6', []);
                                            @endphp
                                            <a href="{{ route('checkout', ['payment_type' => 'installment_6']) }}"
                                                class="tf-btn type-xl btn-outline animate-btn w-100 mt-12 btn-add-to-cart btn-installment-purchase"
                                                data-product-id="{{ $product->id }}"
                                                data-qty-input=".product-main-qty"
                                                data-after-add-url="{{ route('checkout', ['payment_type' => 'installment_6']) }}">
                                                Купить в рассрочку до 6 месяцев
                                            </a>
                                            <p class="text-caption-01 cl-text-2 text-center mt-8 mb-0">
                                                Менеджер подтвердит доступность и продолжит оформление
                                            </p>
                                            <p class="text-caption-01 text-center mt-4 mb-0">
                                                <a href="{{ $installmentInfo['video_url'] }}" target="_blank" rel="noopener noreferrer">
                                                    Смотреть видеоинструкцию
                                                </a>
                                                <span class="cl-text-2"> · </span>
                                                <a href="{{ route('installment.info') }}">
                                                    Как оформить рассрочку
                                                </a>
                                            </p>
                                        @endif
                                        <a href="#ask" data-bs-toggle="modal"
                                            class="tf-btn type-xl btn-primary animate-btn w-100">
                                            Заказать консультацию
                                        </a>
                                    </div>
                                </div>
                            @elseif ($availabilityStatus === 'check')
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100">
                                    Заказать консультацию
                                </a>
                            @else
                                {{-- Форма уведомления — нет в наличии --}}
                                <form class="form-notice-stock" action="/subscribe-stock" method="POST">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <h5 class="title text-capitalize mb-8">
                                        Уведомить о поступлении
                                    </h5>
                                    <p class="desc cl-text-2 mb-16">
                                        Введите ваш email и мы сообщим, когда товар появится в наличии.
                                    </p>
                                    <div class="form-content">
                                        <input type="text" name="name" placeholder="Ваше имя*" required>
                                        <input type="email" name="email" placeholder="Email*" required>
                                        <button type="submit" class="tf-btn animate-btn">
                                            Уведомить меня
                                        </button>
                                    </div>
                                </form>
                                {{-- Кнопка консультации всегда доступна --}}
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100 mt-16">
                                    Заказать консультацию
                                </a>
                            @endif

                            {{-- Доп. ссылки --}}
                            <div class="tf-product-extra-link">
                                <a href="#" data-product-id="{{ $product->id }}"
                                    class="product-extra-icon link wishlist-toggle{{ $isWishlisted ? ' is-active' : '' }}"
                                    aria-label="{{ $isWishlisted ? 'Удалить' : 'Добавить' }} {{ $product->name }} {{ $isWishlisted ? 'из избранного' : 'в избранное' }}"
                                    aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}"
                                    title="{{ $isWishlisted ? 'В избранном' : 'В избранное' }}">
                                    <i class="icon {{ $isWishlisted ? 'icon-Heart' : 'icon-heart' }}" aria-hidden="true"></i>
                                    <span class="wishlist-label">{{ $isWishlisted ? 'В избранном' : 'В избранное' }}</span>
                                </a>
                                <a href="#compare" data-product-id="{{ $product->id }}" class="product-extra-icon link">
                                    <i class="icon icon-ArrowsLeftRight"></i>
                                    Сравнить
                                </a>
                                <a href="#ask" data-bs-toggle="modal" class="product-extra-icon link">
                                    <i class="icon icon-Question"></i>
                                    Задать вопрос
                                </a>
                                <a href="#share" data-bs-toggle="modal" class="product-extra-icon link">
                                    <i class="icon icon-ShareNetwork"></i>
                                    Поделиться
                                </a>
                            </div>

                            <div class="br-line"></div>

                            {{-- Доставка --}}
                            <div class="tf-product-delivery-return">
                                <div class="product-delivery">
                                    <i class="icon icon-Timer"></i>
                                    <p>
                                        Доставка по всей Беларуси.
                                        <span class="fw-semibold">Минск — 1-2 дня.</span>
                                        Регионы — <span class="fw-semibold">2-5 дней.</span>
                                    </p>
                                </div>
                                <div class="product-delivery return">
                                    <i class="icon icon-ArrowClockwise"></i>
                                    <p>
                                        Возврат в течение
                                        <span class="fw-semibold">14 дней</span>
                                        с момента покупки.
                                    </p>
                                </div>
                                @if ($product->warranty)
                                    <div class="product-delivery">
                                        <i class="icon icon-ShieldCheck"></i>
                                        <p>Гарантия: <span class="fw-semibold">{{ $product->warranty }}</span></p>
                                    </div>
                                @endif
                            </div>

                            {{-- Инженерный сервис --}}
                            <div class="tf-product-engineering">
                                <div class="tf-product-engineering__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M4 4h16v16H4zM8 8h8M8 12h3M8 16h3M15 12v4M13 14h4"/>
                                    </svg>
                                </div>
                                <div class="tf-product-engineering__content">
                                    <p class="tf-product-engineering__label">Инженерный сервис KOTLOV</p>
                                    <p class="h6 tf-product-engineering__title">Инженерный подбор под ключ</p>
                                    <p class="tf-product-engineering__text">
                                        Рассчитаем мощность, проверим совместимость, подберём комплектующие и организуем монтаж.
                                    </p>
                                    <a href="#engineeringCalculation" data-bs-toggle="modal"
                                        class="tf-product-engineering__link"
                                        data-analytics-event="product_engineering_calculation_click">
                                        Оставить заявку на расчёт
                                        <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    @if ($heatPumpProfile ?? null)
        @include('partials.product-kotlov-heat-pump-profile', ['profile' => $heatPumpProfile])
        @include('partials.product-kotlov-heat-pump-comparison', [
            'models' => $kotlovHeatPumpModels,
            'currentProduct' => $product,
        ])
    @endif

    {{-- Sticky кнопка "В корзину" при скролле --}}
    @if ($canBuy)
    @php
        $mobileInstallmentAvailable = ! $b2bOffer
            && in_array('installment_6', config('shop.checkout_payment_methods', []), true);
    @endphp
    <div class="tf-sticky-btn-atc">
        <div class="container">
            <div class="tf-height-observer w-100 d-flex align-items-center">
                <div class="tf-sticky-atc-product d-flex align-items-center">
                    <div class="atc-product-side">
                        <div class="prd_img">
                            <img loading="lazy" width="60" height="80"
                                src="{{ $product->image_url }}"
                                alt="{{ $product->name }}"
                                onerror="this.src='{{ $placeholder }}'">
                        </div>
                        <div class="prd_info d-none d-lg-grid">
                            <p class="name__prd fw-medium lh-24">{{ Str::limit($product->name, 40) }}</p>
                            @if ($product->brand)
                                <p class="distribute__prd text-caption-01 cl-text-3">{{ $product->brand->name }}</p>
                            @endif
                            @if ($displayPrice > 0)
                                <p class="price__prd fw-semibold">
                                    {{ number_format($displayPrice, 2, '.', ' ') }} BYN
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="tf-sticky-atc-infos">
                    @if ($displayPrice > 0)
                        <p class="mobile-sticky-price d-md-none">
                            {{ number_format($displayPrice, 2, '.', ' ') }} <span>BYN</span>
                        </p>
                    @endif
                    <div class="tf-product-info-quantity">
                        <p class="title">Количество:</p>
                        <div class="wg-quantity style-2">
                            <button class="btn-quantity minus-btn">
                                <i class="icon icon-minus"></i>
                            </button>
                            <input class="quantity-product" type="text" name="number" value="1">
                            <button class="btn-quantity plus-btn">
                                <i class="icon icon-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mobile-sticky-actions{{ $mobileInstallmentAvailable ? ' has-installment' : '' }}">
                        <a href="#shoppingCart"
                            class="tf-btn animate-btn btn-add-to-cart mobile-sticky-cart"
                            data-product-id="{{ $product->id }}"
                            @if ($b2bOffer) data-warehouse-select="#b2b-warehouse-{{ $product->id }}" @endif>
                            <span class="d-md-none">В корзину</span>
                            <span class="d-none d-md-inline">
                                В корзину
                                @if ($displayPrice > 0)
                                    — {{ number_format($displayPrice, 2, '.', ' ') }} BYN
                                @endif
                            </span>
                        </a>
                        @if ($mobileInstallmentAvailable)
                            <a href="{{ route('checkout', ['payment_type' => 'installment_6']) }}"
                                class="mobile-sticky-installment d-md-none btn-add-to-cart"
                                data-product-id="{{ $product->id }}"
                                data-after-add-url="{{ route('checkout', ['payment_type' => 'installment_6']) }}"
                                aria-label="Купить в рассрочку до 6 месяцев">
                                <span>В рассрочку</span>
                                <small>до 6 мес.</small>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Описание, характеристики, сервис, документы, отзывы --}}
    <section class="section-product-description flat-spacing flat-animate-tab">
        <div class="container">
            @php
                $serviceInfo = is_array($product->service_info) ? array_filter($product->service_info) : [];
                $documents = is_array($product->documents) ? array_filter($product->documents) : [];
                $xoControllerManualUrl = '/docs/kotlov-xo-controller-1-2os-ru.pdf';
                $isXoCeramicPro100 = $product->slug === 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt';
                $renderedProductContent = (string) $product->content;
                if ($isXoCeramicPro100 && !str_contains($renderedProductContent, $xoControllerManualUrl)) {
                    $manualLabel = 'Инструкция контроллера XO 1.0S/2.0S (PDF)';
                    $manualLink = '<a href="' . $xoControllerManualUrl . '" class="link fw-medium" target="_blank" rel="noopener"><strong>' . $manualLabel . '</strong></a>';
                    $renderedProductContent = preg_replace(
                        '~<strong>\s*' . preg_quote($manualLabel, '~') . '\s*</strong>~u',
                        $manualLink,
                        $renderedProductContent,
                        1
                    ) ?? $renderedProductContent;
                }
                $contentHasXoControllerManual = str_contains($renderedProductContent, $xoControllerManualUrl);
                if ($isXoCeramicPro100 && !collect($documents)->contains(
                    fn ($document) => is_array($document)
                        ? ($document['url'] ?? null) === $xoControllerManualUrl
                        : $document === $xoControllerManualUrl
                )) {
                    $documents[] = [
                        'label' => 'Инструкция контроллера XO 1.0S/2.0S (PDF)',
                        'url' => $xoControllerManualUrl,
                    ];
                }
                $videoUrl = trim((string) ($product->video_url ?? ''));
                $videoEmbedUrl = null;
                $isDirectVideo = $videoUrl && preg_match('~\.(mp4|webm|mov)(?:\?|$)~i', $videoUrl);
                if ($videoUrl && preg_match('~youtube\.com/embed/([A-Za-z0-9_-]{6,})~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = $videoUrl;
                } elseif ($videoUrl && preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = 'https://www.youtube.com/embed/' . $videoMatch[1];
                } elseif ($videoUrl && preg_match('~player\.vimeo\.com/video/(\d+)~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = $videoUrl;
                } elseif ($videoUrl && preg_match('~vimeo\.com/(\d+)~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = 'https://player.vimeo.com/video/' . $videoMatch[1];
                } elseif ($videoUrl && preg_match('~rutube\.ru/play/embed/([A-Za-z0-9]+)~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = $videoUrl;
                } elseif ($videoUrl && preg_match('~rutube\.ru/video/([A-Za-z0-9]+)/?~i', $videoUrl, $videoMatch)) {
                    $videoEmbedUrl = 'https://rutube.ru/play/embed/' . $videoMatch[1];
                }
                $attributeSpecNames = $attributeValues
                    ->map(fn ($value) => mb_strtolower(trim((string) ($value->attribute?->name ?? ''))))
                    ->filter()
                    ->values();
                $rawSpecs = !empty($product->specs)
                    ? collect(is_array($product->specs) ? $product->specs : (json_decode($product->specs, true) ?? []))
                          ->map(function ($spec, $key) {
                              if (is_array($spec)) {
                                  $specKey = $spec['key'] ?? $spec['name'] ?? $spec['title'] ?? null;
                                  $specValue = $spec['value'] ?? $spec['val'] ?? null;

                                  return [
                                      'key' => is_scalar($specKey) ? trim((string) $specKey) : '',
                                      'value' => is_scalar($specValue) ? trim((string) $specValue) : '',
                                      'unit' => is_scalar($spec['unit'] ?? null) ? trim((string) $spec['unit']) : '',
                                  ];
                              }

                              return [
                                  'key' => is_string($key) ? trim($key) : '',
                                  'value' => is_scalar($spec) ? trim((string) $spec) : '',
                                  'unit' => '',
                              ];
                          })
                          ->filter(function ($spec) use ($attributeSpecNames) {
                              if ($spec['key'] === '' || $spec['value'] === '') {
                                  return false;
                              }

                              $normalizedKey = mb_strtolower(trim($spec['key']));

                              if ($attributeSpecNames->contains($normalizedKey)) {
                                  return false;
                              }

                              if ($normalizedKey === 'питание' && $attributeSpecNames->contains('электропитание')) {
                                  return false;
                              }

                              if ($normalizedKey === 'диапазон мощности для фильтра') {
                                  return false;
                              }

                              // Диапазон «Мощность» используется только как фильтр, а точное
                              // значение уже хранится в «Мощность теплового насоса».
                              if ($normalizedKey === 'мощность' && $attributeSpecNames->contains('мощность теплового насоса')) {
                                  return false;
                              }

                              return true;
                          })
                          ->unique(fn($s) => mb_strtolower(trim($s['key'])))
                          ->values()
                    : collect();
                $hasSpecs = $attributeValues->count() > 0 || $rawSpecs->count() > 0;
            @endphp
            <ul class="tab-btn-wrap-v1" role="tablist">
                <li class="nav-tab-item" role="presentation">
                    <a href="#description" data-bs-toggle="tab" class="tf-btn-tab active" role="tab">
                        <span class="h5 fw-medium">Описание</span>
                    </a>
                </li>
                @if ($hasSpecs)
                    <li class="nav-tab-item" role="presentation">
                        <a href="#specifications" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Технические характеристики</span>
                        </a>
                    </li>
                @endif
                @if (!empty($serviceInfo))
                    <li class="nav-tab-item" role="presentation">
                        <a href="#service-info" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Сервис</span>
                        </a>
                    </li>
                @endif
                @if (!empty($documents))
                    <li class="nav-tab-item" role="presentation">
                        <a href="#documents" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Документы</span>
                        </a>
                    </li>
                @endif
                @if ($videoUrl)
                    <li class="nav-tab-item" role="presentation">
                        <a href="#video" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Видео</span>
                        </a>
                    </li>
                @endif
                <li class="nav-tab-item" role="presentation">
                    <a href="#customer-reviews" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                        <span class="h5 fw-medium">Отзывы ({{ $product->reviews_count }})</span>
                    </a>
                </li>
                <li class="nav-tab-item" role="presentation">
                    <a href="#delivery-info" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                        <span class="h5 fw-medium">Доставка и оплата</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                {{-- Описание --}}
                <div class="tab-pane active show" id="description" role="tabpanel">
                    <div class="tab-content_desc site-prose">
                        @if ($product->content)
                            <div class="box-desc">
                                <div class="desc_info cl-text-2">
                                    @if ($isXoCeramicPro100 && !$contentHasXoControllerManual)
                                        <p class="mb-4">
                                            <strong>Контроллер XO 1.0S/2.0S:</strong>
                                            <a href="{{ $xoControllerManualUrl }}" class="link fw-medium" target="_blank" rel="noopener">
                                                открыть инструкцию (PDF)
                                            </a>
                                        </p>
                                    @endif
                                    {!! $renderedProductContent !!}
                                </div>
                            </div>
                        @else
                            <p class="cl-text-2">Описание товара отсутствует.</p>
                        @endif
                    </div>
                </div>

                {{-- Характеристики --}}
                @if ($hasSpecs)
                    <div class="tab-pane" id="specifications" role="tabpanel">
                        <div class="tab-content_desc site-prose">
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    @if ($attributeValues->count() > 0)
                                        @foreach ($attributeValues as $val)
                                            @php
                                                $isEmpty = ! $val->hasDisplayValue();
                                                $displayValue = (string) $val->value;
                                                $displaySuffix = (string) ($val->attribute->suffix ?? '');
                                                if (
                                                    mb_strtolower((string) $val->attribute->name) === 'мощность двигателя'
                                                    && preg_match('/^\d+(?:[,.]\d+)?$/u', $displayValue) === 1
                                                    && (float) str_replace(',', '.', $displayValue) < 1000
                                                    && mb_strtolower(trim($displaySuffix)) === 'квт'
                                                ) {
                                                    $displaySuffix = 'Вт';
                                                }
                                                if ($displaySuffix !== '' && mb_stripos($displayValue, $displaySuffix) !== false) {
                                                    $displaySuffix = '';
                                                }
                                            @endphp
                                            @if (!$isEmpty)
                                                <tr>
                                                    <td class="fw-medium" style="width:45%">
                                                        {{ $val->attribute->name }}
                                                        @if ($displaySuffix)
                                                            <span class="cl-text-2 fw-normal">, {{ $displaySuffix }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @switch($val->attribute->type)
                                                            @case('select')
                                                                {{ $val->option->name }}
                                                                @break
                                                            @case('check')
                                                                @if ($val->is_checked)
                                                                    <i class="icon icon-CheckCircle text-success"></i> Да
                                                                @else
                                                                    <i class="icon icon-XCircle text-danger"></i> Нет
                                                                @endif
                                                                @break
                                                            @default
                                                                {{ $displayValue }} {{ $displaySuffix }}
                                                        @endswitch
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif
                                    @if ($rawSpecs->count() > 0)
                                        @foreach ($rawSpecs as $spec)
                                            <tr>
                                                <td class="fw-medium" style="width:45%">{{ $spec['key'] }}</td>
                                                <td>{{ $spec['value'] }}{{ !empty($spec['unit']) ? ' ' . $spec['unit'] : '' }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Сервис --}}
                @if (!empty($serviceInfo))
                    <div class="tab-pane" id="service-info" role="tabpanel">
                        <div class="tab-content_desc site-prose">
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    @foreach ($serviceInfo as $label => $value)
                                        @if (is_scalar($value) && trim((string) $value) !== '')
                                            <tr>
                                                <td class="fw-medium" style="width:45%">{{ $label }}</td>
                                                <td>{{ $value }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Документы --}}
                @if (!empty($documents))
                    <div class="tab-pane" id="documents" role="tabpanel">
                        <div class="tab-content_desc site-prose">
                            <div class="d-flex flex-column gap-3">
                                @foreach ($documents as $document)
                                    @php
                                        $label = is_array($document) ? ($document['label'] ?? 'Документ') : 'Документ';
                                        $url = is_array($document) ? ($document['url'] ?? null) : $document;
                                    @endphp
                                    @if ($url)
                                        <a href="{{ $url }}" class="link fw-medium" target="_blank" rel="nofollow noopener">
                                            {{ $label }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Отзывы --}}
                @if ($videoUrl)
                    <div class="tab-pane" id="video" role="tabpanel">
                        <div class="tab-content_desc site-prose">
                            <div class="product-video-wrap">
                                @if ($videoEmbedUrl)
                                    <iframe
                                        src="{{ $videoEmbedUrl }}"
                                        title="{{ $product->name }}"
                                        loading="lazy"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen></iframe>
                                @elseif ($isDirectVideo)
                                    <video src="{{ $videoUrl }}" controls preload="metadata"></video>
                                @else
                                    <a href="{{ $videoUrl }}" class="product-video-link" target="_blank" rel="nofollow noopener">
                                        <span class="product-video-link-icon" aria-hidden="true"></span>
                                        <span class="product-video-link-copy">
                                            <strong>Смотреть видео</strong>
                                            <small>Откроется в новой вкладке</small>
                                        </span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="tab-pane" id="customer-reviews" role="tabpanel">
                    <div class="product-desc_review">

                        @if (session('review_sent'))
                            <div class="alert alert-success mb-24" role="alert">
                                ✅ Спасибо! Ваш отзыв отправлен на модерацию и будет опубликован в ближайшее время.
                            </div>
                        @endif

                        {{-- Рейтинг + progress bars --}}
                        <div class="box-rating">
                            <div class="rating-ratio">
                                <p class="text-display fw-medium">{{ $reviews->count() > 0 ? number_format($product->rating, 1) : '—' }}</p>
                                <div class="star-wrap normal d-flex align-items-center">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }} fs-24"></i>
                                    @endfor
                                </div>
                                <p class="rate-number">({{ $product->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $product->reviews_count) }})</p>
                            </div>
                            @if ($reviews->count() > 0)
                                @php
                                    $ratingCounts = $reviews->groupBy('rating')->map->count();
                                    $total = $reviews->count();
                                @endphp
                                <div class="rating-progress-list">
                                    @foreach ([5, 4, 3, 2, 1] as $star)
                                        @php $cnt = $ratingCounts[$star] ?? 0; $pct = $total > 0 ? round($cnt / $total * 100) : 0; @endphp
                                        <div class="rate-progress-star fw-medium">
                                            <span class="number-star">{{ $star }}</span>
                                            <i class="icon icon-Star fs-20 cl-text-yellow"></i>
                                            <div class="progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="number-percent">{{ $pct }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <a href="#write-review" class="action tf-btn animate-btn">Написать отзыв</a>
                        </div>

                        {{-- Список отзывов --}}
                        @if ($reviews->count() > 0)
                            <div class="box-comment">
                                <div class="head">
                                    <h4>{{ $product->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $product->reviews_count) }}</h4>
                                </div>
                                <div class="wg-comment">
                                    <div class="comment-list">
                                        @foreach ($reviews as $review)
                                            <div class="box-comment">
                                                <div class="comment_info">
                                                    <div class="info_image">
                                                        <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                                            style="width:60px;height:60px;font-size:20px;flex-shrink:0">
                                                            {{ mb_strtoupper(mb_substr($review->author_name, 0, 1)) }}
                                                        </div>
                                                    </div>
                                                    <div class="info_author">
                                                        <p class="h6 author__name">{{ $review->author_name }}</p>
                                                        <div class="star-wrap normal d-flex align-items-center">
                                                            @for ($s = 1; $s <= 5; $s++)
                                                                <i class="icon icon-Star{{ $s <= $review->rating ? '' : 'EmptyOutlined' }}"></i>
                                                            @endfor
                                                        </div>
                                                        <p class="author_date text-caption-01 cl-text-3">{{ $review->created_at->diffForHumans() }}</p>
                                                    </div>
                                                </div>
                                                <p class="comment_text text-body-1">{{ $review->text }}</p>

                                                @if ($review->reply)
                                                    <div class="comment_reply">
                                                        <div class="comment_info">
                                                            <div class="info_image">
                                                                <div class="d-flex align-items-center justify-content-center rounded-circle fw-semibold"
                                                                    style="width:60px;height:60px;font-size:18px;flex-shrink:0;background:#f97316;color:#fff;">
                                                                    К
                                                                </div>
                                                            </div>
                                                            <div class="info_author">
                                                                <p class="h6 author__name">Ответ от KOTLOV</p>
                                                                <p class="author_date text-caption-01 cl-text-3">
                                                                    {{ $review->replied_at?->diffForHumans() }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <p class="comment_text text-body-1">{{ $review->reply }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="cl-text-2 mb-32">Отзывов пока нет. Будьте первым!</p>
                        @endif

                        {{-- Форма написать отзыв --}}
                        <div class="box-write-comment" id="write-review">
                            <div class="head">
                                <h5>Написать отзыв:</h5>
                                <div class="star-wrap rate-click d-flex align-items-center" id="star-rating">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <i class="icon icon-Star" data-value="{{ $s }}" style="cursor:pointer;font-size:24px;"></i>
                                    @endfor
                                </div>
                            </div>
                            @if ($errors->any())
                                <div class="alert alert-danger mb-16">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form class="form-rating" method="POST" action="{{ route('reviews.store', $product) }}">
                                @csrf
                                <x-form-protection />
                                <input type="hidden" name="rating" id="rating-value" value="{{ old('rating', 5) }}">
                                <div class="form-content mb-24">
                                    <div class="tf-grid-layout md-col-2">
                                        <div class="tf-grid-layout">
                                            <fieldset class="tf-field">
                                                <label for="review-name" class="tf-lable fw-medium">Ваше имя <span class="text-primary">*</span></label>
                                                <input type="text" id="review-name" name="author_name"
                                                    placeholder="Имя (будет опубликовано)"
                                                    value="{{ old('author_name') }}" required>
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label for="review-phone" class="tf-lable fw-medium">Телефон</label>
                                                <input type="tel" id="review-phone" name="author_phone"
                                                    placeholder="+375 (XX) XXX-XX-XX (не публикуется)"
                                                    value="{{ old('author_phone') }}">
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label for="review-email" class="tf-lable fw-medium">Email</label>
                                                <input type="email" id="review-email" name="author_email"
                                                    placeholder="Email (не публикуется)"
                                                    value="{{ old('author_email') }}">
                                            </fieldset>
                                        </div>
                                        <fieldset class="tf-field d-flex flex-column">
                                            <label for="review-text" class="tf-lable fw-medium">Отзыв <span class="text-primary">*</span></label>
                                            <textarea id="review-text" name="text"
                                                placeholder="Поделитесь вашим мнением о товаре..."
                                                class="h-md-100" required>{{ old('text') }}</textarea>
                                        </fieldset>
                                    </div>
                                </div>
                                <button type="submit" class="tf-btn animate-btn">Отправить отзыв</button>
                            </form>
                        </div>

                    </div>
                </div>

                {{-- Доставка и оплата --}}
                <div class="tab-pane" id="delivery-info" role="tabpanel">
                    <div class="tab-content_desc tf-grid-layout md-col-2">
                        <div class="box-desc">
                            <h5 class="desc_title">Доставка</h5>
                            <div class="desc_info">
                                <ul class="list">
                                    <li class="cl-text-2">— По Минску: 1-2 рабочих дня</li>
                                    <li class="cl-text-2">— По регионам: 2-5 рабочих дней</li>
                                    <li class="cl-text-2">— Крупногабаритное оборудование — по договорённости</li>
                                    <li class="cl-text-2">— Самовывоз: ул. Селицкого 39Б, каб.23, Минск</li>
                                </ul>
                            </div>
                        </div>
                        <div class="box-desc">
                            <h5 class="desc_title">Оплата</h5>
                            <div class="desc_info">
                                <ul class="list">
                                    <li class="cl-text-2">— Наличными при получении</li>
                                    <li class="cl-text-2">— Банковской картой</li>
                                    <li class="cl-text-2">— Безналичный расчёт (для юрлиц)</li>
                                    <li class="cl-text-2">— Рассрочка 0%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <x-order-confirmation-notice
                        class="mt-24"
                        :availability-confirmed="$product->slug === 'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140' && (int) $product->stock_qty > 0"
                    />
                </div>

            </div>
        </div>
    </section>

    @if (($heatPumpGuides ?? collect())->isNotEmpty())
        @include('partials.product-heat-pump-resources', ['articles' => $heatPumpGuides])
    @endif

    @if ($product->slug === 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt')
        @include('partials.product-xo-resources', ['product' => $product])
    @endif

    @if ($product->slug === 'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140')
        @include('partials.product-xo-evo-resources', ['product' => $product])
    @endif

    @if (in_array($product->slug, \App\Models\Product::PUBLIC_SALE_SLUGS, true))
        @include('partials.xo-controller-feature')
    @endif

    {{-- Похожие товары --}}
    @if ($relatedProducts->count() > 0)
        <section class="flat-spacing">
            <div class="container">
                <div class="sect-heading type-2 text-center wow fadeInUp">
                    <h3 class="s-title">Похожие товары</h3>
                </div>
                <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3"
                    data-mobile-sm="2" data-mobile="1"
                    data-space-lg="30" data-space-md="20" data-space="10">
                    <div class="swiper-wrapper">
                        @foreach ($relatedProducts as $related)
                            <div class="swiper-slide">
                                @include('partials.product-card', ['product' => $related])
                            </div>
                        @endforeach
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </section>
    @endif

</main>

@push('styles')
@if (str_contains((string) $product->content, 'product-description-faq'))
<link rel="stylesheet" href="{{ asset('css/product-description-faq.css') }}">
@endif
<style>
.product-model-selector__title {
    margin: 0 0 8px;
    color: var(--main);
    font-size: 15px;
    font-weight: 600;
}

.product-model-selector__options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.product-model-selector__option {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 7px 12px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--white);
    color: var(--main);
    font-weight: 600;
    line-height: 1;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.product-model-selector__option:hover,
.product-model-selector__option.is-current {
    border-color: var(--primary);
    box-shadow: 0 0 0 1px var(--primary);
    color: var(--primary);
}

.product-model-selector__status {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #dc3545;
}

.product-model-selector__status.is-in_stock { background: #22a447; }
.product-model-selector__status.is-check { background: #d99a00; }

.product-model-selector__hint {
    margin: 7px 0 0;
    color: var(--text-2);
    font-size: 12px;
}

.gas-meter-fit {
    padding: 15px 16px;
    border: 1px solid #dfe6dd;
    border-radius: 12px;
    background: #f7faf6;
}
.gas-meter-fit__eyebrow {
    margin: 0 0 3px;
    color: #27833f;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.gas-meter-fit__title { margin: 0 0 8px; font-weight: 650; line-height: 1.35; }
.gas-meter-fit ul { display: grid; gap: 4px; margin: 0 0 9px; padding-left: 18px; color: var(--text-2); font-size: 13px; }

@media (max-width: 767px) {
    .hotta-product-gallery .tf-product-media-main {
        aspect-ratio: 1 / 1;
    }

    .hotta-product-gallery .tf-product-media-main .swiper-wrapper,
    .hotta-product-gallery .tf-product-media-main .swiper-slide,
    .hotta-product-gallery .tf-product-media-main .item {
        height: 100% !important;
    }

    .hotta-product-gallery .tf-product-media-main .item img {
        object-fit: contain;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const stars = document.querySelectorAll('#star-rating .icon');
    const ratingInput = document.getElementById('rating-value');
    if (!stars.length || !ratingInput) return;

    function setStars(value) {
        stars.forEach(s => {
            const v = parseInt(s.dataset.value);
            s.classList.toggle('icon-Star', v <= value);
            s.classList.toggle('icon-StarEmptyOutlined', v > value);
        });
        ratingInput.value = value;
    }

    // Инициализация по old('rating')
    setStars(parseInt(ratingInput.value) || 5);

    stars.forEach(star => {
        star.addEventListener('mouseover', () => {
            stars.forEach(s => {
                const v = parseInt(s.dataset.value);
                s.classList.toggle('icon-Star', v <= parseInt(star.dataset.value));
                s.classList.toggle('icon-StarEmptyOutlined', v > parseInt(star.dataset.value));
            });
        });
        star.addEventListener('mouseleave', () => setStars(parseInt(ratingInput.value) || 5));
        star.addEventListener('click', () => setStars(parseInt(star.dataset.value)));
    });

    // Прокрутка к форме по клику "Написать отзыв"
    document.querySelectorAll('a[href="#write-review"]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const tab = document.querySelector('a[href="#customer-reviews"]');
            if (tab) tab.click();
            setTimeout(() => {
                const form = document.getElementById('write-review');
                if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        });
    });

    // После успешной отправки — открыть вкладку отзывов и показать сообщение
    @if(session('review_sent'))
    (function() {
        const tab = document.querySelector('a[href="#customer-reviews"]');
        if (tab) {
            tab.click();
            setTimeout(() => {
                const msg = document.querySelector('.alert-success');
                if (msg) msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
        }
    })();
    @endif
});
</script>
@endpush

@endsection
