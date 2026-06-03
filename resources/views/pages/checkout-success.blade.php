@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    <section class="flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">

                    <div class="mb-24" style="font-size:64px;">✅</div>

                    <h3 class="mb-12">Заказ оформлен!</h3>
                    <p class="text-body-1 cl-text-2 mb-8">
                        Ваш заказ <strong>{{ $order->number }}</strong> принят в обработку.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Наш менеджер свяжется с вами по телефону
                        <strong>{{ $order->customer_phone }}</strong>
                        для подтверждения.
                    </p>

                    {{-- Товары --}}
                    <div class="box-order-summary text-start mb-32">
                        <h6 class="mb-16 fw-semibold">Состав заказа:</h6>
                        @foreach ($order->items as $item)
                            <div class="d-flex justify-content-between align-items-center py-8 border-bottom">
                                <div>
                                    <p class="fw-medium mb-0">{{ $item->product_name }}</p>
                                    <p class="text-caption-01 cl-text-3 mb-0">{{ $item->quantity }} шт.</p>
                                </div>
                                <span class="fw-semibold text-primary">
                                    {{ number_format($item->total, 2, '.', ' ') }} BYN
                                </span>
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-between align-items-center pt-16">
                            <span class="fw-semibold h6 mb-0">Итого:</span>
                            <span class="fw-semibold text-primary fs-18">
                                {{ number_format($order->total, 2, '.', ' ') }} BYN
                            </span>
                        </div>
                    </div>

                    <div class="d-flex gap-12 justify-content-center flex-wrap">
                        <a href="/catalog" class="tf-btn animate-btn">Продолжить покупки</a>
                        @auth
                            <a href="/account" class="tf-btn btn-stroke">Мои заказы</a>
                        @endauth
                    </div>

                </div>
            </div>
        </div>
    </section>

</main>
@endsection
