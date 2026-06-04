{{-- resources/views/partials/product-card-list.blade.php --}}
{{-- List view — детальная карточка товара --}}

@php
    $placeholder = asset('img/products/product-placeholder.jpg');
    $imageUrl  = $product->image_url;
    $imageUrl2 = $product->imageUrl(1);

    $price = $product->price > 0
        ? number_format($product->price, 2, '.', ' ') . ' BYN'
        : 'Цена по запросу';
    $priceOld = ($product->price_old && $product->price_old > 0)
        ? number_format($product->price_old, 2, '.', ' ') . ' BYN'
        : null;

    $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;
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
    </div>

    <div class="card-product_info">
        <a href="{{ $productUrl }}" class="name-product lh-24 fw-medium link-underline-text">
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
                <span class="price-old text-caption-01 cl-text-3">{{ $priceOld }}</span>
            @endif
        </div>

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
            @if ($product->in_stock)
                <span class="text-caption-01 text-success">
                    <i class="icon icon-CheckCircle"></i> В наличии
                </span>
            @else
                <span class="text-caption-01 text-danger">
                    <i class="icon icon-XCircle"></i> Нет в наличии
                </span>
            @endif
        </div>

        {{-- Действия --}}
        <ul class="product-action_list">
            <li>
                <a href="#shoppingCart"
                    class="hover-tooltip box-icon btn-add-to-cart"
                    data-product-id="{{ $product->id }}">
                    <span class="icon icon-Handbag"></span>
                    <span class="tooltip">В корзину</span>
                </a>
            </li>
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
                <a href="#quickView" data-bs-toggle="offcanvas" class="hover-tooltip box-icon">
                    <span class="icon icon-Eye"></span>
                    <span class="tooltip">Быстрый просмотр</span>
                </a>
            </li>
        </ul>
    </div>
</div>
