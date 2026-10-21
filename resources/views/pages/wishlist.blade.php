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
                    <p class="cl-text-2" id="wishlist-page-count">{{ $products->count() }} {{ trans_choice('товар|товара|товаров', $products->count()) }}</p>
                </div>

                <div class="tf-grid-layout tf-col-2 md-col-3 xl-col-4 wrapper-wishlist">
                    @foreach ($products as $product)
                        @php
                            $placeholder = asset('img/products/product-placeholder.jpg');
                            $imageUrl  = $product->image_url;
                            $imageUrl2 = $product->imageUrl(1);
                            $hasAssignedSupplier = $product->hasAssignedSupplier();
                            $hasVerifiedPublicPrice = (float) $product->price > 0
                                && ! $product->requires_price_confirmation
                                && $hasAssignedSupplier;
                            $price = $hasVerifiedPublicPrice
                                ? number_format($product->price, 2, '.', ' ') . ' BYN'
                                : 'Цену уточняйте';
                            $isPublicSale = method_exists($product, 'isPublicSale') ? $product->isPublicSale() : false;
                            $priceOld = ($hasVerifiedPublicPrice && $isPublicSale && $product->price_old && $product->price_old > $product->price)
                                ? number_format($product->price_old, 2, '.', ' ') . ' BYN'
                                : null;
                            $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;
                            $wishlistB2bOffer = app(\App\Services\B2bCatalogOfferResolver::class)
                                ->forProduct($product, auth()->user());
                            $canBuy = $wishlistB2bOffer
                                ? $hasAssignedSupplier && $product->is_active && ! $product->is_archived && (float) $wishlistB2bOffer->stock_quantity >= 1
                                : $product->canBeAddedToCart();
                            $quickViewImages = collect($product->images ?: [$product->main_image])
                                ->filter()
                                ->keys()
                                ->map(fn ($index) => $product->imageUrl($index))
                                ->values()
                                ->all();
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
                                            data-product-id="{{ $product->id }}"
                                            data-wishlist-remove>
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
                                            data-product-id="{{ $product->id }}"
                                            data-name="{{ $product->name }}"
                                            data-price="{{ $price }}"
                                            data-category="{{ $product->category->name ?? '' }}"
                                            data-description="{{ Str::limit(strip_tags($product->short_description ?: $product->content ?: 'Описание товара отсутствует.'), 240) }}"
                                            data-image="{{ $product->image_url }}"
                                            data-images='@json($quickViewImages ?: [$product->image_url])'
                                            data-url="{{ $productUrl }}"
                                            data-can-buy="{{ $canBuy ? '1' : '0' }}"
                                            class="hover-tooltip tooltip-left box-icon">
                                            <span class="icon icon-Eye"></span>
                                            <span class="tooltip">Быстрый просмотр</span>
                                        </a>
                                    </li>
                                </ul>

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

                                <div class="product-action_bot">
                                    @if ($canBuy)
                                    <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                        class="tf-btn btn-white small w-100 btn-add-to-cart"
                                        data-product-id="{{ $product->id }}">
                                        В корзину
                                    </a>
                                    @else
                                    <a href="#ask" data-bs-toggle="modal"
                                        class="tf-btn btn-white small w-100 btn-product-availability-request"
                                        data-product-id="{{ $product->id }}"
                                        data-product-name="{{ $product->name }}"
                                        data-product-url="{{ url($productUrl) }}">
                                        Уточнить наличие
                                    </a>
                                    @endif
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
function wishlistWord(count) {
    var mod100 = count % 100;
    var mod10 = count % 10;

    if (mod100 >= 11 && mod100 <= 14) return 'товаров';
    if (mod10 === 1) return 'товар';
    if (mod10 >= 2 && mod10 <= 4) return 'товара';
    return 'товаров';
}

function removeWishlist(productId, el) {
    $.ajax({
        url: '/wishlist/remove',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        xhrFields: { withCredentials: true },
        data: JSON.stringify({ product_id: productId }),
        success: function (data) {
            var $card = $(el).closest('.card-product');
            $card.fadeOut(300, function () {
                $card.remove();
                var count = $('.wrapper-wishlist .card-product').length;
                if (count === 0) location.reload();
                $('#wishlist-page-count').text(count + ' ' + wishlistWord(count));
            });
            if (window.updateWishlistCount) {
                window.updateWishlistCount(Number(data.count) || 0);
            }
        }
    });
}

$(document).on('click', '[data-wishlist-remove]', function (e) {
    e.preventDefault();
    removeWishlist(parseInt($(this).data('product-id'), 10), this);
});

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
