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
                    <p class="text-caption-01">Сравнение товаров</p>
                </div>
                <h3>Сравнение товаров</h3>
                <p class="text-body-1 cl-text-2">
                    Сравните характеристики выбранных товаров<br class="d-none d-lg-block">
                    и выберите лучший вариант для вашего объекта.
                </p>
            </div>
        </div>
    </section>

    {{-- Таблица сравнения --}}
    <div class="flat-spacing">
        <div class="container">

            @if ($products->count() > 0)

                {{-- Кнопка очистить --}}
                <div class="d-flex justify-content-end mb-16">
                    <form action="/compare/clear" method="POST">
                        @csrf
                        <button type="submit" class="tf-btn btn-outline small">
                            <i class="icon icon-trash"></i> Очистить список
                        </button>
                    </form>
                </div>

                <div class="tf-table-compare">
                    <table>
                        <thead>
                            <tr class="compare-row">
                                <th class="compare-col"></th>

                                @foreach ($products as $product)
                                    @php
                                        $images = is_array($product->images) ? $product->images : [];
                                        $img = $images[0] ?? null;
                                        $imgUrl = $img
                                            ? 'https://kotlov.by/images/product/' . $img
                                            : asset('img/products/product-placeholder.jpg');
                                        $productUrl = '/' . ($product->category->slug ?? 'catalog') . '/' . $product->slug;
                                    @endphp
                                    <th class="compare-col compare-head">
                                        <div class="compare-item text-center">
                                            <div class="item_image">
                                                <img loading="lazy" width="276" height="356"
                                                    src="{{ $imgUrl }}"
                                                    alt="{{ $product->name }}"
                                                    onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
                                                <span class="remove" onclick="removeFromCompare({{ $product->id }}, this)" style="cursor:pointer;">
                                                    <i class="icon icon-trash"></i>
                                                </span>
                                            </div>
                                            <a href="{{ $productUrl }}" class="item_name fw-medium lh-24 link">
                                                {{ $product->name }}
                                            </a>
                                            <p class="item_type text-caption-01 cl-text-2">
                                                {{ $product->category->name ?? '' }}
                                            </p>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>

                            {{-- Рейтинг --}}
                            <tr class="compare-row">
                                <td class="compare-col compare-title">Рейтинг</td>
                                @foreach ($products as $product)
                                    <td class="compare-col">
                                        @if ($product->rating > 0)
                                            <div class="compare_rate">
                                                <div class="star-wrap normal d-flex align-items-center">
                                                    @for ($s = 1; $s <= 5; $s++)
                                                        <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : ' cl-text-line' }}"></i>
                                                    @endfor
                                                </div>
                                                <span class="rate_count">({{ number_format($product->rating, 1) }})</span>
                                            </div>
                                        @else
                                            <span class="cl-text-3">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Цена --}}
                            <tr class="compare-row">
                                <td class="compare-col compare-title">Цена</td>
                                @foreach ($products as $product)
                                    <td class="compare-col compare-value">
                                        @if ($product->price > 0)
                                            <span class="text-primary fw-semibold">
                                                {{ number_format($product->price, 2, '.', ' ') }} BYN
                                            </span>
                                        @else
                                            <span class="cl-text-3">По запросу</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Бренд --}}
                            <tr class="compare-row">
                                <td class="compare-col compare-title">Бренд</td>
                                @foreach ($products as $product)
                                    <td class="compare-col compare-value">
                                        <span>{{ $product->brand->name ?? '—' }}</span>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Наличие --}}
                            <tr class="compare-row">
                                <td class="compare-col compare-title">Наличие</td>
                                @foreach ($products as $product)
                                    <td class="compare-col compare-value">
                                        @if ($product->in_stock)
                                            <span class="text-success">В наличии</span>
                                        @else
                                            <span class="cl-text-3">Под заказ</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Атрибуты из БД --}}
                            @foreach ($allAttributes as $attr)
                                <tr class="compare-row">
                                    <td class="compare-col compare-title">{{ $attr['name'] }}</td>
                                    @foreach ($products as $product)
                                        @php
                                            $val = $product->attributeValues
                                                ->first(fn($v) => $v->attribute_id === $attr['id']);
                                        @endphp
                                        <td class="compare-col compare-value">
                                            <span>
                                                @if ($val)
                                                    {{ $val->option->name ?? $val->value ?? '—' }}
                                                    @if ($val->attribute && $val->attribute->suffix)
                                                        {{ $val->attribute->suffix }}
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            {{-- Кнопка в корзину --}}
                            <tr class="compare-row">
                                <td class="compare-col compare-title">Корзина</td>
                                @foreach ($products as $product)
                                    <td class="compare-col compare-value">
                                        <a href="#shoppingCart" class="tf-btn s-small animate-btn"
                                            data-bs-toggle="offcanvas">
                                            <span class="text-caption-01">В корзину</span>
                                        </a>
                                    </td>
                                @endforeach
                            </tr>

                        </tbody>
                    </table>
                </div>

            @else

                {{-- Пусто --}}
                <div class="text-center py-60">
                    <i class="icon icon-ArrowsLeftRight fs-48 cl-text-3 mb-16"></i>
                    <p class="h5 cl-text-2 mb-8">Список сравнения пуст</p>
                    <p class="text-body-1 cl-text-3 mb-24">
                        Добавьте товары для сравнения с карточки товара
                    </p>
                    <a href="/catalog" class="tf-btn btn-primary">Перейти в каталог</a>
                </div>

            @endif

        </div>
    </div>

</main>

@push('scripts')
<script>
function removeFromCompare(productId, btn) {
    fetch('/compare/remove', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(() => window.location.reload());
}
</script>
@endpush

@endsection
