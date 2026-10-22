@extends('layouts.amerce')

@section('content')
@php
    $statusLabel = \App\Models\Order::STATUSES[$order->status] ?? $order->status;
    $paymentTypeLabel = \App\Models\Order::PAYMENT_TYPES[$order->payment_type] ?? $order->payment_type;
    $deliveryTypeLabel = \App\Models\Order::DELIVERY_TYPES[$order->delivery_type] ?? $order->delivery_type;
    $deliveryAddress = collect([$order->delivery_region, $order->delivery_city, $order->delivery_address])
        ->filter()
        ->unique()
        ->implode(', ');
    $onlinePayment = in_array($order->payment_type, ['online_card', 'webpay'], true);
    $statusBanner = match ($order->status) {
        'processing' => [
            'title' => 'Заказ в исполнении',
            'description' => 'Менеджер передал заказ в работу. Сейчас мы готовим товары к доставке или выдаче.',
            'icon' => '↻',
            'class' => 'is-processing',
        ],
        'shipped' => [
            'title' => 'Передан в доставку',
            'description' => 'Заказ собран и передан в доставку или подготовлен к выдаче клиенту.',
            'icon' => '→',
            'class' => 'is-shipped',
        ],
        'delivered', 'completed' => [
            'title' => 'Заказ доставлен',
            'description' => 'Заказ получен клиентом. Благодарим за покупку в KOTLOV.BY.',
            'icon' => '✓',
            'class' => 'is-delivered',
        ],
        'cancelled' => [
            'title' => 'Заказ отменён',
            'description' => 'Заказ отменён. Если у вас остались вопросы, пожалуйста, свяжитесь с менеджером.',
            'icon' => '×',
            'class' => 'is-cancelled',
        ],
        default => [
            'title' => 'Наличие подтверждено',
            'description' => 'Менеджер проверил возможность исполнения всех позиций. Здесь отображаются актуальный состав и сумма заказа.',
            'icon' => '✓',
            'class' => 'is-confirmed',
        ],
    };
@endphp

