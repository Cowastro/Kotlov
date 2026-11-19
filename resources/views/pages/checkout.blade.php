@extends('layouts.amerce')

@push('styles')
<style>
.section-checkout .payment_check input[type="radio"] { flex: 0 0 22px; min-width: 22px; height: 22px; }
.section-checkout .payment_check .pay-title { min-width: 0; }
.section-checkout .payment_check { align-items: flex-start; }
.checkout-delivery-options { display: grid; gap: 8px; }
.checkout-delivery-option {
    display: grid; grid-template-columns: 20px 24px minmax(0, 1fr) auto; gap: 10px;
    align-items: start; padding: 12px; border: 1px solid #e4e4e4;
    border-radius: 10px; cursor: pointer;
}
.checkout-delivery-option[hidden] { display: none; }
.checkout-delivery-option:has(input:checked) { border-color: #222; background: #f7f6f5; }
.checkout-delivery-option:has(input:focus-visible) { outline: 2px solid #222; outline-offset: 2px; }
.checkout-delivery-option input[type="radio"] { width: 19px; height: 19px; margin: 2px 0 0; accent-color: #222; }
.checkout-delivery-option__icon { width: 22px; height: 22px; color: #505050; }
.checkout-delivery-option__price { text-align: right; white-space: nowrap; }
@media (max-width: 479px) {
    .checkout-delivery-option { grid-template-columns: 20px 22px minmax(0, 1fr); }
    .checkout-delivery-option__price { grid-column: 3; text-align: left; white-space: normal; }
}
.payment-info-accordion .accordion-button::after { margin-left: auto; flex-shrink: 0; }
.payment-info-accordion .accordion-button:not(.collapsed) { color: inherit; }
.payment-info-list { padding-left: 20px; margin: 0; }
.payment-info-list li { margin-bottom: 6px; line-height: 1.6; }
.payment-info-list--disc { list-style-type: disc; }
.tf-field input.is-invalid,
.tf-field select.is-invalid { border-color: #dc3545 !important; }
.b2b-checkout-intro { margin-bottom:20px; padding:24px; border-radius:16px; color:#fff; background:#162231; }
.b2b-checkout-intro span { color:#ff6268; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.b2b-checkout-intro h1 { margin:5px 0 8px; color:#fff; font-size:30px; }
.b2b-checkout-intro p { margin:0; color:#cbd5df; }
.b2b-checkout-flow { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-top:18px; }
.b2b-checkout-flow div { padding:10px 12px; border-radius:9px; background:rgba(255,255,255,.08); font-size:13px; }
.b2b-checkout-flow b { display:block; margin-bottom:2px; color:#fff; }
.b2b-checkout-settings { display:inline-block; margin-top:8px; color:#d94045; font-weight:600; }
@media (max-width: 767px) { .b2b-checkout-flow { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<main id="wrapper">

    @if($isB2bCheckout ?? false)
        @include('partials.b2b-page-head')
    @endif

    {{-- Заголовок --}}
    @unless($isB2bCheckout ?? false)
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
    @endunless

    <section class="section-checkout flat-spacing-2">
        <div class="container">

            @if($isB2bCheckout ?? false)
                <div class="b2b-checkout-intro">
                    <span>B2B · заявка на поставку</span>
                    <h1>Проверим заказ и подготовим счёт</h1>
                    <p>Цены товаров зафиксированы. Наличие, срок и стоимость доставки подтвердит менеджер.</p>
                    <div class="b2b-checkout-flow">
                        <div><b>1. Отправьте заявку</b>Без оплаты на этом шаге</div>
                        <div><b>2. Менеджер проверит</b>Наличие и срок поставки</div>
                        <div><b>3. Получите счёт</b>В кабинете и на e-mail</div>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger mb-20" role="alert">{{ session('error') }}</div>
            @endif
            @if (session('info'))
                <div class="alert alert-info mb-20" role="status">{{ session('info') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger mb-20" role="alert">
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

                            {{-- 1. Контакты: только имя и телефон; у вернувшегося покупателя — из прошлого заказа --}}
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Контакты</div>
                                <div class="form-content">
                                    <div class="tf-grid-layout sm-col-2">
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium" for="customer-name">
                                                Имя <span class="text-primary">*</span>
                                            </label>
                                            <input type="text" name="customer_name" id="customer-name" autocomplete="name"
                                                class="{{ $errors->has('customer_name') ? 'is-invalid' : '' }}"
                                                value="{{ old('customer_name', $user->name ?? $lastOrder?->customer_name ?? '') }}"
                                                placeholder="Иван" required>
                                            @error('customer_name') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium" for="customer-phone">
                                                Телефон <span class="text-primary">*</span>
                                            </label>
                                            <input type="tel" name="customer_phone" id="customer-phone" autocomplete="tel" inputmode="tel"
                                                class="{{ $errors->has('customer_phone') ? 'is-invalid' : '' }}"
                                                value="{{ old('customer_phone', $user->phone ?? $lastOrder?->customer_phone ?? '') }}"
                                                placeholder="+375 (29) 000-00-00" required>
                                            @error('customer_phone') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                    </div>
                                </div>
                            </div>

                            {{-- 2. Доставка (из config/shop.php) --}}
                            @php
                                $checkoutDelivery = collect($checkoutDeliveryMethods ?? config('shop.checkout_delivery_methods', []))
                                    ->mapWithKeys(fn($key) => [$key => config("shop.delivery_methods.$key")])
                                    ->filter();
                                $evropochtaMethod = \App\Services\Shipping\EvropochtaCheckout::METHOD;
                                $defaultDelivery = $checkoutDelivery->has($evropochtaMethod)
                                    ? $evropochtaMethod
                                    : ($lastOrder?->delivery_type ?: $checkoutDelivery->keys()->first());
                                $selectedDelivery = old('delivery_type', $defaultDelivery);
                                if (! is_string($selectedDelivery) || ! $checkoutDelivery->has($selectedDelivery)) {
                                    $selectedDelivery = $checkoutDelivery->keys()->first() ?? 'courier';
                                }
                                $isEvropochtaSelected = $selectedDelivery === $evropochtaMethod;
                                $deliveryIcons = [
                                    'evropochta' => 'cube',
                                    'courier' => 'truck',
                                    'own_transport' => 'calendar-days',
                                    'transport' => 'truck',
                                    'pickup' => 'building-storefront',
                                    'b2b_delivery' => 'truck',
                                ];
                                $deliveryDetails = [
                                    'evropochta' => 'До отделения с оплатой при получении',
                                    'courier' => 'По адресу в Минске и в пределах МКАД-2',
                                    'own_transport' => 'В города по графику',
                                    'transport' => 'Для крупных заказов и посылок, недоступных для расчёта Европочтой',
                                    'pickup' => 'Минск, ул. Селицкого, 23/10',
                                ];
                            @endphp
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">{{ ($isB2bCheckout ?? false) ? 'Получение заказа' : 'Куда доставить' }}</div>
                                <div class="form-content">

                                    <fieldset id="delivery-options" class="checkout-delivery-options" aria-describedby="delivery-options-message">
                                        <legend class="tf-lable fw-medium">
                                            Способ доставки <span class="text-primary">*</span>
                                        </legend>
                                        @foreach ($checkoutDelivery as $key => $method)
                                            @php
                                                $methodPrice = \App\Http\Controllers\CheckoutController::calcDelivery($key, (float) $subtotal);
                                            @endphp
                                            <label class="checkout-delivery-option" data-delivery-option>
                                                <input type="radio" name="delivery_type" value="{{ $key }}"
                                                    @checked($selectedDelivery === $key) required>
                                                @svg('heroicon-o-'.($deliveryIcons[$key] ?? 'cube'), 'checkout-delivery-option__icon', ['aria-hidden' => 'true'])
                                                <span>
                                                    <span class="fw-medium d-block">{{ $key === 'pickup' ? $method['name'] : ($method['checkout_name'] ?? $method['name']) }}</span>
                                                    @isset($deliveryDetails[$key])
                                                        <span class="text-caption-01 cl-text-2 d-block">{{ $deliveryDetails[$key] }}</span>
                                                    @endisset
                                                </span>
                                                <span class="checkout-delivery-option__price text-caption-01 fw-medium" @if($key === $evropochtaMethod) data-evropochta-card-price @endif>
                                                    @if ($key === $evropochtaMethod)
                                                        Выберите отделение
                                                    @elseif ($methodPrice === 0.0)
                                                        Бесплатно
                                                    @elseif ($methodPrice !== null)
                                                        {{ number_format($methodPrice, 2, '.', ' ') }} BYN
                                                    @else
                                                        По согласованию
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                        @error('delivery_type')
                                            <div class="text-danger text-caption-01" id="delivery-options-message">{{ $message }}</div>
                                        @else
                                            <div class="text-caption-01 cl-text-2" id="delivery-options-message" hidden></div>
                                        @enderror
                                    </fieldset>

                                    <div id="delivery-address-fields">
                                        <div class="tf-grid-layout sm-col-2">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium" for="delivery-city">
                                                    Город <span class="text-primary delivery-required-mark">*</span>
                                                </label>
                                                <input type="text" name="delivery_city" id="delivery-city" list="delivery-city-list" autocomplete="address-level2"
                                                    class="{{ $errors->has('delivery_city') ? 'is-invalid' : '' }}"
                                                    value="{{ old('delivery_city', ($isB2bCheckout ?? false) ? ($invoiceProfile['delivery_city'] ?? '') : (session('cart_delivery_city') ?: $lastOrder?->delivery_city)) }}"
                                                    placeholder="Минск" required>
                                                <datalist id="delivery-city-list">
                                                    @foreach ($deliveryCities as $deliveryCity)
                                                        <option value="{{ $deliveryCity }}"></option>
                                                    @endforeach
                                                </datalist>
                                                @error('delivery_city') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium" for="delivery-address">
                                                    Улица, дом, квартира <span class="text-primary delivery-required-mark">*</span>
                                                </label>
                                                <input type="text" name="delivery_address" id="delivery-address" autocomplete="street-address"
                                                    class="{{ $errors->has('delivery_address') ? 'is-invalid' : '' }}"
                                                    value="{{ old('delivery_address', ($isB2bCheckout ?? false) ? ($invoiceProfile['delivery_address'] ?? '') : $lastOrder?->delivery_address) }}"
                                                    placeholder="ул. Ленина, 1, кв. 5" required>
                                                @error('delivery_address') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                            </fieldset>
                                        </div>
                                    </div>

                                    @if (!empty($evropochtaUnavailable))
                                        <div class="text-caption-01" id="evropochta-availability">
                                            <p>{{ $evropochtaUnavailable }}</p>
                                            <a href="{{ route('evropochta.calculator') }}" target="_blank" rel="noopener" class="link">
                                                Рассчитать доставку Европочтой отдельно
                                            </a>
                                        </div>
                                    @endif

                                    @if (!empty($evropochtaDestinations))
                                        <div id="evropochta-fields" class="tf-grid-layout" style="display:none;"
                                            data-quote-url="{{ route('checkout.evropochta.quote') }}">
                                            <fieldset class="tf-field">
                                                @include('partials.evropochta-branch-picker', [
                                                    'branches' => $evropochtaDestinations,
                                                    'branchId' => old('evropochta_branch'),
                                                    'pickerDisabled' => true,
                                                ])
                                                <input type="hidden" id="evropochta-token" name="evropochta_token">
                                                <p id="evropochta-status" class="text-caption-01" role="status"></p>
                                                <button type="button" id="evropochta-retry" class="link text-caption-01" hidden>Пересчитать тариф</button>
                                                @error('evropochta_branch') <div class="text-danger text-caption-01">{{ $message }}</div> @enderror
                                                @error('evropochta_token') <div class="text-danger text-caption-01">{{ $message }}</div> @enderror
                                            </fieldset>
                                        </div>
                                    @endif

                                    @if($isB2bCheckout ?? false)
                                        <a class="b2b-checkout-settings" href="{{ route('b2b.cabinet', ['section' => 'profile']) }}">Изменить основной адрес в настройках компании →</a>
                                    @else
                                        @include('partials.own-transport-schedule', ['collapsible' => true])
                                    @endif

                                </div>
                            </div>

                            {{-- 3. Оплата (из config/shop.php) --}}
                            @php
                                $paymentOverrides = $b2bCheckout['payment_method_overrides'] ?? [];
                                $checkoutPayment = collect($checkoutPaymentMethods ?? config('shop.checkout_payment_methods', []))
                                    ->mapWithKeys(fn($key) => [$key => array_replace(
                                        config("shop.payment_methods.$key", []),
                                        $paymentOverrides[$key] ?? [],
                                    )])
                                    ->filter();
                                $codPayment = \App\Services\Shipping\EvropochtaCheckout::COD_PAYMENT;
                                $hiddenPayments = $isEvropochtaSelected
                                    ? $checkoutPayment->keys()->diff($evropochtaPayments)->all()
                                    : $evropochtaOnlyPayments;
                                $visiblePayments = $checkoutPayment->except($hiddenPayments);
                                $currentPayment = old('payment_type', $selectedPayment);
                                if (! is_string($currentPayment) || ! $visiblePayments->has($currentPayment)) {
                                    $currentPayment = $isEvropochtaSelected ? $codPayment : $visiblePayments->keys()->first();
                                }
                            @endphp
                            <div class="box-ip-payment">
                                <h5 class="title">Способ оплаты</h5>
                                @if(($b2bCheckout['is_b2b'] ?? false) && $b2bCheckout['client'])
                                    <div class="alert alert-info mb-16">
                                        <strong>{{ $b2bCheckout['client']->priceLevel?->name ?: 'B2B-условия' }}</strong>
                                        · {{ $b2bCheckout['client']->payment_terms ?: 'Оплата по счёту после подтверждения заказа' }}.
                                        Заказ отправляется менеджеру на согласование. Оплата станет доступна после подтверждения наличия и формирования счёта.
                                        @if($b2bCheckout['credit']['enabled'])
                                            <span class="d-block mt-4">Отсрочка {{ $b2bCheckout['credit']['deferment_days'] }} дней: доступно {{ number_format((float)$b2bCheckout['credit']['available'], 2, ',', ' ') }} BYN из {{ number_format((float)$b2bCheckout['credit']['limit'], 2, ',', ' ') }} BYN.</span>
                                        @elseif($b2bCheckout['credit']['deferment_days'] > 0 && $b2bCheckout['credit']['issue'])
                                            <span class="d-block mt-4">Отсрочка сейчас недоступна: {{ $b2bCheckout['credit']['issue'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                <div class="payment-method-box" id="payment-method-box">

                                    @foreach ($checkoutPayment as $key => $method)
                                    @php
                                        $hidePayment = in_array($key, $hiddenPayments, true);
                                    @endphp
                                    <div class="payment_accordion" @if($hidePayment) style="display: none" @endif>
                                        <label for="pay-{{ $key }}" class="payment_check checkbox-wrap">
                                            <input type="radio" name="payment_type"
                                                class="tf-check-rounded style-2"
                                                id="pay-{{ $key }}"
                                                value="{{ $key }}"
                                                @disabled($hidePayment)
                                                @checked($currentPayment === $key)>
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
                                                @if (\App\Services\CartCouponService::onlineDiscountIsPublic())
                                                    <span class="cl-text-2 d-block">Онлайн-скидка −3% при рассрочке не применяется.</span>
                                                @endif
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

                            {{-- 3б. Реквизиты для счёта (только при invoice) --}}
                            @if($checkoutPayment->has('invoice'))
                            <div class="box-ip-checkout" id="invoice-fields" style="display:none;">
                                <div class="h5 title">Реквизиты организации</div>
                                <div class="form-content">
                                    @if($isB2bCheckout ?? false)
                                        <div class="text-caption-01 cl-text-2 mb-12">Данные загружены из карточки вашей компании. Изменения в этом заказе не затронут профиль.</div>
                                    @endif
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium" for="company-unp">
                                            УНП <span class="text-primary">*</span>
                                        </label>
                                        <div class="d-flex gap-8 align-items-stretch">
                                            <input type="text" name="company_unp" id="company-unp"
                                                value="{{ old('company_unp', $invoiceProfile['company_unp'] ?? '') }}"
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
                                            <label class="tf-lable fw-medium" for="company-name">
                                                Организация / ИП <span class="text-primary">*</span>
                                            </label>
                                            <input type="text" name="company_name" id="company-name"
                                                value="{{ old('company_name', $invoiceProfile['company_name'] ?? '') }}"
                                                placeholder="ООО Теплосервис">
                                            @error('company_name') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                        <fieldset class="tf-field">
                                            <label class="tf-lable fw-medium" for="company-email">
                                                Email для счёта <span class="text-primary">*</span>
                                            </label>
                                            <input type="email" name="company_email" id="company-email"
                                                value="{{ old('company_email', $invoiceProfile['company_email'] ?? old('customer_email', $user->email ?? '')) }}"
                                                placeholder="buh@company.by">
                                            @error('company_email') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        </fieldset>
                                    </div>
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium" for="company-address">
                                            Юридический адрес <span class="text-primary">*</span>
                                        </label>
                                        <input type="text" name="company_address" id="company-address"
                                            value="{{ old('company_address', $invoiceProfile['company_address'] ?? '') }}"
                                            placeholder="г. Минск, ул. Ленина, 1">
                                        @error('company_address') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                    </fieldset>
                                    <div class="p-12" style="border-radius:10px;background:#fff7e6;color:#8a5a00;">
                                        Сначала менеджер проверит наличие и срок поставки всех позиций. После проверки он отправит готовый счёт на указанный email.
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- 4. Необязательное — свёрнуто: e-mail и комментарий (cart_note из session) --}}
                            @php $checkoutExtrasOpen = ($isB2bCheckout ?? false) || $errors->has('customer_email') || filled(old('comment', session('cart_note'))); @endphp
                            <details class="box-ip-checkout checkout-extras" @if ($checkoutExtrasOpen) open @endif>
                                <summary class="fw-medium cl-text-2" style="cursor:pointer;font-size:15px;">{{ ($isB2bCheckout ?? false) ? 'Примечание к заказу' : '+ Дополнительно: e-mail и комментарий' }}</summary>
                                <div class="form-content mt-12">
                                    <fieldset class="tf-field">
                                        <label class="tf-lable fw-medium" for="customer-email">Email</label>
                                        @if ($user)
                                            <input type="email" id="customer-email" value="{{ $user->email }}" readonly>
                                            <input type="hidden" name="customer_email" value="{{ $user->email }}">
                                            <div class="text-caption-01 cl-text-3 mt-4">Подтверждение заказа придёт на {{ $user->email }}.</div>
                                        @else
                                            <input type="email" name="customer_email" id="customer-email" autocomplete="email"
                                                value="{{ old('customer_email') }}" placeholder="example@mail.by">
                                            @error('customer_email') <div class="text-danger text-caption-01 mt-4">{{ $message }}</div> @enderror
                                        @endif
                                    </fieldset>
                                    <fieldset class="tf-field d-grid">
                                        <label class="tf-lable fw-medium" for="order-comment">{{ ($isB2bCheckout ?? false) ? 'Примечание для менеджера' : 'Комментарий' }}</label>
                                        <textarea name="comment" id="order-comment" rows="{{ ($isB2bCheckout ?? false) ? 4 : 3 }}"
                                            placeholder="{{ ($isB2bCheckout ?? false) ? 'Адрес и время приёмки, объект, контакт на месте, нужна ли разгрузка, номер вашей заявки…' : 'Удобное время доставки, этаж, подъезд...' }}">{{ old('comment', session('cart_note')) }}</textarea>
                                    </fieldset>
                                </div>
                            </details>

                            @if($isB2bCheckout ?? false)
                                <div class="p-16 mt-20" style="border:1px solid #e6d7c7;border-left:3px solid #d94045;border-radius:12px;background:#fffdf9;">
                                    <strong>Что произойдёт после отправки</strong>
                                    <p class="mb-0 mt-4 cl-text-2">Наличие проверяется сразу, товар резервируется на складе. Менеджер подтвердит позиции и пришлёт заказ вам на согласование — после вашего подтверждения счёт сразу появится в B2B-кабинете и придёт на e-mail для счёта. При действующем кредитном лимите можно выбрать отсрочку платежа.</p>
                                </div>
                            @else
                                <x-order-confirmation-notice class="mt-20" />
                            @endif

                            <button type="submit" class="tf-btn animate-btn w-100 mt-20" data-checkout-submit>
                                {{ ($isB2bCheckout ?? false) ? 'Отправить заказ на согласование' : 'Оформить заказ' }}
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
                                                    {{ ($isB2bCheckout ?? false) ? 'B2B-цена' : 'Партнёрская цена' }}
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
                                        @elseif ($coupon['code'] === config('shop.online_order_discount.code', 'ONLINE3') && \App\Services\CartCouponService::onlineDiscountIsPublic())
                                            Онлайн-скидка применена автоматически.
                                        @else
                                            Промокод {{ $coupon['code'] }} применён.
                                        @endif
                                    </p>
                                </div>
                            @endif

                            {{-- Итоги --}}
                            @php
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
                                        <span>{{ $coupon['code'] === config('shop.online_order_discount.code', 'ONLINE3') ? 'Онлайн-скидка −3%:' : 'Скидка по промокоду:' }}</span>
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
                                    <span>{{ ($isB2bCheckout ?? false) ? 'Доставка после согласования:' : 'Доставка:' }}</span>
                                    <span id="checkout-delivery-cost">
                                        @if ($isEvropochtaSelected)
                                            <span class="cl-text-2">Выберите отделение</span>
                                        @elseif ($deliveryCost === null)
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
                                <span>{{ ($isB2bCheckout ?? false) ? 'Предварительно:' : 'Итого:' }}</span>
                                <span class="text-primary" id="checkout-total">
                                    {{ $isEvropochtaSelected ? '—' : number_format($total, 2, '.', ' ').' BYN' }}
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
    var deliveryMethods = @json(collect($checkoutDeliveryMethods ?? config('shop.checkout_delivery_methods', []))->mapWithKeys(fn($k) => [$k => config("shop.delivery_methods.$k")])->filter());
    var isB2bCheckout = @json((bool) ($isB2bCheckout ?? false));
    var subtotal = {{ (float) $subtotal }};
    var evropochtaPrice = null;
    var couponDiscount = {{ (float) $coupon['coupon_discount'] }};
    var bundleDiscount = {{ (float) $coupon['bundle_discount'] }};
    var onlineOrderDiscountCoupon = @json($onlineOrderDiscountCoupon);

    // ── Показ/скрытие полей адреса при самовывозе ──
    var deliveryGroup = document.getElementById('delivery-options');
    var evropochtaCardPrice = deliveryGroup?.querySelector('[data-evropochta-card-price]');
    var deliveryRadios = deliveryGroup ? Array.from(deliveryGroup.querySelectorAll('input[name="delivery_type"]')) : [];
    function deliveryType() {
        return deliveryRadios.find(function (option) { return option.checked && !option.disabled; })?.value ?? '';
    }
    var ownTransportSchedule = document.getElementById('own-transport-schedule');
    var addressFields    = document.getElementById('delivery-address-fields');
    var addressInputs    = ['delivery-city', 'delivery-address'].map(function(id) { return document.getElementById(id); }).filter(Boolean);
    var requiredMarks    = document.querySelectorAll('.delivery-required-mark');
    if (deliveryGroup && addressFields) {
        function toggleAddress() {
            var isPickup = deliveryType() === 'pickup';
            var isEvropochta = deliveryType() === 'evropochta';
            addressFields.hidden = isEvropochta;
            addressInputs.forEach(function(el) { el.required = !isPickup && !isEvropochta; el.disabled = isEvropochta; });
            if (ownTransportSchedule) ownTransportSchedule.style.display = deliveryType() === 'own_transport' ? '' : 'none';
            requiredMarks.forEach(function(el) { el.style.display = isPickup ? 'none' : ''; });
            if (ownTransportSchedule && deliveryType() === 'own_transport') {
                ownTransportSchedule.open = true;
            }
        }
        deliveryGroup.addEventListener('change', toggleAddress);
        toggleAddress();
    }

    var cityInput = document.getElementById('delivery-city');
    var ownRouteCities = @json(collect(config('shop.delivery_methods.own_transport.routes', []))->pluck('destinations')->flatten()->map(fn ($c) => mb_strtolower($c))->values());
    var deliveryChosenByHand = @json(old('delivery_type') !== null);
    if (deliveryGroup) {
        deliveryGroup.addEventListener('change', function (event) { if (event.isTrusted) deliveryChosenByHand = true; });
    }
    function cityKey(city) {
        return String(city || '').trim().toLowerCase().replace(/^(?:г\.\s*|город\s+)/, '');
    }
    function applyCity() {
        if (isB2bCheckout) return;
        var city = cityKey(cityInput.value);
        var routeCity = ownRouteCities.includes(city);
        var available = deliveryRadios.filter(function (option) {
            var method = option.value;
            var show = !city || method === 'pickup' || (city === 'минск'
                ? method === 'courier'
                : method === 'evropochta' || method === 'transport' || (method === 'own_transport' && routeCity));
            option.disabled = !show;
            option.closest('[data-delivery-option]').hidden = !show;
            return show;
        });
        var preferred = !city || city === 'минск' ? 'courier' : (routeCity ? 'own_transport' : 'transport');
        var selected = available.find(function (option) { return option.checked; });
        if (!selected || (!deliveryChosenByHand && selected.value !== 'pickup')) {
            var suggested = ['evropochta', preferred].map(function (method) {
                return available.find(function (option) { return option.value === method; });
            }).find(Boolean) ?? available[0];
            if (suggested) suggested.checked = true;
        }
        deliveryGroup.dispatchEvent(new Event('change'));
    }
    if (cityInput && deliveryGroup) {
        cityInput.addEventListener('change', applyCity);
    }

    // ── Гость: контакты и адрес прошлого заказа хранятся в браузере и подставляются в пустые поля ──
    var checkoutForm = document.querySelector('form.tf-checkout-cart-main');
    var rememberKey = @json($user ? 'kotlov-checkout-contact:user:'.$user->id : 'kotlov-checkout-contact:guest');
    var rememberIds = ['customer-name', 'customer-phone', 'delivery-city', 'delivery-address'];
    try {
        var remembered = JSON.parse(localStorage.getItem(rememberKey) || '{}');
        rememberIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el.value && remembered[id]) el.value = remembered[id];
        });
    } catch (e) {}
    if (cityInput && deliveryGroup) applyCity();
    if (checkoutForm) {
        var submitButton = checkoutForm.querySelector('[data-checkout-submit]');
        var submitLabel = submitButton ? submitButton.textContent : '';
        window.addEventListener('pageshow', function () {
            if (submitButton) { submitButton.disabled = false; submitButton.textContent = submitLabel; }
        });
        checkoutForm.addEventListener('submit', function () {
            if (submitButton) { submitButton.disabled = true; submitButton.textContent = 'Отправляем заказ…'; }
            try {
                var data = {};
                rememberIds.forEach(function (id) { var el = document.getElementById(id); if (el) data[id] = el.value; });
                localStorage.setItem(rememberKey, JSON.stringify(data));
            } catch (e) {}
        });
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
        if (key === 'evropochta') return evropochtaPrice;
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
        if (!deliveryCostEl || !totalEl || !deliveryGroup) return;

        var cost = calcDelivery(deliveryType());
        if (evropochtaCardPrice) {
            evropochtaCardPrice.textContent = evropochtaPrice === null ? 'Выберите отделение' : fmtByn(evropochtaPrice);
        }
        var total = subtotal - effectiveDiscount() + (cost !== null ? cost : 0);

        if (cost === null && deliveryType() === 'evropochta') {
            deliveryCostEl.textContent = 'Выберите отделение';
            totalEl.textContent = '—';
            return;
        }
        if (cost === null) {
            deliveryCostEl.innerHTML = '<span class="cl-text-2">уточняется</span>';
        } else if (cost === 0) {
            deliveryCostEl.innerHTML = '<span class="text-primary fw-semibold">Бесплатно</span>';
        } else {
            deliveryCostEl.innerHTML = '<span class="fw-semibold">' + fmtByn(cost) + '</span>';
        }

        totalEl.textContent = fmtByn(total);
    }

    if (deliveryGroup) {
        deliveryGroup.addEventListener('change', updateTotals);
        updateTotals();
    }

    // ── Показ/скрытие реквизитов при оплате по счёту ──
    var paymentRadios = document.querySelectorAll('input[name="payment_type"]');

    var evropochtaFields = document.getElementById('evropochta-fields');
    if (evropochtaFields && deliveryGroup && checkoutForm) {
        var picker = window.mountEvropochtaBranchPicker(evropochtaFields.querySelector('[data-evropochta-picker]'));
        var branchField = picker.branch;
        var branchSearch = picker.search;
        var quoteToken = document.getElementById('evropochta-token');
        var quoteStatus = document.getElementById('evropochta-status');
        var retry = document.getElementById('evropochta-retry');
        var quoteRequest;
        var active = null;
        var evropochtaPayments = @json($evropochtaPayments);
        var evropochtaOnlyPayments = @json($evropochtaOnlyPayments);
        var codPayment = @json(\App\Services\Shipping\EvropochtaCheckout::COD_PAYMENT);

        function updateEvropochtaPayment() {
            var selected = null;
            var fallback = null;
            paymentRadios.forEach(function (radio) {
                var isCod = radio.value === codPayment;
                var show = active ? evropochtaPayments.includes(radio.value) : !evropochtaOnlyPayments.includes(radio.value);
                radio.disabled = !show;
                radio.closest('.payment_accordion').style.display = show ? '' : 'none';
                if (!show) return;
                if (radio.checked) selected = radio;
                if (!fallback || (active && isCod)) fallback = radio;
            });
            selected = selected ?? fallback;
            if (selected && !selected.checked) {
                selected.checked = true;
                selected.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        async function loadEvropochtaQuote() {
            if (quoteRequest) quoteRequest.abort();
            quoteToken.value = '';
            evropochtaPrice = null;
            retry.hidden = true;
            updateTotals();
            var destination = branchField.value;
            branchSearch.setCustomValidity(active && !destination ? 'Выберите отделение из списка.' : '');
            quoteStatus.textContent = '';
            if (!active || !destination) return;

            var request = new AbortController();
            quoteRequest = request;
            branchSearch.setCustomValidity('Дождитесь расчёта тарифа.');
            quoteStatus.textContent = 'Рассчитываем тариф…';
            try {
                var response = await fetch(evropochtaFields.dataset.quoteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': checkoutForm.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ branch_id: destination }),
                    signal: request.signal
                });
                var result = await response.json();
                if (request.signal.aborted) return;
                if (!response.ok || typeof result.price !== 'number' || typeof result.token !== 'string') {
                    throw new Error('Quote unavailable');
                }
                evropochtaPrice = result.price;
                quoteToken.value = result.token;
                branchSearch.setCustomValidity('');
                quoteStatus.textContent = 'Доставка до отделения — ' + fmtByn(evropochtaPrice);
                updateTotals();
            } catch (error) {
                if (request.signal.aborted) return;
                branchSearch.setCustomValidity('Пересчитайте тариф или смените способ доставки.');
                quoteStatus.textContent = 'Не удалось рассчитать тариф. Попробуйте ещё раз или выберите другой способ доставки.';
                retry.hidden = false;
            }
        }

        function toggleEvropochta() {
            var selected = deliveryType() === 'evropochta';
            if (selected === active) return;
            active = selected;
            evropochtaFields.style.display = active ? '' : 'none';
            picker.toggle(active, cityInput?.value);
            updateEvropochtaPayment();
            loadEvropochtaQuote();
        }

        branchField.addEventListener('change', loadEvropochtaQuote);
        retry.addEventListener('click', loadEvropochtaQuote);
        deliveryGroup.addEventListener('change', toggleEvropochta);
        toggleEvropochta();
    }

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
            var isInvoice = checked && ['invoice', 'invoice_deferred'].indexOf(checked.value) !== -1;
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
