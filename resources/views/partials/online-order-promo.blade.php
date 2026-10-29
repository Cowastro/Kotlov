@php
    // Only the product page itself: on listings @extends passes the last
    // loop variable $product to the layout, which must not hide the banner.
    $onlineDiscountExcludedProduct = request()->route()?->getActionName() === \App\Http\Controllers\ProductController::class.'@show'
        && isset($product)
        && $product instanceof \App\Models\Product
        && ! $product->qualifiesForOnlineOrderDiscount();
@endphp
@unless (! \App\Services\CartCouponService::onlineDiscountIsPublic() || auth()->user()?->isB2B() || $onlineDiscountExcludedProduct)
<aside class="online-order-promo" aria-label="Специальные условия онлайн-оформления">
    <div class="container online-order-promo__inner">
        <div class="online-order-promo__text">
            <strong>Специальные условия онлайн</strong>
            <span>Оформляйте через корзину в любое время — итоговая стоимость рассчитывается в корзине. Условия зависят от товара.</span>
        </div>
        <a href="{{ route('cart') }}" class="online-order-promo__code" aria-label="Перейти в корзину и узнать итоговую стоимость">
            <strong>Узнать стоимость</strong>
        </a>
    </div>
</aside>
@endunless
