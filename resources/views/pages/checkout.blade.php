@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('cart') }}" class="text-caption-01 cl-text-3 link">Корзина</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Оформление заказа</p>
                </div>
                <h3>Оформление заказа</h3>
            </div>
        </div>
    </section>

    <section class="section-checkout flat-spacing-2">
        <div class="container">

            @if ($errors->any())
                <div class="alert alert-danger mb-20">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="row">

                {{-- ===== Форма ===== --}}
                <div class="col-lg-7">
                    <div class="tf-page-checkout mb-lg-0">

                        {{-- Быстрый вход для гостей --}}
                        @guest
                            <div class="wrap-quick-login mb-24">
                                <p class="title cl-text-2">
                                    Уже есть аккаунт?
                                    <a href="#sign" data-bs-toggle="modal"
                                        class="tf-btn-line-2 style-primary fw-semibold">
                                        Войти
                                    </a>
                                </p>
                            </div>
                        @endguest

                        <form action="{{ route('checkout.store') }}" method="POST"
                            class="tf-checkout-cart-main">
                            @csrf

                            {{-- Контактная информация --}}
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Контактная информация</div>
                                <div class="form-content">
                                    <div class="tf-grid-layout sm-col-2">
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Имя и фамилия <span class="text-primary">*</span>
                                            </label>
                                            <input type="text" name="customer_name"
                                                value="{{ old('customer_name', $user->name ?? '') }}"
                                                placeholder="Иванов Иван" required>
                                        </fieldset>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Телефон <span class="text-primary">*</span>
                                            </label>
                                            <input type="tel" name="customer_phone"
                                                value="{{ old('customer_phone', $user->phone ?? '') }}"
                                                placeholder="+375 (29) 000-00-00" required>
                                        </fieldset>
                                    </div>
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">Email</label>
                                        <input type="email" name="customer_email"
                                            value="{{ old('customer_email', $user->email ?? '') }}"
                                            placeholder="example@mail.by">
                                    </fieldset>
                                </div>
                            </div>

                            {{-- Доставка --}}
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Доставка</div>
                                <div class="form-content">

                                    {{-- Способ доставки --}}
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">
                                            Способ доставки <span class="text-primary">*</span>
                                        </label>
                                        <div class="tf-select">
                                            <select name="delivery_type" id="delivery-type" required>
                                                <option value="pickup" {{ old('delivery_type') === 'pickup' ? 'selected' : '' }}>
                                                    Самовывоз
                                                </option>
                                                <option value="courier" {{ old('delivery_type', 'courier') === 'courier' ? 'selected' : '' }}>
                                                    Курьером по Минску
                                                </option>
                                                <option value="transport" {{ old('delivery_type') === 'transport' ? 'selected' : '' }}>
                                                    Транспортной компанией
                                                </option>
                                            </select>
                                        </div>
                                    </fieldset>

                                    {{-- Поля адреса (скрываем при самовывозе) --}}
                                    <div id="delivery-address-fields">
                                        <div class="tf-grid-layout sm-col-2">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Область / Регион</label>
                                                <div class="tf-select">
                                                    <select name="delivery_region">
                                                        <option value="">— выберите —</option>
                                                        <option value="Минск" {{ old('delivery_region') === 'Минск' ? 'selected' : '' }}>Минск</option>
                                                        <option value="Минская область" {{ old('delivery_region') === 'Минская область' ? 'selected' : '' }}>Минская область</option>
                                                        <option value="Брестская область" {{ old('delivery_region') === 'Брестская область' ? 'selected' : '' }}>Брестская область</option>
                                                        <option value="Гродненская область" {{ old('delivery_region') === 'Гродненская область' ? 'selected' : '' }}>Гродненская область</option>
                                                        <option value="Витебская область" {{ old('delivery_region') === 'Витебская область' ? 'selected' : '' }}>Витебская область</option>
                                                        <option value="Могилёвская область" {{ old('delivery_region') === 'Могилёвская область' ? 'selected' : '' }}>Могилёвская область</option>
                                                        <option value="Гомельская область" {{ old('delivery_region') === 'Гомельская область' ? 'selected' : '' }}>Гомельская область</option>
                                                    </select>
                                                </div>
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Город</label>
                                                <input type="text" name="delivery_city"
                                                    value="{{ old('delivery_city') }}"
                                                    placeholder="Минск">
                                            </fieldset>
                                        </div>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">Адрес доставки</label>
                                            <input type="text" name="delivery_address"
                                                value="{{ old('delivery_address') }}"
                                                placeholder="ул. Ленина, д. 1, кв. 5">
                                        </fieldset>
                                    </div>

                                </div>
                            </div>

                            {{-- Оплата --}}
                            <div class="box-ip-payment">
                                <h5 class="title">Способ оплаты</h5>
                                <div class="payment-method-box" id="payment-method-box">

                                    <div class="payment_accordion">
                                        <label for="pay-cash" class="payment_check checkbox-wrap">
                                            <input type="radio" name="payment_type"
                                                class="tf-check-rounded style-2"
                                                id="pay-cash" value="cash"
                                                {{ old('payment_type', 'cash') === 'cash' ? 'checked' : '' }}>
                                            <span class="pay-title fw-medium">Наличными при получении</span>
                                        </label>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="pay-card" class="payment_check checkbox-wrap">
                                            <input type="radio" name="payment_type"
                                                class="tf-check-rounded style-2"
                                                id="pay-card" value="card"
                                                {{ old('payment_type') === 'card' ? 'checked' : '' }}>
                                            <span class="pay-title fw-medium">Картой при получении</span>
                                        </label>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="pay-invoice" class="payment_check checkbox-wrap">
                                            <input type="radio" name="payment_type"
                                                class="tf-check-rounded style-2"
                                                id="pay-invoice" value="invoice"
                                                {{ old('payment_type') === 'invoice' ? 'checked' : '' }}>
                                            <span class="pay-title fw-medium">По счёту (для организаций)</span>
                                        </label>
                                    </div>

                                </div>
                            </div>

                            {{-- Комментарий --}}
                            <div class="box-ip-checkout">
                                <div class="form-content">
                                    <fieldset class="tf-field d-grid">
                                        <label class="tf-lable fw-medium">Комментарий к заказу</label>
                                        <textarea name="comment" rows="3"
                                            placeholder="Уточните удобное время доставки, этаж и т.д.">{{ old('comment') }}</textarea>
                                    </fieldset>
                                </div>
                            </div>

                            <button type="submit" class="tf-btn animate-btn w-100 mt-20">
                                Оформить заказ
                            </button>

                        </form>
                    </div>
                </div>

                {{-- ===== Список товаров ===== --}}
                <div class="col-lg-5">
                    <div class="fl-sidebar-cart type-2 mt-lg-0 sticky-top">
                        <div class="box-your-order">
                            <h5 class="title">Ваш заказ</h5>
                            <ul class="list-order-product">
                                @foreach ($cart as $item)
                                    <li class="order-item fw-medium">
                                        <a href="/{{ $item['category_slug'] }}/{{ $item['slug'] }}" class="img-prd">
                                            <img loading="lazy" width="80" height="80"
                                                src="{{ $item['image'] ? asset($item['image']) : asset('img/products/product-placeholder.jpg') }}"
                                                alt="{{ $item['name'] }}">
                                        </a>
                                        <div class="infor-prd">
                                            <a href="/{{ $item['category_slug'] }}/{{ $item['slug'] }}"
                                                class="prd_name fw-medium lh-24 link link-underline">
                                                {{ $item['name'] }}
                                            </a>
                                            <div class="text-caption-01 cl-text-2">
                                                {{ $item['quantity'] }} шт.
                                            </div>
                                        </div>
                                        <div class="quantity-price text-primary fw-semibold">
                                            {{ number_format($item['price'] * $item['quantity'], 2, '.', ' ') }} BYN
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="br-line my-20"></div>

                            <div class="d-flex justify-content-between align-items-center mb-8">
                                <p class="fw-medium mb-0">Сумма товаров:</p>
                                <span class="fw-semibold text-primary">
                                    {{ number_format($subtotal, 2, '.', ' ') }} BYN
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-8">
                                <p class="fw-medium mb-0">Доставка:</p>
                                <span class="cl-text-2">уточняется</span>
                            </div>

                            <div class="br-line my-20"></div>

                            <div class="d-flex justify-content-between align-items-center">
                                <p class="fw-semibold mb-0 h6">Итого:</p>
                                <span class="fw-semibold text-primary fs-18">
                                    {{ number_format($subtotal, 2, '.', ' ') }} BYN
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

</main>
@endsection

@push('scripts')
<script>
// Скрываем поля адреса при выборе самовывоза
(function () {
    var select = document.getElementById('delivery-type');
    var fields = document.getElementById('delivery-address-fields');
    if (!select || !fields) return;

    function toggle() {
        fields.style.display = select.value === 'pickup' ? 'none' : '';
    }
    select.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush
