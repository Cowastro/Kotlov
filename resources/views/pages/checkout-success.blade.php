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
                    <p class="text-caption-01">Заказ оформлен</p>
                </div>
                <h3>Спасибо за заказ!</h3>
                <p class="text-body-1 cl-text-2">
                    Ваш заказ принят. Мы свяжемся с вами в ближайшее время.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing-2">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">

                    {{-- Карточка заказа --}}
                    <div class="box-ip-checkout estimate-shipping">

                        <div class="h5 title d-flex justify-content-between align-items-center">
                            <span>Заказ <span class="text-primary">{{ $order->number }}</span></span>
                            <span class="text-caption-01 fw-medium
                                @if($order->status === 'new') cl-text-2
                                @elseif($order->status === 'cancelled') text-danger
                                @elseif($order->status === 'delivered') text-success
                                @else cl-text-2
                                @endif">
                                {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}
                            </span>
                        </div>

                        <div class="form-content">

                            {{-- Товары --}}
                            <ul class="list-order-product mb-20">
                                @foreach ($order->items as $item)
                                    <li class="order-item fw-medium">
                                        <div class="infor-prd">
                                            <span class="prd_name fw-medium lh-24">{{ $item->product_name }}</span>
                                            <div class="text-caption-01 cl-text-2">
                                                {{ $item->quantity }} шт.
                                                @if ($item->product_sku)
                                                    · {{ $item->product_sku }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="quantity-price text-primary fw-semibold">
                                            {{ number_format($item->total, 2, '.', ' ') }} BYN
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Итоги --}}
                            <ul class="list-total">
                                <li class="total-item lh-24 fw-medium">
                                    <span>Товары ({{ $order->items->sum('quantity') }} шт.):</span>
                                    <span>{{ number_format($order->subtotal, 2, '.', ' ') }} BYN</span>
                                </li>
                                <li class="total-item lh-24 fw-medium">
                                    <span>Доставка:</span>
                                    <span>
                                        @if ((float) $order->delivery_price === 0.0)
                                            <span class="text-primary">Бесплатно</span>
                                        @else
                                            {{ number_format($order->delivery_price, 2, '.', ' ') }} BYN
                                        @endif
                                    </span>
                                </li>
                            </ul>

                            <div class="last-total h5 fw-medium mt-16">
                                <span>Итого:</span>
                                <span class="text-primary">
                                    {{ number_format($order->total, 2, '.', ' ') }} BYN
                                </span>
                            </div>

                            {{-- Доставка и оплата --}}
                            <div class="tf-grid-layout sm-col-2 mt-24">
                                <div>
                                    <p class="text-caption-01 cl-text-3 mb-4">Способ доставки</p>
                                    <p class="fw-medium lh-24">
                                        {{ config("shop.delivery_methods.{$order->delivery_type}.name", $order->delivery_type) }}
                                    </p>
                                    @php
                                        $addressParts = array_filter([
                                            $order->delivery_region,
                                            $order->delivery_city,
                                            $order->delivery_address,
                                        ]);
                                    @endphp
                                    @if (count($addressParts))
                                        <p class="text-caption-01 cl-text-2">
                                            {{ implode(', ', $addressParts) }}
                                        </p>
                                    @endif
                                </div>

                                <div>
                                    <p class="text-caption-01 cl-text-3 mb-4">Способ оплаты</p>
                                    <p class="fw-medium lh-24">
                                        {{ config("shop.payment_methods.{$order->payment_type}.name", $order->payment_type) }}
                                    </p>
                                </div>
                            </div>

                            {{-- Реквизиты организации (только invoice) --}}
                            @if ($order->payment_type === 'invoice' && $order->company_name)
                                <div class="mt-16 p-16"
                                    style="background:var(--surface-2,#f5f5f5);border-radius:8px;">
                                    <p class="text-caption-01 cl-text-3 mb-8">Реквизиты организации</p>
                                    <p class="fw-medium lh-24">{{ $order->company_name }}</p>
                                    @if ($order->company_unp)
                                        <p class="text-caption-01 cl-text-2">УНП: {{ $order->company_unp }}</p>
                                    @endif
                                    @if ($order->company_address)
                                        <p class="text-caption-01 cl-text-2">{{ $order->company_address }}</p>
                                    @endif
                                    @if ($order->company_email)
                                        <p class="text-caption-01 cl-text-2">{{ $order->company_email }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Комментарий --}}
                            @if ($order->comment)
                                <div class="mt-16">
                                    <p class="text-caption-01 cl-text-3 mb-4">Комментарий к заказу</p>
                                    <p class="lh-24">{{ $order->comment }}</p>
                                </div>
                            @endif

                        </div>
                    </div>

                    {{-- CTA --}}
                    <div class="mt-24 d-flex flex-column gap-12">

                        @auth
                            <a href="{{ route('account') }}" class="tf-btn animate-btn w-100">
                                Перейти в личный кабинет
                            </a>
                        @else
                            <div class="box-ip-checkout estimate-shipping text-center">
                                <p class="lh-24 cl-text-2 mb-16">
                                    Зарегистрируйтесь, чтобы отслеживать статус заказа в личном кабинете
                                    и получать уведомления об изменениях.
                                </p>
                                <div class="d-flex gap-12 justify-content-center flex-wrap">
                                    <a href="#register" data-bs-toggle="modal"
                                        class="tf-btn animate-btn">
                                        Зарегистрироваться
                                    </a>
                                    <a href="#sign" data-bs-toggle="modal"
                                        class="tf-btn btn-outline-primary">
                                        Войти
                                    </a>
                                </div>
                            </div>
                        @endauth

                        <a href="{{ route('catalog') }}" class="tf-btn btn-outline-primary w-100">
                            Продолжить покупки
                        </a>

                    </div>

                </div>
            </div>
        </div>
    </section>

</main>
@endsection
