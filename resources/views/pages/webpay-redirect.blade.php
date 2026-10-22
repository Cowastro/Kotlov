@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    <section class="flat-spacing text-center">
        <div class="container" style="max-width: 680px;">
            <h3 class="mb-16">Переходим к безопасной оплате</h3>
            <p class="text-body-1 cl-text-2 mb-24">Сейчас откроется защищённая платёжная страница WEBPAY.</p>

            <form id="webpay-payment-form" method="POST" action="{{ $action }}">
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @foreach ($items as $index => $item)
                    <input type="hidden" name="wsb_invoice_item_name[{{ $index }}]" value="{{ $item['name'] }}">
                    <input type="hidden" name="wsb_invoice_item_quantity[{{ $index }}]" value="{{ $item['quantity'] }}">
                    <input type="hidden" name="wsb_invoice_item_price[{{ $index }}]" value="{{ $item['price'] }}">
                @endforeach
                <button type="submit" class="tf-btn animate-btn">Перейти к оплате</button>
            </form>
        </div>
    </section>
</main>

<script>
    window.setTimeout(function () {
        document.getElementById('webpay-payment-form').submit();
    }, 250);
</script>
@endsection
