@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Избранное</p>
                </div>
                <h3>Избранное</h3>
                <p class="text-body-1 cl-text-2">
                    Сохранённые товары — просматривайте и добавляйте в корзину<br class="d-none d-lg-block">
                    в удобное для вас время.
                </p>
            </div>
        </div>
    </section>

    {{-- Список избранного --}}
    <div class="section-wishlist flat-spacing">
        <div class="container">

            @if ($products->count() > 0)

                <div class="d-flex justify-content-between align-items-center mb-20">
                    <p class="cl-text-2">{{ $products->count() }} {{ trans_choice('товар|товара|товаров', $products->count()) }}</p>
                </div>

                <div class="tf-grid-layout tf-col-2 md-col-3 xl-col-4 wrapper-wishlist">
                    @foreach ($products as $product)
                        @php
                            $images = is_array($product->images) ? $product->images : [];
                            $img  = $images[0] ?? null;
                            $img2 = $images[1] ?? null;
                            $placeholder = asset('img/products/product-placeholder.jpg');
                            $imageUrl  = $img  ? 'https://kotlov.by/images/product/' . $img  : $placeholder;
                            $imageUrl2 = $img2 ? 'https://kotlov.by/images/product/' . $img2 : $imageUrl;
                            $price    = $product->price > 0
                                ? number_format($product->price, 2, '.', ' ') . ' BYN'
                                : 'Цена по запросу';
                            $priceOld = ($product->price_old && $product->price_old > 0)
                                ? number_format($product->price_old, 2, '.', ' ') . ' BYN'
                                : null;
                            $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;
                        @endphp

                        <div class="card-product" data-product-id="{{ $product->id }}">
                            <div class="card-product_wrapper">
                                <a href="{{ $productUrl }}" class="product-img">
                                    <img class="img-product" loading="lazy" width="330" height="440"
                                        src="{{ $imageUrl }}" alt="{{ $product->name }}"
                                        onerror="this.src='{{ $placeholder }}'">
                                    <img class="img-hover" loading="lazy" width="330" height="440"
                                        src="{{ $imageUrl2 }}" alt="{{ $product->name }}"
                                        onerror="this.src='{{ $placeholder }}'">
                                </a>

                                <ul class="product-action_list">
                                    <li class="wishlist">
                                        {{-- Кнопка удалить из избранного --}}
                                        <a href="#" class="hover-tooltip tooltip-left box-icon addwishlist"
                                            onclick="removeWishlist({{ $product->id }}, this); return false;">
                                            <span class="icon icon-trash"></span>
                                            <span class="tooltip">Удалить</span>
                                        </a>
                                    </li>
                                    <li class="compare">
                                        <a href="#compare" data-bs-toggle="offcanvas"
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
                                    <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                        class="tf-btn btn-white small w-100">
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
                    @endforeach
                </div>

            @else
                {{-- Пустое состояние --}}
                <div class="text-center py-60">
                    <i class="icon icon-HeartStraight fs-48 cl-text-3 mb-16"></i>
                    <p class="h5 cl-text-2 mb-8">Список избранного пуст</p>
                    <p class="text-body-1 cl-text-3 mb-24">
                        Добавляйте понравившиеся товары нажатием на сердечко
                    </p>
                    <a href="/catalog" class="tf-btn btn-primary">Перейти в каталог</a>
                </div>
            @endif

        </div>
    </div>

</main>

@push('scripts')
<script>
function removeWishlist(productId, el) {
    $.ajax({
        url: '/wishlist/remove',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ product_id: productId }),
        success: function () {
            var $card = $(el).closest('.card-product');
            $card.fadeOut(300, function () {
                $card.remove();
                var count = $('.wrapper-wishlist .card-product').length;
                if (count === 0) location.reload();
            });
        }
    });
}

// Кнопки wishlist на карточках — toggle через AJAX
$(document).on('click', '.card-product .wishlist a:not(.addwishlist)', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var productId = $btn.closest('.card-product').data('product-id');
    if (!productId) return;

    $.ajax({
        url: '/wishlist/toggle',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ product_id: productId }),
        success: function (data) {
            var icon = $btn.find('.icon');
            var tip  = $btn.find('.tooltip');
            if (data.message === 'added') {
                icon.removeClass('icon-heart').addClass('icon-trash');
                tip.text('Удалить из избранного');
                $btn.addClass('addwishlist');
            } else {
                icon.removeClass('icon-trash').addClass('icon-heart');
                tip.text('В избранное');
                $btn.removeClass('addwishlist');
            }
        }
    });
});
</script>
@endpush

@endsection
