{{-- resources/views/partials/product-card.blade.php --}}

@php
    $image = is_array($product->images) ? ($product->images[0] ?? null) : null;

    if ($image) {
        // Фото берём с живого сайта kotlov.by
        $imageUrl = 'https://kotlov.by/images/product/' . $image;
        $imageHover = isset($product->images[1])
            ? 'https://kotlov.by/images/product/' . $product->images[1]
            : $imageUrl;
    } else {
        $imageUrl = asset('img/products/product-placeholder.jpg');
        $imageHover = $imageUrl;
    }

    $price = $product->price > 0
        ? number_format($product->price, 2, '.', ' ') . ' BYN'
        : 'Цена по запросу';

    $priceOld = ($product->price_old && $product->price_old > 0)
        ? number_format($product->price_old, 2, '.', ' ') . ' BYN'
        : null;

    $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;
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
                    <li class="product-badge_item text-caption-01 sale">Акция</li>
                @elseif ($product->is_new)
                    <li class="product-badge_item text-caption-01 new">Новинка</li>
                @elseif ($product->is_featured)
                    <li class="product-badge_item text-caption-01 sale">Хит продаж</li>
                @endif
            </ul>
        @endif

        <div class="product-action_bot">
            <a href="#shoppingCart"
                class="tf-btn btn-white small w-100 btn-add-to-cart"
                data-product-id="{{ $product->id }}">
                В корзину
            </a>
        </div>
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
