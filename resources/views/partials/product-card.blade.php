{{-- resources/views/partials/product-card.blade.php --}}

@php
    $imageUrl   = $product->image_url;
    $imageHover = $product->imageUrl(1);

    $availabilityStatus = method_exists($product, 'effectiveAvailabilityStatus') ? $product->effectiveAvailabilityStatus() : ($product->in_stock ? 'in_stock' : 'out_of_stock');
    $availabilityLabel = method_exists($product, 'availabilityLabel') ? $product->availabilityLabel() : ($product->in_stock ? 'В наличии' : 'Нет в наличии');
    $canBuy = method_exists($product, 'canBeOrdered') ? $product->canBeOrdered() : ($product->in_stock && $product->price > 0);

    $price = $canBuy
        ? number_format($product->price, 2, '.', ' ') . ' BYN'
        : ($availabilityStatus === 'out_of_stock' ? 'Нет в наличии' : 'Цена по запросу');

    $priceOld = ($product->price_old && $product->price_old > 0)
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
@endphp

<div class="card-product product-style_stroke">
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
            <li class="wishlist">
                <a href="#" class="hover-tooltip tooltip-left box-icon"
                    data-product-id="{{ $product->id }}">
                    <span class="icon icon-heart"></span>
                    <span class="tooltip">В избранное</span>
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
                    data-description="{{ Str::limit(strip_tags($product->short_description ?: $product->content ?: 'Описание товара отсутствует.'), 240) }}"
                    data-image="{{ $product->image_url }}"
                    data-images='@json($quickViewImages)'
                    data-url="{{ $productUrl }}"
                    class="hover-tooltip tooltip-left box-icon">
                    <span class="icon icon-Eye"></span>
                    <span class="tooltip">Быстрый просмотр</span>
                </a>
            </li>
        </ul>

        {{-- Бейджи --}}
        @if ($product->is_sale || $product->is_new || $product->is_featured)
            <ul class="product-badge_list">
                @if ($product->is_sale)
                    <li class="product-badge_item text-caption-01 sale"><span class="badge-label-full">Акция</span><span class="badge-label-mobile">%</span></li>
                @elseif ($product->is_new)
                    <li class="product-badge_item text-caption-01 new"><span class="badge-label-full">Новинка</span><span class="badge-label-mobile">NEW</span></li>
                @elseif ($product->is_featured)
                    <li class="product-badge_item text-caption-01 sale"><span class="badge-label-full">Хит продаж</span><span class="badge-label-mobile">Хит</span></li>
                @endif
            </ul>
        @endif

        @if ($canBuy)
        <div class="product-action_bot">
            <a href="#shoppingCart"
                class="tf-btn btn-white small w-100 btn-add-to-cart"
                data-product-id="{{ $product->id }}"
                aria-label="Добавить {{ $product->name }} в корзину">
                <i class="icon icon-Handbag"></i>
                <span class="add-to-cart-label">В корзину</span>
            </a>
        </div>
        @endif
    </div>

    <div class="card-product_info">
        <a href="{{ $productUrl }}"
            class="name-product lh-24 fw-medium link-underline-text">
            {{ $product->name }}
        </a>

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
                <span class="price-old text-decoration-line-through cl-text-2 ms-8">
                    {{ $priceOld }}
                </span>
            @endif
        </div>
    </div>
</div>