<main id="wrapper" class="account-order-page">
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('account') }}" class="text-caption-01 cl-text-3 link">Личный кабинет</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Заказ {{ $order->number }}</p>
                </div>
                <h3>Заказ {{ $order->number }}</h3>
                <p class="text-body-1 cl-text-2 mb-0">
                    Создан {{ $order->created_at?->format('d.m.Y в H:i') }}
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="order-confirmed-banner {{ $statusBanner['class'] }} mb-24">
                <div class="order-confirmed-banner__icon">{{ $statusBanner['icon'] }}</div>
                <div>
                    <h5 class="mb-4">{{ $statusBanner['title'] }}</h5>
                    <p class="mb-0 cl-text-2">
                        {{ $statusBanner['description'] }}
                    </p>
                </div>
            </div>

            <div class="row g-24">
                <div class="col-lg-8">
                    <div class="order-card">
                        <div class="d-flex flex-wrap justify-content-between gap-12 align-items-center mb-24">
                            <h5 class="mb-0">Состав заказа</h5>
                            <span class="order-status-pill">{{ $statusLabel }}</span>
                        </div>

                        <div class="order-lines">
                            @foreach ($order->items as $item)
                                <div class="order-line">
                                    <div class="order-line__main">
                                        <p class="fw-medium mb-4">{{ $item->product_name ?: 'Товар' }}</p>
                                        @if ($item->product_sku)
                                            <p class="text-caption-01 cl-text-3 mb-0">Артикул: {{ $item->product_sku }}</p>
                                        @endif
                                    </div>
                                    <div class="order-line__qty cl-text-2">
                                        {{ $item->quantity }} шт. × {{ number_format((float) $item->price, 2, ',', ' ') }} BYN
                                    </div>
                                    <div class="order-line__total fw-medium">
                                        {{ number_format((float) $item->total, 2, ',', ' ') }} BYN
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="order-totals mt-24">
                            <div><span>Товары</span><strong>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} BYN</strong></div>
                            @if ((float) $order->discount > 0)
                                <div><span>Скидка</span><strong>−{{ number_format((float) $order->discount, 2, ',', ' ') }} BYN</strong></div>
                            @endif
                            @if ((float) $order->delivery_price > 0)
                                <div><span>Доставка</span><strong>{{ number_format((float) $order->delivery_price, 2, ',', ' ') }} BYN</strong></div>
                            @endif
                            <div class="order-totals__grand"><span>Итого</span><strong>{{ number_format((float) $order->total, 2, ',', ' ') }} BYN</strong></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="order-card mb-24">
                        <h5 class="mb-20">Оплата</h5>

                        @if (session('payment_success'))
                            <div class="order-payment-note order-payment-note--success mb-20">{{ session('payment_success') }}</div>
                        @endif
                        @if (session('payment_error'))
                            <div class="order-payment-note order-payment-note--error mb-20">{{ session('payment_error') }}</div>
                        @endif

                        <dl class="order-info mb-0">
                            <div><dt>Способ</dt><dd>{{ $paymentTypeLabel }}</dd></div>
                            <div><dt>Статус</dt><dd>{{ $order->paymentStatusLabel() }}</dd></div>
                        </dl>

                        @if ($order->payment_status === 'paid')
                            <div class="order-payment-note order-payment-note--success mt-20">Заказ оплачен</div>
                        @elseif ($webpayCanPay)
                            <div class="order-payment-note mt-20">
                                Состав и сумма подтверждены менеджером. Заказ можно безопасно оплатить картой через WEBPAY.
                            </div>
                            <form method="POST" action="{{ route('account.orders.webpay.start', $order) }}" class="mt-16">
                                @csrf
                                <button type="submit" class="tf-btn animate-btn w-100">Оплатить через WEBPAY</button>
                            </form>
                        @elseif ($onlinePayment && ! $webpayConfigured)
                            <div class="order-payment-note mt-20">
                                Онлайн-оплата временно недоступна. Менеджер сообщит, когда можно будет оплатить заказ.
                            </div>
                        @elseif ($order->isPaymentOnReceipt())
                            <div class="order-payment-note mt-20">Оплата производится при получении заказа.</div>
                        @endif
                    </div>

                    <div class="order-card">
                        <h5 class="mb-20">Получение</h5>
                        <dl class="order-info mb-0">
                            <div><dt>Способ</dt><dd>{{ $deliveryTypeLabel }}</dd></div>
                            @if ($deliveryAddress)
                                <div><dt>Адрес</dt><dd>{{ $deliveryAddress }}</dd></div>
                            @endif
                            @if ($order->planned_delivery_date)
                                <div><dt>Плановая дата</dt><dd>{{ $order->planned_delivery_date->format('d.m.Y') }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="mt-24">
                <a href="{{ route('account') }}" class="tf-btn btn-white btn-stroke">Вернуться в личный кабинет</a>
            </div>
        </div>
    </section>
</main>

<style>
    .account-order-page { background: #f7f7f5; }
    .order-card, .order-confirmed-banner { background: #fff; border: 1px solid #e8e8e3; border-radius: 16px; padding: 28px; }
    .order-confirmed-banner { display: flex; align-items: center; gap: 18px; border-color: #cfe8d6; background: #f5fbf7; }
    .order-confirmed-banner__icon { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 50%; display: grid; place-items: center; color: #fff; background: #278449; font-size: 22px; font-weight: 700; }
    .order-confirmed-banner.is-processing { border-color: #cbdcf5; background: #f3f7fd; }
    .order-confirmed-banner.is-processing .order-confirmed-banner__icon { background: #2563a8; }
    .order-confirmed-banner.is-shipped { border-color: #f0ddb0; background: #fffaf0; }
    .order-confirmed-banner.is-shipped .order-confirmed-banner__icon { background: #bd7600; }
    .order-confirmed-banner.is-cancelled { border-color: #f0caca; background: #fff6f6; }
    .order-confirmed-banner.is-cancelled .order-confirmed-banner__icon { background: #b42323; }
    .order-status-pill { display: inline-flex; align-items: center; min-height: 32px; padding: 6px 12px; border-radius: 999px; color: #246c3d; background: #e9f7ed; font-size: 13px; font-weight: 600; }
    .order-line { display: grid; grid-template-columns: minmax(0, 1fr) auto 140px; gap: 20px; align-items: center; padding: 20px 0; border-top: 1px solid #ededeb; }
    .order-line:first-child { border-top: 0; padding-top: 0; }
    .order-line__total { text-align: right; }
    .order-totals { margin-left: auto; max-width: 430px; border-top: 1px solid #deded9; padding-top: 16px; }
    .order-totals > div { display: flex; justify-content: space-between; gap: 20px; padding: 7px 0; }
    .order-totals__grand { margin-top: 8px; padding-top: 16px !important; border-top: 1px solid #deded9; font-size: 18px; }
    .order-info > div { padding: 13px 0; border-top: 1px solid #ededeb; }
    .order-info > div:first-child { border-top: 0; padding-top: 0; }
    .order-info dt { color: #7a7a74; font-size: 13px; font-weight: 400; margin-bottom: 3px; }
    .order-info dd { margin: 0; color: #171714; }
    .order-payment-note { padding: 14px 16px; border-radius: 10px; background: #fff5d8; color: #6b5313; font-size: 14px; line-height: 1.45; }
    .order-payment-note--success { background: #e9f7ed; color: #246c3d; }
    .order-payment-note--error { background: #fdecec; color: #9a2f2f; }
    @media (max-width: 767.98px) {
        .order-card, .order-confirmed-banner { padding: 20px; }
        .order-line { grid-template-columns: 1fr auto; gap: 8px 16px; }
        .order-line__main { grid-column: 1 / -1; }
        .order-line__total { text-align: right; }
    }
</style>
@endsection
