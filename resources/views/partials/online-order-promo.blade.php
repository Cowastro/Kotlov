@unless (auth()->user()?->isB2B())
<aside class="online-order-promo" aria-label="Скидка за онлайн-заказ">
    <div class="container online-order-promo__inner">
        <div class="online-order-promo__text">
            <strong>Онлайн-цена ниже на 3%</strong>
            <span>Оформляйте через корзину в любое время — скидка применится автоматически.</span>
        </div>
        <a href="{{ route('cart') }}" class="online-order-promo__code" aria-label="Перейти в корзину и получить онлайн-скидку">
            <strong>Получить −3%</strong>
        </a>
    </div>
</aside>
@endunless
