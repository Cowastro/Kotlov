@extends('layouts.amerce')

@section('content')
<main id="wrapper" class="cart-page {{ $b2bClient ? 'cart-page--b2b' : '' }}" data-cart-workspace data-initial-selection='@json($cartSelection)'>

    @if ($b2bClient)
        @include('partials.b2b-page-head')
        <div class="container"><h1 class="b2b-cart-title">Корзина заказа <span>{{ $b2bClient->company_name }}</span></h1></div>
    @else
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Корзина</p>
                </div>
                <h3>Корзина</h3>
            </div>
        </div>
    </section>
    @endif

    {{--
        ВАЖНО: убраны классы each-list-prd и each-prd — они триггерят Amerce main.js,
        который пересчитывает суммы в USD. Используем собственный JS.
    --}}
    <section class="section-shoping-cart flat-spacing-2">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success mb-20">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger mb-20">{{ session('error') }}</div>
            @endif

            @include('partials.cart-selection-tools')

            @if (count($items) > 0)
                <div class="row">

                    {{-- Таблица товаров --}}
                    <div class="col-lg-8">
                        @include('partials.cart-workspace-items')

                        <div class="d-flex justify-content-between align-items-center mt-16">
                            <a href="{{ $b2bClient ? route('b2b.catalog') : '/catalog' }}" class="tf-btn-line-3 type-primary">
                                <span class="text-caption-01 fw-semibold">← Продолжить покупки</span>
                            </a>
                            <form action="{{ route('cart.clear') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="tf-btn-line-3 type-primary border-0 bg-transparent p-0">
                                    <span class="text-caption-01 fw-semibold">Очистить корзину</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Итого + согласие + кнопка --}}
                    <div class="col-lg-4">
                        <div class="fl-sidebar-cart mt-lg-0 sticky-top">
                            <div class="box-order-summary" id="cart-order-summary" tabindex="-1">
                                <h5 class="title mb-20">Итого по выбранным товарам</h5>

                                <div class="subtotal d-flex justify-content-between align-items-center mb-12">
                                    <p class="fw-medium lh-24 mb-0">Товаров:</p>
                                    <span class="fw-medium lh-24" id="cart-items-count">
                                        {{ $cartSelection['count'] }} шт.
                                    </span>
                                </div>

                                <div class="subtotal d-flex justify-content-between align-items-center mb-20">
                                    <p class="fw-medium lh-24 mb-0">Сумма товаров:</p>
                                    <span class="fw-semibold text-primary" id="cart-subtotal">
                                        {{ number_format($subtotal, 2, '.', ' ') }} BYN
                                    </span>
                                </div>

                                <div id="cart-coupon-summary" @if ($coupon['discount'] <= 0) style="display:none" @endif>
                                    <div id="cart-online-discount-row" class="d-flex justify-content-between align-items-center mb-12" @if ($coupon['coupon_discount'] <= 0) style="display:none" @endif>
                                        <p class="fw-medium lh-24 mb-0">{{ $coupon['code'] === config('shop.online_order_discount.code', 'ONLINE3') ? 'Онлайн-скидка −3%:' : 'Скидка по промокоду '.$coupon['code'].':' }}</p>
                                        <span class="fw-semibold" id="cart-online-discount">−{{ number_format($coupon['coupon_discount'], 2, '.', ' ') }} BYN</span>
                                    </div>
                                    <div id="cart-bundle-discount-row" class="d-flex justify-content-between align-items-center mb-12" @if ($coupon['bundle_discount'] <= 0) style="display:none" @endif>
                                        <p class="fw-medium lh-24 mb-0" id="cart-bundle-discount-label">{{ $coupon['bundle']['mode'] === 'self_selection' ? 'Самостоятельный подбор' : 'Дымоход с оборудованием' }} −{{ number_format($coupon['bundle']['percent'], 0) }}%:</p>
                                        <span class="fw-semibold" id="cart-bundle-discount">−{{ number_format($coupon['bundle_discount'], 2, '.', ' ') }} BYN</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-20">
                                        <p class="fw-semibold lh-24 mb-0">Итого со скидкой:</p>
                                        <span class="fw-semibold text-primary" id="cart-total">{{ number_format($coupon['total'], 2, '.', ' ') }} BYN</span>
                                    </div>
                                </div>

                                @unless ($b2bClient)
                                @if ($coupon['code'] === config('shop.online_order_discount.code', 'ONLINE3') && \App\Services\CartCouponService::onlineDiscountIsPublic())
                                    <div class="online-order-cart-note mb-20" id="cart-online-note">
                                        <strong>Онлайн-скидка применена автоматически</strong>
                                        <p class="text-caption-01 cl-text-2 mt-4 mb-0">Онлайн-скидка применяется к участвующим в предложении товарам. Тепловые насосы не участвуют. Ничего вводить не нужно.</p>
                                    </div>
                                @endif

                                {{-- Промокод: всегда на виду --}}
                                <form method="POST" action="{{ route('cart.coupon') }}" class="promo-inline mb-20">
                                    @csrf
                                    <input type="text" name="coupon" id="cart-coupon" value="{{ session('cart_coupon') }}"
                                        placeholder="Промокод" maxlength="50" autocomplete="off" aria-label="Промокод">
                                    <button type="submit" class="tf-btn btn-stroke">Применить</button>
                                </form>

                                @if ($coupon['bundle']['active'])
                                    <div class="online-order-cart-note mb-20" id="cart-bundle-note">
                                        <strong>Скидка на дымоход применена</strong>
                                        <p class="text-caption-01 cl-text-2 mt-4 mb-0">Печь, камин или котёл и участвующие комплектующие находятся в одной корзине. На дымоход действует −{{ number_format($coupon['bundle']['percent'], 0) }}%.</p>
                                    </div>
                                @endif

                                @endunless

                                <div class="br-line mb-16"></div>

                                @if ($b2bClient)
                                    <div class="d-flex justify-content-between align-items-start gap-12 mb-16">
                                        <p class="fw-medium lh-24 mb-0">Доставка:</p>
                                        <span class="text-caption-01 text-end">
                                            {{ $b2bClient->default_delivery_address ? trim($b2bClient->default_delivery_city.', '.$b2bClient->default_delivery_address, ', ') : 'B2B-доставка или самовывоз со склада' }}<br>
                                            <span class="cl-text-3">стоимость и срок согласует менеджер</span>
                                        </span>
                                    </div>
                                    <fieldset class="tf-field d-grid mb-16">
                                        <label class="tf-lable fw-medium" for="cart-b2b-note">Примечание к заказу</label>
                                        <textarea id="cart-b2b-note" rows="3" maxlength="1000"
                                            placeholder="Адрес и время приёмки, объект, контакт на месте, разгрузка, номер вашей заявки…">{{ session('cart_note') }}</textarea>
                                        <span class="text-caption-01 cl-text-3 mt-4" id="cart-b2b-note-state">Попадёт в заказ и увидит менеджер.</span>
                                    </fieldset>
                                    <div class="b2b-cart-process mb-20">
                                        <strong>Как пройдёт заказ</strong>
                                        <ol>
                                            <li>Сразу проверяем наличие и резервируем товар на складе.</li>
                                            <li>Менеджер подтверждает позиции — вы согласуете заказ в кабинете.</li>
                                            <li>Счёт сразу появляется в B2B-кабинете и приходит на почту.</li>
                                        </ol>
                                    </div>
                                @else
                                @php
                                    $threshold = (float) config('shop.free_delivery_threshold', 400);
                                @endphp
                                <div class="d-flex justify-content-between align-items-center mb-20">
                                    <p class="fw-medium lh-24 mb-0">Доставка:</p>
                                    @if ($subtotal >= $threshold)
                                        <span class="text-caption-01 text-end" id="cart-delivery-estimate" data-threshold="{{ $threshold }}">
                                            <strong class="text-primary">В пределах МКАД-2 — бесплатно</strong><br>
                                            по городам графика — согласно тарифу
                                        </span>
                                    @else
                                        <span class="text-caption-01 cl-text-3 text-end" id="cart-delivery-estimate" data-threshold="{{ $threshold }}">от 10 BYN, точная цена при оформлении</span>
                                    @endif
                                </div>

                                <x-order-confirmation-notice class="mb-20" />
                                @endif

                                {{-- Согласие с условиями --}}
                                <div class="checkbox-wrap mb-16">
                                    <input class="tf-check style-2" type="checkbox" id="cart-agree">
                                    <label for="cart-agree">
                                        Я согласен с
                                        <a href="/privacy" class="text-decoration-underline" target="_blank">
                                            условиями обработки данных
                                        </a>
                                    </label>
                                </div>

                                {{-- Кнопка: изначально disabled, активируется после чекбокса --}}
                                <button type="button" id="checkout-btn"
                                    class="tf-btn animate-btn w-100 mb-12"
                                    disabled
                                    style="opacity:0.45;cursor:not-allowed;">
                                    Оформить заказ
                                </button>

                            </div>
                        </div>
                    </div>

                </div>

                <aside class="cart-mobile-total" aria-label="Итог выбранных товаров">
                    <div><small><span data-selected-positions>{{ $cartSelection['positions'] }}</span> поз. · <span data-selected-count>{{ $cartSelection['count'] }}</span> шт.</small><strong data-selected-total>{{ number_format($cartSelection['total'], 2, ',', ' ') }} BYN</strong></div>
                    <button type="button" id="cart-mobile-checkout">К оформлению <span aria-hidden="true">→</span></button>
                </aside>
            @else
                <div class="text-center py-80">
                    <i class="icon icon-Handbag fs-60 cl-text-3 mb-20"
                        style="font-size:60px;display:block;"></i>
                    <h4 class="mb-12">Корзина пуста</h4>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Добавьте товары из каталога отопительного оборудования
                    </p>
                    <a href="{{ $b2bClient ? route('b2b.catalog') : '/catalog' }}" class="tf-btn animate-btn">Перейти в каталог</a>
                </div>
            @endif

        </div>
    </section>

</main>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/cart-workspace.js') }}?v=20261117" defer></script>
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/cart-workspace.css') }}?v=20261117">
<style>
.b2b-cart-title { margin: 22px 0 0; font-size: 28px; letter-spacing: -.02em; }
.b2b-cart-title span { display: block; margin-top: 4px; color: #78889b; font-size: 14px; font-weight: 500; letter-spacing: 0; }
.b2b-cart-process { padding: 14px 16px; border: 1px solid #d8eee6; border-left: 3px solid #15946e; border-radius: 12px; background: #f4fbf8; font-size: 13px; }
.b2b-cart-process strong { display: block; margin-bottom: 6px; color: #142338; }
.b2b-cart-process ol { margin: 0; padding-left: 18px; color: #4a5a6c; }
.b2b-cart-process li + li { margin-top: 3px; }
#cart-b2b-note { width: 100%; padding: 10px 12px; border: 1px solid #e4e4e4; border-radius: 10px; resize: vertical; }
</style>
@endpush
