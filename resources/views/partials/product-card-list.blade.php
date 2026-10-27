{{-- resources/views/partials/product-card-list.blade.php --}}
{{-- List view — детальная карточка товара --}}

@php
    $placeholder = asset('img/products/product-placeholder.jpg');
    $imageUrl  = $product->image_url;
    $imageUrl2 = $product->imageUrl(1);

    $availabilityStatus = method_exists($product, 'effectiveAvailabilityStatus') ? $product->effectiveAvailabilityStatus() : ($product->in_stock ? 'in_stock' : 'out_of_stock');
    $availabilityLabel = method_exists($product, 'publicAvailabilityLabel') ? $product->publicAvailabilityLabel() : ($product->in_stock ? 'В наличии' : 'Нет в наличии');
    $displayAvailabilityStatus = method_exists($product, 'publicAvailabilityStatus') ? $product->publicAvailabilityStatus() : $availabilityStatus;
    $b2bComparison = app(\App\Services\B2bCatalogOfferResolver::class)->comparison($product, auth()->user());
    $b2bOffer = $b2bComparison['offer'] ?? null;
    $isB2bOffer = $b2bOffer !== null;
    $isProcurementOffer = $isB2bOffer && $b2bOffer->source?->isProcurementMode();
    $b2bAvailability = $isB2bOffer
        ? app(\App\Services\B2bCatalogOfferResolver::class)->availabilityFor($b2bOffer, auth()->user())
        : null;
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
    $onlineDiscountPercent = (float) config('shop.online_order_discount.percent', 3);
    $onlinePrice = ! $isB2bOffer && $canBuy && $product->qualifiesForOnlineOrderDiscount() && $hasVerifiedPublicPrice
        ? round((float) $product->price * (1 - $onlineDiscountPercent / 100), 2)
        : null;
    $onlineSaving = $onlinePrice !== null ? round((float) $product->price - $onlinePrice, 2) : null;

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

