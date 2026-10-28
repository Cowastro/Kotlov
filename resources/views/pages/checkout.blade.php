@extends('layouts.amerce')

@push('styles')
<style>
.payment-info-accordion .accordion-button::after { margin-left: auto; flex-shrink: 0; }
.payment-info-accordion .accordion-button:not(.collapsed) { color: inherit; }
.payment-info-list { padding-left: 20px; margin: 0; }
.payment-info-list li { margin-bottom: 6px; line-height: 1.6; }
.payment-info-list--disc { list-style-type: disc; }
.tf-field input.is-invalid,
.tf-field select.is-invalid { border-color: #dc3545 !important; }
</style>
@endpush

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
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

                {{-- ===== Левая колонка: форма ===== --}}
                <div class="col-lg-7">
                    <div class="tf-page-checkout mb-lg-0">

                        {{-- Подсказка гостю --}}
                        @guest
                            <div class="wrap-quick-login mb-20">
                                <p class="title cl-text-2 mb-8">
                                    Уже есть аккаунт?
                                    <a href="#sign" data-bs-toggle="modal"
                                        class="tf-btn-line-2 style-primary fw-semibold">Войти</a>
                                </p>
                                <p class="text-caption-01 cl-text-3">
                                    <a href="#register" data-bs-toggle="modal"
                                        class="text-decoration-underline">Зарегистрируйтесь</a>,
                                    чтобы видеть историю заказов в личном кабинете.
                                </p>
                            </div>
                        @endguest

                        <form action="{{ route('checkout.store') }}" method="POST"
                            class="tf-checkout-cart-main">
                            @csrf
                            <input type="hidden" name="order_token" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <x-form-protection />

                            {{-- 1. Контактная информация --}}
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Контактная информация</div>
                                <div class="form-content">

                                    <div class="tf-grid-layout sm-col-2">
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Имя и фамилия <span class="text-primary">*</span>
                                            </label>
                                            <input type="text" name="customer_name"
                                                class="{{ $errors->has('customer_name') ? 'is-invalid' : '' }}"
                                                value="{{ old('customer_name', $user->name ?? '') }}"
                                                placeholder="Иванов Иван" required>
                                            @error('customer_name') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Телефон <span class="text-primary">*</span>
                                            </label>
                                            <input type="tel" name="customer_phone"
                                                class="{{ $errors->has('customer_phone') ? 'is-invalid' : '' }}"
                                                value="{{ old('customer_phone', $user->phone ?? '') }}"
                                                placeholder="+375 (29) 000-00-00" required>
                                            @error('customer_phone') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                    </div>

                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">Email для связи</label>
                                        @if ($user)
                                            <input type="email" value="{{ $user->email }}" readonly>
                                            <input type="hidden" name="customer_email" value="{{ $user->email }}">
                                            <div class="text-caption-01 cl-text-3 mt-4">Подтверждение заказа придёт на email вашего аккаунта: {{ $user->email }}.</div>
                                        @else
                                            <input type="email" name="customer_email"
                                                value="{{ old('customer_email') }}"
                                                placeholder="example@mail.by">
                                            <div class="text-caption-01 cl-text-3 mt-4">Письмо-подтверждение получают только зарегистрированные покупатели. Для гостевого заказа менеджер свяжется по телефону.</div>
                                        @endif
                                    </fieldset>

                                </div>
                            </div>

                            {{-- 2. Доставка (из config/shop.php) --}}
                            @php
                                $checkoutDelivery = collect(config('shop.checkout_delivery_methods', []))
                                    ->mapWithKeys(fn($key) => [$key => config("shop.delivery_methods.$key")])
                                    ->filter();
                            @endphp
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Доставка</div>
                                <div class="form-content">

                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">
                                            Способ доставки <span class="text-primary">*</span>
                                        </label>
                                        <div class="tf-select">
                                            <select name="delivery_type" id="delivery-type" required>
                                                @foreach ($checkoutDelivery as $key => $method)
                                                    @php
                                                        $methodPrice = \App\Http\Controllers\CheckoutController::calcDelivery($key, (float) $subtotal);
                                                    @endphp
                                                    <option value="{{ $key }}"
                                                        {{ old('delivery_type', 'courier') === $key ? 'selected' : '' }}>
                                                        {{ $method['name'] }}
                                                        @if ($methodPrice === 0.0)
                                                            — Бесплатно
                                                        @elseif ($methodPrice !== null)
                                                            — {{ number_format($methodPrice, 2, '.', ' ') }} BYN
                                                        @else
                                                            — стоимость уточняется
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </fieldset>

                                    @include('partials.own-transport-schedule', ['collapsible' => true])

                                    <div id="delivery-address-fields">
                                        <div class="tf-grid-layout sm-col-2">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">
                                                    Область / Регион <span class="text-primary delivery-required-mark">*</span>
                                                </label>
                                                <div class="tf-select">
                                                    <select name="delivery_region" id="delivery-region"
                                                        class="{{ $errors->has('delivery_region') ? 'is-invalid' : '' }}" required>
                                                        <option value="">— выберите —</option>
                                                        @foreach(['Минск','Минская область','Брестская область','Гродненская область','Витебская область','Могилёвская область','Гомельская область'] as $region)
                                                            <option value="{{ $region }}"
                                                                {{ old('delivery_region', session('cart_delivery_region')) === $region ? 'selected' : '' }}>
                                                                {{ $region }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                @error('delivery_region') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">
                                                    Город <span class="text-primary delivery-required-mark">*</span>
                                                </label>
                                                <input type="text" name="delivery_city" id="delivery-city"
                                                    class="{{ $errors->has('delivery_city') ? 'is-invalid' : '' }}"
                                                    value="{{ old('delivery_city', session('cart_delivery_city')) }}"
                                                    placeholder="Минск" required>
                                                @error('delivery_city') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                            </fieldset>
                                        </div>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Адрес доставки <span class="text-primary delivery-required-mark">*</span>
                                            </label>
                                            <input type="text" name="delivery_address" id="delivery-address"
                                                class="{{ $errors->has('delivery_address') ? 'is-invalid' : '' }}"
                                                value="{{ old('delivery_address') }}"
                                                placeholder="ул. Ленина, д. 1, кв. 5" required>
                                            @error('delivery_address') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                    </div>

                                </div>
                            </div>

                            {{-- 3. Оплата (из config/shop.php) --}}
                            @php
                                $checkoutPayment = collect(config('shop.checkout_payment_methods', []))
                                    ->mapWithKeys(fn($key) => [$key => config("shop.payment_methods.$key")])
                                    ->filter();
                                $firstPayment = $checkoutPayment->keys()->first();
                            @endphp
                            <div class="box-ip-payment">
                                <h5 class="title">Способ оплаты</h5>
                                <div class="payment-method-box" id="payment-method-box">

                                    @foreach ($checkoutPayment as $key => $method)
                                    <div class="payment_accordion">
                                        <label for="pay-{{ $key }}" class="payment_check checkbox-wrap">
                                            <input type="radio" name="payment_type"
                                                class="tf-check-rounded style-2"
                                                id="pay-{{ $key }}"
                                                value="{{ $key }}"
                                                {{ old('payment_type', $selectedPayment ?? $firstPayment) === $key ? 'checked' : '' }}>
                                            <span class="pay-title fw-medium lh-24">
                                                {{ $method['name'] }}
                                                @if (!empty($method['desc']))
                                                    <span class="text-caption-01 cl-text-2 d-block fw-normal">
                                                        {{ $method['desc'] }}
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                        @if ($key === 'installment_6')
                                            <div class="text-caption-01 ms-4 mt-1">
                                                <span class="cl-text-2 d-block">Онлайн-скидка −3% при рассрочке не применяется.</span>
                                                <a href="{{ $method['video_url'] }}" target="_blank" rel="noopener noreferrer">
                                                    Смотреть видеоинструкцию
                                                </a>
                                                <span class="cl-text-2"> · </span>
                                                <a href="{{ route('installment.info') }}">
                                                    Как оформить рассрочку
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    @endforeach

                                </div>
                            </div>

                            {{-- 3а. Информационные блоки об оплате --}}
                            <div class="box-ip-checkout" id="payment-info-accordion">
                                <div class="h5 title">Как оплатить заказ</div>

                                @php
                                $paymentInfoSections = [
                                    'order-process' => [
                                        'title' => 'Как оформить заказ через сайт',
                                        'body'  => <<<'HTML'
                                            <ol class="payment-info-list">
                                                <li>Добавьте товар в корзину.</li>
                                                <li>Нажмите «Оформить заказ».</li>
                                                <li>Заполните информацию о доставке и оплате.</li>
                                                <li>Нажмите «Подтвердить заказ».</li>
                                            </ol>
                                            <p class="mt-12 cl-text-2">После оформления заказа наш менеджер свяжется с вами для уточнения и подтверждения условий заказа.</p>
                                        HTML,
                                    ],
                                ];
                                @endphp

                                <div class="accordion payment-info-accordion" id="paymentInfoAccordion">
                                    @foreach ($paymentInfoSections as $id => $section)
                                    <div class="accordion-item border-0 border-bottom">
                                        <h6 class="accordion-header m-0">
                                            <button class="accordion-button collapsed px-0 py-12 fw-medium bg-transparent shadow-none"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#pi-{{ $id }}"
                                                aria-expanded="false"
                                                aria-controls="pi-{{ $id }}">
                                                {{ $section['title'] }}
                                            </button>
                                        </h6>
                                        <div id="pi-{{ $id }}"
                                            class="accordion-collapse collapse"
                                            data-bs-parent="#paymentInfoAccordion">
                                            <div class="accordion-body px-0 pb-16 pt-4 text-body-1 cl-text-2">
                                                {!! $section['body'] !!}
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                            </div>

                            {{-- 3б. Реквизиты для счёта (только при invoice) --}}
                            @if($checkoutPayment->has('invoice'))
                            <div class="box-ip-checkout" id="invoice-fields" style="display:none;">
                                <div class="h5 title">Реквизиты организации</div>
                                <div class="form-content">
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">
                                            УНП <span class="text-primary">*</span>
                                        </label>
                                        <div class="d-flex gap-8 align-items-stretch">
                                            <input type="text" name="company_unp" id="company-unp"
                                                value="{{ old('company_unp') }}"
                                                inputmode="numeric" maxlength="9"
                                                placeholder="100000000" style="flex:1;">
                                            <button type="button" class="tf-btn btn-outline" id="company-lookup-button"
                                                style="width:auto;min-width:150px;padding:0 18px;">
                                                Найти по УНП
                                            </button>
                                        </div>
                                        <div class="text-caption-01 cl-text-2 mt-4" id="company-lookup-status">
                                            Заполним название и юридический адрес по данным реестра МНС.
                                        </div>
                                        @error('company_unp') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                    </fieldset>
                                    <div class="tf-grid-layout sm-col-2">
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Организация / ИП <span class="text-primary">*</span>
                                            </label>
                                            <input type="text" name="company_name" id="company-name"
                                                value="{{ old('company_name') }}"
                                                placeholder="ООО Теплосервис">
                                            @error('company_name') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium">
                                                Email для счёта <span class="text-primary">*</span>
                                            </label>
                                            <input type="email" name="company_email" id="company-email"
                                                value="{{ old('company_email', old('customer_email', $user->email ?? '')) }}"
                                                placeholder="buh@company.by">
                                            @error('company_email') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                    </div>
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium">
                                            Юридический адрес <span class="text-primary">*</span>
                                        </label>
                                        <input type="text" name="company_address" id="company-address"
                                            value="{{ old('company_address') }}"
                                            placeholder="г. Минск, ул. Ленина, 1">
                                        @error('company_address') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                    </fieldset>
                                    <div class="p-12" style="border-radius:10px;background:#fff7e6;color:#8a5a00;">
                                        Сначала менеджер проверит наличие и срок поставки всех позиций. После проверки он отправит готовый счёт на указанный email.
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- 4. Комментарий — подтягиваем cart_note из session --}}
                            <div class="box-ip-checkout">
                                <div class="h5 title">Комментарий к заказу</div>
                                <div class="form-content">
                                    <fieldset class="tf-field d-grid">
                                        <textarea name="comment" rows="3"
                                            placeholder="Уточните удобное время доставки, этаж, подъезд...">{{ old('comment', session('cart_note')) }}</textarea>
                                    </fieldset>
                                </div>
                            </div>

                            <x-order-confirmation-notice class="mt-20" />

                            <button type="submit" class="tf-btn animate-btn w-100 mt-20">
                                Оформить заказ
                            </button>

                        </form>
                    </div>
                </div>

                {{-- ===== Правая колонка: итого ===== --}}
                <div class="col-lg-5">
                    <div class="fl-sidebar-cart type-2 mt-lg-0 sticky-top">
                        <div class="box-your-order">
                            <h5 class="title">Ваш заказ</h5>

                            <ul class="list-order-product">
                                @foreach ($cart as $item)
                                    <li class="order-item fw-medium">
                                        <a href="/{{ $item['category_slug'] }}/{{ $item['slug'] }}" class="img-prd">
                                            <img loading="lazy" width="100" height="100"
                                                src="{{ $item['image'] ?? asset('img/products/product-placeholder.jpg') }}"
                                                alt="{{ $item['name'] }}"
                                                onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
                                        </a>
                                        <div class="infor-prd">
                                            <a href="/{{ $item['category_slug'] }}/{{ $item['slug'] }}"
                                                class="prd_name fw-medium lh-24 link link-underline">
                                                {{ $item['name'] }}
                                            </a>
                                            <div class="text-caption-01 cl-text-2">
                                                {{ $item['quantity'] }} шт.
                                                @if ($item['sku'])
                                                    · {{ $item['sku'] }}
                                                @endif
                                            </div>
                                            @if (($item['pricing_type'] ?? null) === 'b2b')
                                                <div class="text-caption-01 cl-text-2">
                                                    Партнёрская цена
                                                </div>
                                            @endif
                                            @if ($item['requires_availability_confirmation'] ?? false)
                                                <div class="text-caption-01" style="color:#b45309;">
                                                    Наличие подтвердит менеджер после оформления
                                                </div>
                                            @endif
                                        </div>
                                        <div class="quantity-price text-primary fw-semibold">
                                            {{ number_format($item['price'] * $item['quantity'], 2, '.', ' ') }} BYN
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Промокод --}}
                            @if ($coupon['valid'] || $coupon['bundle']['active'])
                                <div class="mt-16 mb-8" id="checkout-coupon-summary"
                                    @if ($onlineOrderDiscountExcludedByPayment && ! $coupon['bundle']['active']) style="display:none" @endif>
                                    <p class="text-caption-01 cl-text-2">
                                        @if ($coupon['bundle']['active'])
                                            Комплектная скидка на дымоход −{{ number_format($coupon['bundle']['percent'], 0) }}% применена автоматически.
                                        @else
                                            Онлайн-скидка применена автоматически.
                                        @endif
                                    </p>
                                </div>
                            @endif

                            {{-- Итоги --}}
                            @php
                                $selectedDelivery = old('delivery_type', 'courier');
                                $deliveryCost     = \App\Http\Controllers\CheckoutController::calcDelivery($selectedDelivery, $subtotal);
                                $effectiveDiscount = ($onlineOrderDiscountExcludedByPayment ? 0 : $coupon['coupon_discount']) + $coupon['bundle_discount'];
                                $total            = $subtotal - $effectiveDiscount + ($deliveryCost ?? 0);
                            @endphp
                            <ul class="list-total mt-20">
                                <li class="total-item lh-24 fw-medium">
                                    <span>Товаров ({{ array_sum(array_column($cart, 'quantity')) }} шт.):</span>
                                    <span>{{ number_format($subtotal, 2, '.', ' ') }} BYN</span>
                                </li>
                                @if ($coupon['coupon_discount'] > 0)
                                    <li class="total-item lh-24 fw-medium" id="checkout-discount-row"
                                        @if ($onlineOrderDiscountExcludedByPayment) style="display:none" @endif>
                                        <span>Онлайн-скидка −3%:</span>
                                        <span>−{{ number_format($coupon['coupon_discount'], 2, '.', ' ') }} BYN</span>
                                    </li>
                                @endif
                                @if ($coupon['bundle_discount'] > 0)
                                    <li class="total-item lh-24 fw-medium">
                                        <span>{{ $coupon['bundle']['mode'] === 'self_selection' ? 'Самостоятельный подбор' : 'Дымоход с оборудованием' }} −{{ number_format($coupon['bundle']['percent'], 0) }}%:</span>
                                        <span>−{{ number_format($coupon['bundle_discount'], 2, '.', ' ') }} BYN</span>
                                    </li>
                                @endif
                                <li class="total-item lh-24 fw-medium">
                                    <span>Доставка:</span>
                                    <span id="checkout-delivery-cost">
                                        @if ($deliveryCost === null)
                                            <span class="cl-text-2">уточняется</span>
                                        @elseif ($deliveryCost === 0.0)
                                            <span class="text-primary fw-semibold">Бесплатно</span>
                                        @else
                                            <span class="fw-semibold">{{ number_format($deliveryCost, 2, '.', ' ') }} BYN</span>
                                        @endif
                                    </span>
                                </li>
                            </ul>

                            <div class="last-total h5 fw-medium">
                                <span>Итого:</span>
                                <span class="text-primary" id="checkout-total">
                                    {{ number_format($total, 2, '.', ' ') }} BYN
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
(function () {
    // ── Конфиг доставки из PHP (price: null = уточняется, 0 = бесплатно) ──
    var deliveryMethods = @json(collect(config('shop.checkout_delivery_methods', []))->mapWithKeys(fn($k) => [$k => config("shop.delivery_methods.$k")])->filter());
    var subtotal = {{ (float) $subtotal }};
    var couponDiscount = {{ (float) $coupon['coupon_discount'] }};
    var bundleDiscount = {{ (float) $coupon['bundle_discount'] }};
    var onlineOrderDiscountCoupon = @json($onlineOrderDiscountCoupon);

    // ── Показ/скрытие полей адреса при самовывозе ──
    var deliverySelect   = document.getElementById('delivery-type');
    var ownTransportSchedule = document.getElementById('own-transport-schedule');
    var addressFields    = document.getElementById('delivery-address-fields');
    var addressInputs    = ['delivery-region', 'delivery-city', 'delivery-address'].map(function(id) { return document.getElementById(id); }).filter(Boolean);
    var requiredMarks    = document.querySelectorAll('.delivery-required-mark');
    if (deliverySelect && addressFields) {
        function toggleAddress() {
            var isPickup = deliverySelect.value === 'pickup';
            addressFields.style.display = isPickup ? 'none' : '';
            addressInputs.forEach(function(el) { el.required = !isPickup; });
            requiredMarks.forEach(function(el) { el.style.display = isPickup ? 'none' : ''; });
            if (ownTransportSchedule && deliverySelect.value === 'own_transport') {
                ownTransportSchedule.open = true;
            }
        }
        deliverySelect.addEventListener('change', toggleAddress);
        toggleAddress();
    }

    // ── Динамический пересчёт стоимости доставки ──
    var deliveryCostEl = document.getElementById('checkout-delivery-cost');
    var totalEl        = document.getElementById('checkout-total');
    var couponSummaryEl = document.getElementById('checkout-coupon-summary');
    var discountRowEl   = document.getElementById('checkout-discount-row');

    function installmentSelected() {
        var checked = document.querySelector('input[name="payment_type"]:checked');
        return checked && checked.value === 'installment_6';
    }

    function effectiveDiscount() {
        return (onlineOrderDiscountCoupon && installmentSelected() ? 0 : couponDiscount) + bundleDiscount;
    }

    function calcDelivery(key) {
        var method = deliveryMethods[key];
        if (!method) return null;
        var price    = method.price;     // number | null
        var freeFrom = method.free_from; // number | null
        if (Array.isArray(method.price_tiers) && method.price_tiers.length) {
            var tiers = method.price_tiers.slice().sort(function(a, b) {
                return Number(b.from || 0) - Number(a.from || 0);
            });
            for (var i = 0; i < tiers.length; i++) {
                if (subtotal >= Number(tiers[i].from || 0)) {
                    return tiers[i].price === null ? null : Number(tiers[i].price);
                }
            }
        }
        if (price === null) return null;           // уточняется
        if (price === 0)    return 0;              // самовывоз
        if (freeFrom !== null && subtotal >= freeFrom) return 0;
        return price;
    }

    function fmtByn(val) {
        return val.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' BYN';
    }

    function updateTotals() {
        if (!deliveryCostEl || !totalEl || !deliverySelect) return;

        var cost  = calcDelivery(deliverySelect.value);
        var total = subtotal - effectiveDiscount() + (cost !== null ? cost : 0);

        if (cost === null) {
            deliveryCostEl.innerHTML = '<span class="cl-text-2">уточняется</span>';
        } else if (cost === 0) {
            deliveryCostEl.innerHTML = '<span class="text-primary fw-semibold">Бесплатно</span>';
        } else {
            deliveryCostEl.innerHTML = '<span class="fw-semibold">' + fmtByn(cost) + '</span>';
        }

        totalEl.textContent = fmtByn(total);
    }

    if (deliverySelect) {
        deliverySelect.addEventListener('change', updateTotals);
        updateTotals();
    }

    // ── Показ/скрытие реквизитов при оплате по счёту ──
    var paymentRadios = document.querySelectorAll('input[name="payment_type"]');
    var invoiceFields = document.getElementById('invoice-fields');
    function updatePaymentDiscount() {
        var hideOnlineDiscount = onlineOrderDiscountCoupon && installmentSelected();
        if (couponSummaryEl) couponSummaryEl.style.display = hideOnlineDiscount && bundleDiscount <= 0 ? 'none' : '';
        if (discountRowEl) discountRowEl.style.display = hideOnlineDiscount ? 'none' : '';
        updateTotals();
    }
    paymentRadios.forEach(function (r) { r.addEventListener('change', updatePaymentDiscount); });
    updatePaymentDiscount();

    if (paymentRadios.length && invoiceFields) {
        function toggleInvoice() {
            var checked = document.querySelector('input[name="payment_type"]:checked');
            var isInvoice = checked && checked.value === 'invoice';
            invoiceFields.style.display = isInvoice ? '' : 'none';
            ['company-unp', 'company-name', 'company-address', 'company-email'].forEach(function (id) {
                var input = document.getElementById(id);
                if (input) input.required = Boolean(isInvoice);
            });
        }
        paymentRadios.forEach(function (r) { r.addEventListener('change', toggleInvoice); });
        toggleInvoice();
    }

    // ── Реквизиты организации из реестра МНС ──
    var companyUnp = document.getElementById('company-unp');
    var companyName = document.getElementById('company-name');
    var companyAddress = document.getElementById('company-address');
    var companyLookupButton = document.getElementById('company-lookup-button');
    var companyLookupStatus = document.getElementById('company-lookup-status');
    var companyLookupUrl = @json(route('checkout.company-lookup'));
    var companyLookupInProgress = false;

    async function lookupCompany() {
        if (!companyUnp || !companyLookupButton || !companyLookupStatus || companyLookupInProgress) return;

        var unp = companyUnp.value.replace(/\D/g, '');
        companyUnp.value = unp;
        if (!/^\d{9}$/.test(unp)) {
            companyLookupStatus.textContent = 'Введите УНП из 9 цифр.';
            companyLookupStatus.style.color = '#b42318';
            return;
        }

        companyLookupInProgress = true;
        companyLookupButton.disabled = true;
        companyLookupButton.textContent = 'Ищем…';
        companyLookupStatus.textContent = 'Проверяем данные в реестре МНС…';
        companyLookupStatus.style.color = '';

        try {
            var response = await fetch(companyLookupUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ unp: unp })
            });
            var payload = await response.json();
            if (!response.ok || !payload.data) {
                throw new Error(payload.message || 'Не удалось получить реквизиты.');
            }

            if (companyName) companyName.value = payload.data.legal_name || payload.data.short_name || '';
            if (companyAddress) companyAddress.value = payload.data.address || '';
            companyLookupStatus.textContent = 'Реквизиты заполнены · '
                + (payload.data.source || 'реестр МНС')
                + (payload.data.status ? ' · ' + payload.data.status : '');
            companyLookupStatus.style.color = '#16835f';
        } catch (error) {
            companyLookupStatus.textContent = error.message || 'Не удалось получить реквизиты. Заполните их вручную.';
            companyLookupStatus.style.color = '#b42318';
        } finally {
            companyLookupInProgress = false;
            companyLookupButton.disabled = false;
            companyLookupButton.textContent = 'Найти по УНП';
        }
    }

    if (companyLookupButton) companyLookupButton.addEventListener('click', lookupCompany);
    if (companyUnp) {
        companyUnp.addEventListener('input', function () {
            companyUnp.value = companyUnp.value.replace(/\D/g, '').slice(0, 9);
        });
        companyUnp.addEventListener('blur', function () {
            if (/^\d{9}$/.test(companyUnp.value)) lookupCompany();
        });
    }
})();
</script>
@endpush
