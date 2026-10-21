{{-- resources/views/partials/product-card.blade.php --}}

@php
    $imageUrl   = $product->image_url;
    $imageHover = $product->imageUrl(1);

    $availabilityStatus = method_exists($product, 'effectiveAvailabilityStatus') ? $product->effectiveAvailabilityStatus() : ($product->in_stock ? 'in_stock' : 'out_of_stock');
    $availabilityLabel = method_exists($product, 'publicAvailabilityLabel') ? $product->publicAvailabilityLabel() : ($product->in_stock ? 'В наличии' : 'Нет в наличии');
    $displayAvailabilityStatus = method_exists($product, 'publicAvailabilityStatus') ? $product->publicAvailabilityStatus() : $availabilityStatus;
    $b2bComparison = app(\App\Services\B2bCatalogOfferResolver::class)->comparison($product, auth()->user());
    $b2bOffer = $b2bComparison['offer'] ?? null;
    $isB2bOffer = $b2bOffer !== null;
    $isProcurementOffer = $isB2bOffer && $b2bOffer->source?->isProcurementMode();
    $b2bMoscowStock = $isB2bOffer && !app(\App\Services\B2bCatalogOfferResolver::class)->hasFreshStock($b2bOffer)
        ? $b2bOffer->product?->warehouseStocks->first(fn ($stock) => (float) $stock->available_quantity > 0)
        : null;
    $hasAssignedSupplier = method_exists($product, 'hasAssignedSupplier') && $product->hasAssignedSupplier();
    $canBuy = $isB2bOffer
        ? $hasAssignedSupplier && $product->is_active && ! $product->is_archived && app(\App\Services\B2bCatalogOfferResolver::class)->canOrder($b2bOffer)
        : (method_exists($product, 'canBeAddedToCart') ? $product->canBeAddedToCart() : false);
    $isPublicSale = method_exists($product, 'isPublicSale') ? $product->isPublicSale() : false;
    $isWishlisted = in_array((int) $product->id, $wishlistProductIds ?? [], true);
    $hasVerifiedPublicPrice = (float) $product->price > 0
        && ! $product->requires_price_confirmation
        && $hasAssignedSupplier;

    $price = $isB2bOffer
        ? number_format($b2bComparison['wholesale_price'], 2, '.', ' ') . ' BYN'
        : ($hasVerifiedPublicPrice
            ? number_format((float) $product->price, 2, '.', ' ') . ' BYN'
            : ($availabilityStatus === 'out_of_stock' ? 'Нет в наличии' : 'Цену уточняйте'));

    $priceOld = (! $isB2bOffer && $canBuy && $isPublicSale && $product->price_old && $product->price_old > $product->price)
        ? number_format($product->price_old, 2, '.', ' ') . ' BYN'
        : null;

    $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;

    $quickViewImages = collect($product->images ?: [$product->main_image])
        ->filter()
        ->keys()
        ->map(fn ($index) => $product->imageUrl($index))
        ->values()
        ->all();

    if (empty($quickViewImages)) {
        $quickViewImages = [$product->image_url];
    }

    $heatPumpMeta = method_exists($product, 'heatPumpCatalogMeta')
        ? $product->heatPumpCatalogMeta()
        : null;
    $gasMeterMeta = \App\Support\Catalog\GasMeterSelectionGuide::forProduct($product);
@endphp