<div class="card-product product-style_list">
    <div class="card-product_wrapper">
        <a href="{{ $productUrl }}" class="product-img">
            <img class="img-product" loading="lazy" width="330" height="440"
                src="{{ $imageUrl }}" alt="{{ $product->name }}"
                onerror="this.src='{{ $placeholder }}'">
            <img class="img-hover" loading="lazy" width="330" height="440"
                src="{{ $imageUrl2 }}" alt="{{ $product->name }}"
                onerror="this.src='{{ $placeholder }}'">
        </a>

        {{-- Бейджи --}}
        @if ($isPublicSale || $product->is_new || $product->is_featured)
            <ul class="product-badge_list">
                @if ($isPublicSale)
                    <li class="product-badge_item text-caption-01 sale">Акция</li>
                @elseif ($product->is_new)
                    <li class="product-badge_item text-caption-01 new">Новинка</li>
                @elseif ($product->is_featured)
                    <li class="product-badge_item text-caption-01 sale">Хит продаж</li>
                @endif
            </ul>
        @endif
    </div>

    <div class="card-product_info">
        <a href="{{ $productUrl }}" class="name-product lh-24 fw-medium link-underline-text">
            {{ $product->name }}
        </a>

        @if ($heatPumpMeta)
            <div class="heat-pump-card-meta heat-pump-card-meta--list" aria-label="Основные характеристики модели">
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
                @if ($gasMeterMeta['thermal']) · с термокомпенсацией @endif
            </p>
            <p class="text-caption-01 cl-text-2 mb-8">{{ \Illuminate\Support\Str::ucfirst($gasMeterMeta['purpose']) }}.</p>
        @endif

        @if ($product->rating > 0)
            <div class="star-wrap d-flex align-items-center">
                @for ($s = 1; $s <= 5; $s++)
                    <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }}"></i>
                @endfor
            </div>
        @endif

        <div class="price-wrap">
            <span class="price-new text-primary fw-semibold">{{ $price }}</span>
            @if ($priceOld)
                <span class="price-old text-caption-01 cl-text-3">{{ $priceOld }}</span>
            @endif
        </div>

        @if ($onlinePrice !== null)
            <p class="online-order-card-price mb-6">Онлайн: {{ number_format($onlinePrice, 2, '.', ' ') }} BYN · экономия {{ number_format($onlineSaving, 2, '.', ' ') }} BYN</p>
        @endif

        @if ($isB2bOffer)
            <p class="text-caption-01 cl-text-2 mb-4">
                Партнёрская цена {{ $b2bOffer->source?->partnerName() }}
            </p>
            @if ($b2bComparison['retail_price'] > 0)
                <p class="text-caption-01 cl-text-2 mb-0">
                    Розничная: {{ number_format($b2bComparison['retail_price'], 2, '.', ' ') }} BYN
                </p>
            @endif
            @if ($b2bComparison['difference'] > 0)
                <p class="text-caption-01 text-success mb-4">
                    Ваша скидка к рознице: {{ number_format($b2bComparison['difference'], 2, '.', ' ') }} BYN
                    @if ($b2bComparison['difference_percent'] !== null)
                        ({{ number_format($b2bComparison['difference_percent'], 1, '.', ' ') }}%)
                    @endif
                </p>
            @endif
        @endif

        {{-- Краткое описание --}}
        @if ($product->short_description)
            <p class="description text-caption-01 mb-10">
                {{ Str::limit($product->short_description, 200) }}
            </p>
        @elseif ($product->content)
            <p class="description text-caption-01 mb-10">
                {{ Str::limit(strip_tags($product->content), 200) }}
            </p>
        @endif

        {{-- Бренд и артикул --}}
        @if ($product->brand || $product->sku)
            <div class="d-flex gap-16 mb-10 text-caption-01 cl-text-2">
                @if ($product->brand)
                    <span>Бренд: <strong>{{ $product->brand->name }}</strong></span>
                @endif
                @if ($product->sku)
                    <span>Арт: <strong>{{ $product->sku }}</strong></span>
                @endif
            </div>
        @endif

        @include('partials.manager-product-context', ['product' => $product, 'compact' => true, 'productUrl' => $productUrl])

        {{-- Наличие --}}
        <div class="mb-10">
            @if ($isB2bOffer)
                <span class="text-caption-01 text-success">
                    <i class="icon icon-CheckCircle"></i>
                    @if ($b2bAvailability)
                        {{ $b2bAvailability['label'] }}
                    @elseif ($b2bMoscowStock)
                        {{ $b2bMoscowStock->warehouse_name }}:
                        {{ $b2bMoscowStock->formattedAvailableQuantity() }}
                    @else
                        {{ $product->brand?->slug === 'ecokamin' ? 'Склад Минск' : data_get($b2bOffer->source?->settings, 'warehouse_label', 'Основной') }}:
                        {{ $b2bOffer->formattedStockQuantity() }}
                    @endif
                </span>
            @elseif ($displayAvailabilityStatus === 'in_stock')
                <span class="text-caption-01 text-success">
                    <i class="icon icon-CheckCircle"></i> {{ $availabilityLabel }}
                </span>
            @elseif ($displayAvailabilityStatus === 'check')
                <span class="text-caption-01 text-warning">
                    <i class="icon icon-CheckCircle"></i> {{ $availabilityLabel }} · наличие и срок подтвердит менеджер
                </span>
            @else
                <span class="text-caption-01 text-danger">
                    <i class="icon icon-XCircle"></i> Нет в наличии
                </span>
            @endif
        </div>

        {{-- Действия --}}
        <ul class="product-action_list">
            @if ($canBuy)
            <li>
                <a href="#shoppingCart"
                    class="hover-tooltip box-icon btn-add-to-cart"
                    data-product-id="{{ $product->id }}">
                    <span class="icon icon-Handbag"></span>
                    <span class="tooltip">{{ $availabilityStatus === 'check' && ! $isB2bOffer ? 'Заказать' : 'В корзину' }}</span>
                </a>
            </li>
            @else
            <li>
                <a href="#ask" data-bs-toggle="modal" class="hover-tooltip box-icon btn-product-availability-request"
                    data-product-id="{{ $product->id }}"
                    data-product-name="{{ $product->name }}"
                    data-product-url="{{ url($productUrl) }}">
                    <span class="icon icon-Phone"></span>
                    <span class="tooltip">Уточнить наличие</span>
                </a>
            </li>
            @endif
            <li class="wishlist{{ $isWishlisted ? ' addwishlist' : '' }}">
                <a href="#" class="hover-tooltip box-icon"
                    data-product-id="{{ $product->id }}">
                    <span class="icon {{ $isWishlisted ? 'icon-Heart' : 'icon-heart' }}"></span>
                    <span class="tooltip">{{ $isWishlisted ? 'Удалить из избранного' : 'В избранное' }}</span>
                </a>
            </li>
            <li class="compare">
                <a href="#compare"
                    data-product-id="{{ $product->id }}"
                    class="hover-tooltip box-icon">
                    <span class="icon icon-ArrowsLeftRight"></span>
                    <span class="tooltip">Сравнить</span>
                </a>
            </li>
            <li>
                <a href="#quickView" data-bs-toggle="offcanvas"
                    data-product-id="{{ $product->id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $price }}"
                    data-online-price="{{ $onlinePrice !== null ? number_format($onlinePrice, 2, '.', ' ').' BYN' : '' }}"
                    data-online-saving="{{ $onlineSaving !== null ? number_format($onlineSaving, 2, '.', ' ').' BYN' : '' }}"
                    data-category="{{ $product->category->name ?? '' }}"
                    data-description="{{ Str::limit($gasMeterMeta ? ($gasMeterMeta['size'].' — '.$gasMeterMeta['purpose'].'. '.implode('. ', $gasMeterMeta['facts'])) : strip_tags($product->short_description ?: $product->content ?: 'Описание товара отсутствует.'), 240) }}"
                    data-image="{{ $product->image_url }}"
                    data-images='@json($quickViewImages)'
                    data-url="{{ $productUrl }}"
                    data-can-buy="{{ $canBuy ? '1' : '0' }}"
                    data-needs-confirmation="{{ $availabilityStatus === 'check' && ! $isB2bOffer ? '1' : '0' }}"
                    class="hover-tooltip box-icon">
                    <span class="icon icon-Eye"></span>
                    <span class="tooltip">Быстрый просмотр</span>
                </a>
            </li>
        </ul>
    </div>
</div>
