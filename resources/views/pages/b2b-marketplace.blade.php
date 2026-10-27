@extends('layouts.amerce')

@section('content')
<main id="wrapper" class="b2b-marketplace">
    <section class="b2b-marketplace__hero">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-16">
                <div>
                    <p class="b2b-marketplace__eyebrow mb-8">B2B · {{ auth()->user()->company_name ?: auth()->user()->name }}</p>
                    <h2 class="mb-8">Оптовый каталог</h2>
                    <p class="mb-0">Ваши цены, актуальные остатки и быстрая сборка заявки.</p>
                </div>
                <div class="d-flex gap-8">
                    <a href="{{ route('account') }}" class="tf-btn btn-white">Заказы</a>
                    <a href="{{ route('cart') }}" class="tf-btn btn-primary"><i class="icon icon-Handbag"></i> Корзина</a>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="b2b-marketplace__tabs mb-20">
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'orderable', 'page' => null]) }}" class="{{ $mode === 'orderable' ? 'active' : '' }}">
                    Доступно к заказу
                </a>
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'reference', 'page' => null, 'source' => null]) }}" class="{{ $mode === 'reference' ? 'active' : '' }}">
                    Справочный каталог
                </a>
            </div>

            <form method="GET" action="{{ route('b2b.catalog') }}" class="b2b-marketplace__filters mb-24">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <label class="b2b-marketplace__search">
                    <span>Поиск</span>
                    <input type="search" name="q" value="{{ $query }}" placeholder="Название, артикул, бренд">
                </label>
                @if ($categoryId > 0)<input type="hidden" name="category" value="{{ $categoryId }}">@endif
                <label>
                    <span>Бренд</span>
                    <select name="brand">
                        <option value="">Все бренды</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected($brandId === (int) $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($mode === 'orderable' && $sourceGroups->count() > 1)
                    <label>
                        <span>Поставщик</span>
                        <select name="source">
                            <option value="">Все поставщики</option>
                            @foreach ($sourceGroups as $sourceGroup)
                                <option value="{{ $sourceGroup['key'] }}" @selected($sourceKey === $sourceGroup['key'])>{{ $sourceGroup['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($mode === 'orderable')
                    <label>
                        <span>Сортировка</span>
                        <select name="sort">
                            <option value="name" @selected($sort === 'name')>По названию</option>
                            <option value="stock" @selected($sort === 'stock')>Больше остаток</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Цена по возрастанию</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Цена по убыванию</option>
                        </select>
                    </label>
                @endif
                <button class="tf-btn btn-primary" type="submit"><i class="icon icon-MagnifyingGlass"></i> Найти</button>
            </form>

            <div class="b2b-catalog-layout">
                <aside class="b2b-category-panel">
                    <div class="b2b-category-panel__heading">
                        <strong>Каталог</strong>
                        @if ($categoryId > 0)
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}">Сбросить</a>
                        @endif
                    </div>
                    <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}" class="b2b-category-all {{ $categoryId === 0 ? 'active' : '' }}">
                        <span>Все категории</span>
                    </a>
                    <div class="b2b-category-tree">
                        @include('partials.b2b-category-tree', ['nodes' => $categoryTree, 'level' => 0])
                    </div>
                </aside>

                <div class="b2b-catalog-results">
                    <div class="d-flex align-items-center justify-content-between gap-12 mb-16">
                        <p class="mb-0 cl-text-2">Найдено: <strong>{{ $products->total() }}</strong></p>
                        @if ($mode === 'reference')
                            <p class="mb-0 text-caption-01 cl-text-3">Эти позиции показаны для информации и не добавляются в B2B-заявку.</p>
                        @endif
                    </div>

                    @if ($products->count())
                        <div class="b2b-marketplace__list">
                    @foreach ($products as $item)
                        @php
                            $product = $mode === 'orderable' ? $item['product'] : $item;
                            $productUrl = '/'.($product->category?->slug ?? 'catalog').'/'.$product->slug;
                            $priceRequest = $priceRequests->get($product->id);
                            $priceRequestIsOpen = $priceRequest && in_array($priceRequest->status, \App\Models\ContactRequest::OPEN_STATUSES, true);
                        @endphp
                        <article class="b2b-marketplace__row">
                            <a href="{{ $productUrl }}" class="b2b-marketplace__image">
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
                            </a>
                            <div class="b2b-marketplace__product">
                                <p class="text-caption-01 cl-text-3 mb-4">{{ $product->category?->name }} @if($product->sku) · {{ $product->sku }} @endif</p>
                                <a href="{{ $productUrl }}" class="h6 fw-semibold link">{{ $product->name }}</a>
                                @if ($product->brand)
                                    <p class="text-caption-01 cl-text-2 mt-4 mb-0">{{ $product->brand->name }}</p>
                                @endif
                            </div>

                            @if ($mode === 'orderable')
                                <div class="b2b-marketplace__price">
                                    <span>Ваша цена</span>
                                    <strong>{{ number_format($item['price'], 2, ',', ' ') }} BYN</strong>
                                    @if ($item['retail_price'] > 0)
                                        <small>Розница: {{ number_format($item['retail_price'], 2, ',', ' ') }} BYN</small>
                                    @endif
                                </div>
                                <div class="b2b-marketplace__stock">
                                    <span>{{ $item['offer']->source?->partnerName() }}</span>
                                    <strong>{{ $item['stock_label'] }}</strong>
                                </div>
                                <div class="b2b-marketplace__buy">
                                    <input id="b2b-qty-{{ $product->id }}" type="number" value="1" min="1" max="999" aria-label="Количество">
                                    <button type="button" class="tf-btn btn-primary btn-add-to-cart"
                                        data-product-id="{{ $product->id }}"
                                        data-qty-input="#b2b-qty-{{ $product->id }}">
                                        <i class="icon icon-Handbag"></i> В корзину
                                    </button>
                                </div>
                            @else
                                <div class="b2b-marketplace__reference-price">
                                    <span>Розничная цена</span>
                                    <strong>{{ (float) $product->price > 0 ? number_format((float) $product->price, 2, ',', ' ').' BYN' : 'Уточняется' }}</strong>
                                </div>
                                @if ($priceRequest)
                                    <div class="b2b-price-request-state" data-price-request-state="{{ $product->id }}">
                                        <span>Цена запрошена</span>
                                        <strong>{{ \App\Models\ContactRequest::$statuses[$priceRequest->status] ?? $priceRequest->status }}</strong>
                                        <small>{{ $priceRequest->created_at->format('d.m.Y H:i') }} · {{ $priceRequest->requested_quantity ?: 1 }} {{ $product->unit ?: 'шт.' }}</small>
                                        @if ($priceRequest->client_response)
                                            <p>{{ $priceRequest->client_response }}</p>
                                        @elseif ($priceRequestIsOpen)
                                            <p>Менеджер проверяет цену и срок поставки.</p>
                                        @endif
                                    </div>
                                @endif
                                @if (! $priceRequestIsOpen)
                                    <form class="b2b-marketplace__buy b2b-price-request-form" data-product-id="{{ $product->id }}">
                                        <input id="b2b-request-qty-{{ $product->id }}" name="quantity" type="number" value="1" min="1" max="999" aria-label="Количество">
                                        <button type="submit" class="tf-btn btn-primary">Запросить оптовую цену</button>
                                        @if ($priceRequest)<small>Повторный запрос, если изменилось количество.</small>@endif
                                    </form>
                                @endif
                            @endif
                        </article>
                    @endforeach
                        </div>

                        @include('partials.b2b-pagination', ['paginator' => $products])
                    @else
                        <div class="b2b-marketplace__empty">
                            <i class="icon icon-MagnifyingGlass"></i>
                            <h5>Позиции не найдены</h5>
                            <p>Измените запрос или сбросьте фильтры.</p>
                            <a href="{{ route('b2b.catalog', ['mode' => $mode]) }}" class="tf-btn btn-primary">Сбросить фильтры</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</main>

@push('styles')
<style>
.b2b-marketplace__hero { padding: 42px 0; color: #fff; background: linear-gradient(135deg,#101820,#253445); }
.b2b-marketplace__hero p { color: rgba(255,255,255,.72); }
.b2b-marketplace__eyebrow { color: #f4c430 !important; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.b2b-marketplace__tabs { display:flex; gap:8px; padding:5px; width:fit-content; border-radius:12px; background:#f1f3f5; }
.b2b-marketplace__tabs a { padding:10px 16px; border-radius:9px; color:#555; font-weight:700; }
.b2b-marketplace__tabs a.active { background:#111; color:#fff; }
.b2b-marketplace__filters { display:grid; grid-template-columns:minmax(280px,2fr) repeat(3,minmax(160px,1fr)) auto; align-items:end; gap:10px; padding:16px; border:1px solid #e6e8eb; border-radius:14px; background:#fafbfc; }
.b2b-marketplace__filters label span,.b2b-marketplace__price span,.b2b-marketplace__stock span,.b2b-marketplace__reference-price span { display:block; margin-bottom:5px; color:#777; font-size:12px; }
.b2b-marketplace__filters input,.b2b-marketplace__filters select { width:100%; height:44px; padding:0 12px; border:1px solid #dfe2e6; border-radius:8px; background:#fff; }
.b2b-marketplace__list { border:1px solid #e6e8eb; border-radius:14px; overflow:hidden; }
.b2b-marketplace__row { display:grid; grid-template-columns:72px minmax(220px,2fr) minmax(150px,1fr) minmax(150px,1fr) minmax(180px,auto); align-items:center; gap:16px; padding:14px 16px; background:#fff; }
.b2b-marketplace__row + .b2b-marketplace__row { border-top:1px solid #eceef0; }
.b2b-marketplace__image { width:72px; height:72px; border-radius:9px; overflow:hidden; background:#f5f5f5; }
.b2b-marketplace__image img { width:100%; height:100%; object-fit:contain; }
.b2b-marketplace__price strong,.b2b-marketplace__stock strong,.b2b-marketplace__reference-price strong { display:block; color:#111; }
.b2b-marketplace__price small { display:block; margin-top:3px; color:#888; }
.b2b-marketplace__stock strong { color:#16803a; }
.b2b-marketplace__buy { display:flex; gap:8px; }
.b2b-marketplace__buy input { width:62px; min-height:44px; padding:0 8px; border:1px solid #dfe2e6; border-radius:8px; text-align:center; }
.b2b-marketplace__reference-badge { justify-self:end; padding:7px 10px; border-radius:999px; color:#666; background:#f0f1f2; font-size:12px; font-weight:700; }
.b2b-price-request-state { padding:10px 12px; border-radius:10px; background:#fff7d6; }
.b2b-price-request-state span,.b2b-price-request-state strong,.b2b-price-request-state small { display:block; }
.b2b-price-request-state span,.b2b-price-request-state small { color:#806514; font-size:12px; }
.b2b-price-request-state strong { margin:2px 0; color:#5f4a08; }
.b2b-price-request-state p { margin:8px 0 0; color:#4d3d0a; font-size:13px; line-height:1.45; }
.b2b-price-request-form { flex-wrap:wrap; }
.b2b-price-request-form small { flex-basis:100%; color:#777; }
.b2b-marketplace__empty { padding:56px 20px; border:1px dashed #d9dde1; border-radius:14px; text-align:center; }
.b2b-marketplace__empty i { display:block; margin-bottom:14px; font-size:42px; color:#aaa; }
.b2b-catalog-layout { display:grid; grid-template-columns:260px minmax(0,1fr); align-items:start; gap:20px; }
.b2b-category-panel { position:sticky; top:20px; max-height:calc(100vh - 40px); padding:14px; border:1px solid #e6e8eb; border-radius:14px; background:#fff; overflow:auto; }
.b2b-category-panel__heading { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.b2b-category-panel__heading strong { font-size:18px; }
.b2b-category-panel__heading a { color:#d93d3d; font-size:12px; }
.b2b-category-all,.b2b-category-leaf,.b2b-category-branch summary { display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:36px; padding:7px 9px; border-radius:8px; color:#25282b; cursor:pointer; }
.b2b-category-all:hover,.b2b-category-leaf:hover,.b2b-category-branch summary:hover { background:#f5f6f7; }
.b2b-category-all.active,.b2b-category-leaf.active,.b2b-category-branch.selected > summary { color:#fff; background:#17191b; }
.b2b-category-tree { margin-top:4px; }
.b2b-category-tree__nested { margin-left:12px; padding-left:9px; border-left:1px solid #e4e6e8; }
.b2b-category-branch summary { list-style:none; font-weight:650; }
.b2b-category-branch summary::-webkit-details-marker { display:none; }
.b2b-category-branch summary::before { content:'›'; flex:0 0 auto; transition:transform .15s ease; }
.b2b-category-branch[open] > summary::before { transform:rotate(90deg); }
.b2b-category-branch summary span:first-of-type { flex:1; }
.b2b-category-count { flex:0 0 auto; min-width:24px; color:#777; font-size:11px; text-align:right; }
.b2b-category-branch.selected > summary .b2b-category-count { color:#fff; }
.b2b-category-section-link { display:block; margin:3px 0 3px 19px; padding:5px 8px; color:#d93d3d; font-size:12px; }
.b2b-pagination { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:24px; }
.b2b-pagination__summary { color:#74787d; font-size:13px; }
.b2b-pagination__pages { display:flex; align-items:center; gap:5px; }
.b2b-pagination__link,.b2b-pagination__current,.b2b-pagination__disabled { display:grid; place-items:center; min-width:40px; height:40px; padding:0 10px; border:1px solid #dfe2e6; border-radius:9px; background:#fff; color:#222; }
.b2b-pagination__link:hover { border-color:#e44343; color:#e44343; }
.b2b-pagination__current { border-color:#17191b; color:#fff; background:#17191b; }
.b2b-pagination__disabled { color:#b6b9bd; background:#f7f8f9; }
.b2b-pagination__ellipsis { padding:0 4px; color:#8b8e92; }
@media(max-width:1199px){.b2b-marketplace__filters{grid-template-columns:repeat(3,1fr)}.b2b-catalog-layout{grid-template-columns:220px minmax(0,1fr)}.b2b-marketplace__row{grid-template-columns:64px minmax(190px,2fr) 1fr 1fr}.b2b-marketplace__buy,.b2b-marketplace__reference-badge{grid-column:2/-1;justify-self:start}.b2b-marketplace__image{width:64px;height:64px}}
@media(max-width:900px){.b2b-catalog-layout{grid-template-columns:1fr}.b2b-category-panel{position:static;max-height:340px}.b2b-category-tree{columns:2}.b2b-category-branch,.b2b-category-leaf{break-inside:avoid}}
@media(max-width:767px){.b2b-marketplace__filters{grid-template-columns:1fr}.b2b-marketplace__tabs{width:100%;overflow:auto}.b2b-marketplace__tabs a{white-space:nowrap}.b2b-category-tree{columns:1}.b2b-marketplace__row{grid-template-columns:58px 1fr;gap:12px}.b2b-marketplace__image{width:58px;height:58px}.b2b-marketplace__price,.b2b-marketplace__stock,.b2b-marketplace__reference-price,.b2b-marketplace__buy,.b2b-marketplace__reference-badge{grid-column:1/-1}.b2b-marketplace__buy .tf-btn{flex:1}.b2b-pagination{align-items:flex-start;flex-direction:column}.b2b-pagination__pages{max-width:100%;overflow:auto}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('submit', function (event) {
    var form = event.target.closest('.b2b-price-request-form');
    if (!form) return;
    event.preventDefault();

    var button = form.querySelector('button[type="submit"]');
    var quantity = form.querySelector('input[name="quantity"]');
    if (button.disabled) return;
    button.disabled = true;
    var original = button.textContent;
    button.textContent = 'Отправляем…';

    fetch('{{ route('b2b.price-requests.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            product_id: Number(form.dataset.productId),
            quantity: Math.max(1, Number(quantity.value) || 1),
        }),
    }).then(function (response) {
        return response.json().then(function (data) {
            if (!response.ok) throw new Error(data.message || 'Не удалось отправить запрос.');
            return data;
        });
    }).then(function (data) {
        form.outerHTML = '<div class="b2b-price-request-state">' +
            '<span>Цена запрошена</span>' +
            '<strong>' + (data.status_label || 'Новая') + '</strong>' +
            '<small>' + data.message + '</small>' +
            '</div>';
    }).catch(function (error) {
        window.alert(error.message);
        button.disabled = false;
        button.textContent = original;
    });
});
</script>
@endpush
@endsection
