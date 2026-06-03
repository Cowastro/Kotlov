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
                    <p class="text-caption-01">Корзина</p>
                </div>
                <h3>Корзина</h3>
            </div>
        </div>
    </section>

    <section class="section-shoping-cart each-list-prd flat-spacing-2">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success mb-20">{{ session('success') }}</div>
            @endif

            @if (count($items) > 0)
                <div class="row">

                    {{-- ===== Таблица товаров ===== --}}
                    <div class="col-lg-8">
                        <div class="overflow-auto">
                            <table class="tf-table-page-cart">
                                <thead>
                                    <tr>
                                        <th><p class="h6 fw-medium">Товар</p></th>
                                        <th><p class="h6 fw-medium">Цена</p></th>
                                        <th><p class="h6 fw-medium">Количество</p></th>
                                        <th class="text-end"><p class="h6 fw-medium">Сумма</p></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        @php
                                            $productUrl = '/' . $item['category_slug'] . '/' . $item['slug'];
                                            $itemTotal  = round($item['price'] * $item['quantity'], 2);
                                        @endphp
                                        <tr class="tf-cart_item each-prd file-delete"
                                            data-product-id="{{ $item['id'] }}">

                                            <td class="cart_product">
                                                <a href="{{ $productUrl }}" class="img-prd">
                                                    <img loading="lazy" width="100" height="100"
                                                        src="{{ $item['image'] ? asset($item['image']) : asset('img/products/product-placeholder.jpg') }}"
                                                        alt="{{ $item['name'] }}">
                                                </a>
                                                <div class="infor-prd">
                                                    <a href="{{ $productUrl }}"
                                                        class="prd_name fw-medium link lh-24">
                                                        {{ $item['name'] }}
                                                    </a>
                                                    @if ($item['sku'])
                                                        <p class="text-caption-01 cl-text-3 mb-4">
                                                            Арт: {{ $item['sku'] }}
                                                        </p>
                                                    @endif
                                                    <form action="{{ route('cart.remove') }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="product_id"
                                                            value="{{ $item['id'] }}">
                                                        <button type="submit"
                                                            class="cart_remove tf-btn-line-3 type-primary remove border-0 bg-transparent p-0 cursor-pointer">
                                                            <span class="text-caption-01 fw-semibold">Удалить</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>

                                            <td class="cart_price each-price fw-semibold text-primary"
                                                data-cart-title="Цена"
                                                data-price="{{ $item['price'] }}">
                                                {{ number_format($item['price'], 2, '.', ' ') }} BYN
                                            </td>

                                            <td class="cart_quantity" data-cart-title="Количество">
                                                <div class="wg-quantity">
                                                    <button type="button" class="btn-quantity minus-quantity">
                                                        <i class="icon icon-minus"></i>
                                                    </button>
                                                    <input class="quantity-product cart-qty-input"
                                                        type="text"
                                                        value="{{ $item['quantity'] }}"
                                                        data-product-id="{{ $item['id'] }}"
                                                        min="1" max="999">
                                                    <button type="button" class="btn-quantity plus-quantity">
                                                        <i class="icon icon-plus"></i>
                                                    </button>
                                                </div>
                                            </td>

                                            <td class="text-end">
                                                <div class="cart_total fw-semibold text-primary each-subtotal-price">
                                                    {{ number_format($itemTotal, 2, '.', ' ') }} BYN
                                                </div>
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-16">
                            <a href="/catalog" class="tf-btn-line-3 type-primary">
                                <span class="text-caption-01 fw-semibold">← Продолжить покупки</span>
                            </a>
                            <form action="{{ route('cart.clear') }}" method="POST">
                                @csrf
                                <button type="submit" class="cart_remove tf-btn-line-3 type-primary border-0 bg-transparent p-0">
                                    <span class="text-caption-01 fw-semibold">Очистить корзину</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- ===== Итого ===== --}}
                    <div class="col-lg-4">
                        <div class="fl-sidebar-cart mt-lg-0 sticky-top">
                            <div class="box-order-summary">
                                <h5 class="title mb-20">Итого по заказу</h5>

                                <div class="subtotal d-flex justify-content-between align-items-center mb-12">
                                    <p class="fw-medium lh-24 mb-0">Товаров:</p>
                                    <span class="fw-medium lh-24" id="cart-items-count">
                                        {{ array_sum(array_column($items, 'quantity')) }} шт.
                                    </span>
                                </div>

                                <div class="subtotal d-flex justify-content-between align-items-center mb-20">
                                    <p class="fw-medium lh-24 mb-0">Сумма товаров:</p>
                                    <span class="fw-semibold text-primary" id="cart-subtotal">
                                        {{ number_format($subtotal, 2, '.', ' ') }} BYN
                                    </span>
                                </div>

                                <div class="br-line mb-20"></div>

                                <p class="text-caption-01 cl-text-3 mb-20">
                                    Доставка рассчитывается при оформлении.
                                </p>

                                <a href="{{ route('checkout') }}" class="tf-btn animate-btn w-100 mb-12">
                                    Оформить заказ
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

            @else
                <div class="text-center py-80">
                    <i class="icon icon-Handbag fs-60 cl-text-3 mb-20" style="font-size:60px;display:block;"></i>
                    <h4 class="mb-12">Корзина пуста</h4>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Добавьте товары из каталога отопительного оборудования
                    </p>
                    <a href="/catalog" class="tf-btn animate-btn">Перейти в каталог</a>
                </div>
            @endif

        </div>
    </section>

</main>
@endsection

@push('scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    function formatMoney(num) {
        return parseFloat(num).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1 ');
    }

    function updateHeaderCount(count) {
        document.querySelectorAll('.shop-cart .count').forEach(function (el) {
            el.textContent = count;
        });
    }

    document.querySelectorAll('.cart-qty-input').forEach(function (input) {
        var timer = null;

        function sendUpdate() {
            var qty = parseInt(input.value, 10) || 1;
            if (qty < 1) { qty = 1; input.value = 1; }

            fetch('{{ route("cart.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    product_id: input.dataset.productId,
                    quantity: qty,
                }),
            })
            .then(r => r.json())
            .then(function (data) {
                var row   = input.closest('tr[data-product-id]');
                var price = parseFloat(row.querySelector('[data-price]').dataset.price);
                row.querySelector('.each-subtotal-price').textContent =
                    formatMoney(price * qty) + ' BYN';

                document.getElementById('cart-subtotal').textContent =
                    formatMoney(data.subtotal) + ' BYN';
                document.getElementById('cart-items-count').textContent =
                    data.count + ' шт.';

                updateHeaderCount(data.count);
            });
        }

        input.addEventListener('change', function () {
            clearTimeout(timer);
            timer = setTimeout(sendUpdate, 300);
        });

        // Amerce main.js меняет value через input-событие при нажатии +/-
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(sendUpdate, 500);
        });
    });
})();
</script>
@endpush