<div class="card-product product-style_stroke{{ ! $canBuy ? ' card-product--consultation' : '' }}">
    <div class="card-product_wrapper square">

        <a href="{{ $productUrl }}" class="product-img">
            <img class="img-product" loading="lazy" width="330" height="330"
                src="{{ $imageUrl }}"
                alt="{{ $product->name }}"
                onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
            <img class="img-hover" loading="lazy" width="330" height="330"
                src="{{ $imageHover }}"
                alt="{{ $product->name }}"
                onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
        </a>

        <ul class="product-action_list">
            <li class="wishlist{{ $isWishlisted ? ' addwishlist' : '' }}">
                <a href="#" class="hover-tooltip tooltip-left box-icon"
                    data-product-id="{{ $product->id }}">
                    <span class="icon {{ $isWishlisted ? 'icon-Heart' : 'icon-heart' }}"></span>
                    <span class="tooltip">{{ $isWishlisted ? 'Удалить из избранного' : 'В избранное' }}</span>
                </a>
            </li>
            <li class="compare">
                <a href="#compare"
                    data-product-id="{{ $product->id }}"
                    class="hover-tooltip tooltip-left box-icon">
                    <span class="icon icon-ArrowsLeftRight"></span>
                    <span class="tooltip">Сравнить</span>
                </a>
            </li>
            <li>
                <a href="#quickView" data-bs-toggle="offcanvas"
                    data-product-id="{{ $product->id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $price }}"
                    data-category="{{ $product->category->name ?? '' }}"
                    data-description="{{ Str::limit($gasMeterMeta ? ($gasMeterMeta['size'].' — '.$gasMeterMeta['purpose'].'. '.implode('. ', $gasMeterMeta['facts'])) : strip_tags($product->short_description ?: $product->content ?: 'Описание товара отсутствует.'), 240) }}"
                    data-image="{{ $product->image_url }}"
                    data-images='@json($quickViewImages)'
                    data-url="{{ $productUrl }}"
                    data-can-buy="{{ $canBuy ? '1' : '0' }}"
                    data-needs-confirmation="{{ $availabilityStatus === 'check' && ! $isB2bOffer ? '1' : '0' }}"
                    class="hover-tooltip tooltip-left box-icon">
                    <span class="icon icon-Eye"></span>
                    <span class="tooltip">Быстрый просмотр</span>
                </a>
            </li>
        </ul>

        {{-- Бейджи --}}
        @if ($isPublicSale || $product->is_new || $product->is_featured)
            <ul class="product-badge_list">
                @if ($isPublicSale)
                    <li class="product-badge_item text-caption-01 sale"><span class="badge-label-full">Акция</span><span class="badge-label-mobile">%</span></li>
                @elseif ($product->is_new)
                    <li class="product-badge_item text-caption-01 new"><span class="badge-label-full">Новинка</span><span class="badge-label-mobile">NEW</span></li>
                @elseif ($product->is_featured)
                    <li class="product-badge_item text-caption-01 sale"><span class="badge-label-full">Хит продаж</span><span class="badge-label-mobile">Хит</span></li>
                @endif
            </ul>
        @endif

        <div class="product-action_bot">
            @if ($canBuy)
            <a href="#shoppingCart"
                class="tf-btn btn-white small w-100 btn-add-to-cart"
                data-product-id="{{ $product->id }}"
                aria-label="Добавить {{ $product->name }} в корзину">
                <i class="icon icon-Handbag"></i>
                <span class="add-to-cart-label">{{ $availabilityStatus === 'check' && ! $isB2bOffer ? 'Заказать' : 'В корзину' }}</span>
            </a>
            @else
            <a href="#ask" data-bs-toggle="modal" class="tf-btn btn-white small w-100 btn-product-consultation btn-product-availability-request"
                data-product-id="{{ $product->id }}"
                data-product-name="{{ $product->name }}"
                data-product-url="{{ url($productUrl) }}"
                aria-label="Уточнить наличие: {{ $product->name }}" title="Уточнить наличие">
                <i class="icon icon-Question" aria-hidden="true"></i>
                <span class="product-consultation-label">Уточнить наличие</span>
            </a>
            @endif
        </div>
    </div>

    <div class="card-product_info">
        <a href="{{ $productUrl }}"
            class="name-product lh-24 fw-medium link-underline-text">
            {{ $product->name }}
        </a>

        @if ($heatPumpMeta)
            <div class="heat-pump-card-meta" aria-label="Основные характеристики модели">
                <div class="heat-pump-card-meta__chips">
                    @foreach ($heatPumpMeta['chips'] as $chip)
                        <span>{{ $chip }}</span>
                    @endforeach
                </div>
                <p>{{ $heatPumpMeta['purpose'] }}</p>
            </div>
        @endif

        @if ($gasMeterMeta)
            <p class="text-caption-01 cl-text-2 mb-2">
                <strong>{{ $gasMeterMeta['size'] }}</strong> · до {{ rtrim(rtrim(number_format($gasMeterMeta['max_flow'], 1, ',', ''), '0'), ',') }} м³/ч
                @if ($gasMeterMeta['direction']) · {{ $gasMeterMeta['direction'] }} @endif
                @if ($gasMeterMeta['length']) · L={{ $gasMeterMeta['length'] }} мм @endif
            </p>
            <p class="text-caption-01 cl-text-2 mb-6">{{ \Illuminate\Support\Str::ucfirst($gasMeterMeta['purpose']) }}.</p>
        @endif

        @if ($product->rating > 0)
            <div class="star-wrap d-flex align-items-center">
                @for ($s = 1; $s <= 5; $s++)
                    <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }}"></i>
                @endfor
            </div>
        @endif

        @if (! $isB2bOffer && $displayAvailabilityStatus === 'in_stock')
            <div class="product-card-availability" aria-label="{{ $availabilityLabel }}">
                <span class="product-card-availability__dot" aria-hidden="true"></span>
                <span>{{ $availabilityLabel }}</span>
            </div>
        @elseif (! $isB2bOffer && $displayAvailabilityStatus === 'check')
            <div class="product-card-availability" aria-label="Товар доступен к заказу, наличие подтвердит менеджер">
                <span class="product-card-availability__dot" aria-hidden="true"></span>
                <span>{{ $availabilityLabel }}</span>
            </div>
        @endif

        <div class="price-wrap">
            <span class="price-new text-primary fw-semibold">{{ $price }}</span>
            @if ($priceOld)
                <span class="price-old text-decoration-line-through cl-text-2 ms-8">
                    {{ $priceOld }}
                </span>
            @endif
        </div>

        @if ($isB2bOffer)
            <p class="text-caption-01 cl-text-2 mb-0">
                Партнёрская цена {{ $b2bOffer->source?->partnerName() }}
            </p>
            @if ($b2bComparison['retail_price'] > 0)
                <p class="text-caption-01 cl-text-2 mb-0">
                    Розничная: {{ number_format($b2bComparison['retail_price'], 2, '.', ' ') }} BYN
                </p>
            @endif
            @if ($b2bComparison['difference'] > 0)
                <p class="text-caption-01 text-success mb-0">
                    Ваша скидка к рознице: {{ number_format($b2bComparison['difference'], 2, '.', ' ') }} BYN
                    @if ($b2bComparison['difference_percent'] !== null)
                        ({{ number_format($b2bComparison['difference_percent'], 1, '.', ' ') }}%)
                    @endif
                </p>
            @endif
            <p class="text-caption-01 text-success mb-0">
                @if ($isProcurementOffer)
                    {{ data_get($b2bOffer->source?->settings, 'warehouse_label', 'Под заказ') }}
                @elseif ($b2bMoscowStock)
                    {{ $b2bMoscowStock->warehouse_name }}: {{ $b2bMoscowStock->formattedAvailableQuantity() }}
                @else
                    {{ $product->brand?->slug === 'ecokamin' ? 'Склад Минск' : data_get($b2bOffer->source?->settings, 'warehouse_label', 'Основной') }}: {{ $b2bOffer->formattedStockQuantity() }}
                @endif
            </p>
        @endif

        @if ($showManagerContext ?? true)
            @include('partials.manager-product-context', ['product' => $product, 'compact' => true, 'productUrl' => $productUrl])
        @endif

    </div>
</div>
