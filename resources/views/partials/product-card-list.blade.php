{{-- resources/views/partials/product-card-list.blade.php --}}
{{-- List view — детальная карточка товара --}}

@php
    $placeholder = asset('img/products/product-placeholder.jpg');
    $imageUrl  = $product->image_url;
    $imageUrl2 = $product->imageUrl(1);

    $availabilityStatus = method_exists($product, 'effectiveAvailabilityStatus') ? $product->effectiveAvailabilityStatus() : ($product->in_stock ? 'in_stock' : 'out_of_stock');
    $availabilityLabel = method_exists($product, 'availabilityLabel') ? $product->availabilityLabel() : ($product->in_stock ? 'В наличии' : 'Нет в наличии');
    $b2bComparison = app(\App\Services\B2bCatalogOfferResolver::class)->comparison($product, auth()->user());
    $b2bOffer = $b2bComparison['offer'] ?? null;
    $isB2bOffer = $b2bOffer !== null;
    $canBuy = $isB2bOffer
        ? ! $product->is_archived && (float) $b2bOffer->stock_quantity >= 1
        : (method_exists($product, 'canBeOrdered') ? $product->canBeOrdered() : ($product->in_stock && $product->price > 0));
    $isPublicSale = method_exists($product, 'isPublicSale') ? $product->isPublicSale() : false;

    $price = $isB2bOffer
        ? number_format($b2bComparison['wholesale_price'], 2, '.', ' ') . ' BYN'
        : ($canBuy
            ? number_format((float) $product->price, 2, '.', ' ') . ' BYN'
            : ($availabilityStatus === 'out_of_stock' ? 'Нет в наличии' : 'Цена по запросу'));
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

        {{-- Наличие --}}
        <div class="mb-10">
            @if ($isB2bOffer)
                <span class="text-caption-01 text-success">
                    <i class="icon icon-CheckCircle"></i>
                    {{ data_get($b2bOffer->source?->settings, 'warehouse_label', 'Основной') }}:
                    {{ $b2bOffer->formattedStockQuantity() }}
                </span>
            @elseif ($availabilityStatus === 'in_stock')
                <span class="text-caption-01 text-success">
                    <i class="icon icon-CheckCircle"></i> В наличии
                </span>
            @elseif ($availabilityStatus === 'check')
                <span class="text-caption-01 text-warning">
                    <i class="icon icon-CheckCircle"></i> {{ $availabilityLabel }}
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
                    <span class="tooltip">В корзину</span>
                </a>
            </li>
            @endif
            <li class="wishlist">
                <a href="#" class="hover-tooltip box-icon"
                    data-product-id="{{ $product->id }}">
                    <span class="icon icon-heart"></span>
                    <span class="tooltip">В избранное</span>
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
                    data-category="{{ $product->category->name ?? '' }}"
                    data-description="{{ Str::limit(strip_tags($product->short_description ?: $product->content ?: 'Описание товара отсутствует.'), 240) }}"
                    data-image="{{ $product->image_url }}"
                    data-images='@json($quickViewImages)'
                    data-url="{{ $productUrl }}"
                    class="hover-tooltip box-icon">
                    <span class="icon icon-Eye"></span>
                    <span class="tooltip">Быстрый просмотр</span>
                </a>
            </li>
        </ul>
    </div>
</div>
