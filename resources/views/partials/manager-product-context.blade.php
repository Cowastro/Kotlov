@php
    $managerContext = app(\App\Services\ManagerStorefrontProductContext::class);
    $managerOffers = $managerContext->offers($product, auth()->user());
    $compact = $compact ?? false;
    $visibleOffers = $compact ? $managerOffers->take(2) : $managerOffers;
@endphp

@if ($managerContext->canView(auth()->user()))
    <aside class="manager-product-context{{ $compact ? ' manager-product-context--compact' : '' }}" aria-label="Внутренняя информация для менеджера">
        @forelse ($visibleOffers as $offer)
            @if ($compact)
                <details class="manager-product-context__offer">
                    <summary class="manager-product-context__line">
            @else
                <div class="manager-product-context__offer">
                    <div class="manager-product-context__line">
            @endif
                    <strong>{{ $offer['supplier'] }}</strong>
                    <span class="{{ $offer['is_fresh'] ? 'is-fresh' : ($offer['in_stock'] ? 'is-stale' : 'is-empty') }}">
                        {{ $offer['stock'] }}{{ $offer['in_stock'] && ! $offer['is_fresh'] ? ' · требуется проверка' : '' }}
                    </span>
            @if ($compact)
                    </summary>
            @else
                    </div>
            @endif
                <div class="manager-product-context__meta">
                    @if ($offer['supplier_product_name'] && $offer['supplier_product_name'] !== $product->name)
                        <span class="manager-product-context__wide">У поставщика: {{ $offer['supplier_product_name'] }}</span>
                    @endif
                    @if ($offer['article'])
                        <span>Арт. поставщика: {{ $offer['article'] }}</span>
                    @endif
                    @if ($offer['purchase_price'])
                        <span>Закупка: {{ number_format($offer['purchase_price'], 2, '.', ' ') }} BYN</span>
                    @endif
                    @if ($offer['retail_price'])
                        <span>Розница: {{ number_format($offer['retail_price'], 2, '.', ' ') }} BYN</span>
                    @endif
                    @if ($offer['margin'] !== null)
                        <span>Маржа: {{ number_format($offer['margin'], 2, '.', ' ') }} BYN{{ $offer['margin_percent'] !== null ? ' · '.number_format($offer['margin_percent'], 1, '.', ' ').'%' : '' }}</span>
                    @endif
                    @if ($offer['warehouse'])
                        <span>Склад: {{ $offer['warehouse'] }}</span>
                    @endif
                    @if ($offer['delivery_days'])
                        <span>Поставка: {{ $offer['delivery_days'] }} дн.</span>
                    @endif
                    @if (! $compact && $offer['contact'])
                        <span class="manager-product-context__wide">Контакт: {{ $offer['contact'] }}</span>
                    @endif
                    @foreach (($compact ? collect($offer['phones'])->take(1) : collect($offer['phones'])) as $phone)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">Позвонить: {{ $phone }}</a>
                    @endforeach
                    @if (! $compact)
                        @foreach ($offer['emails'] as $email)
                            <a href="mailto:{{ $email }}">{{ $email }}</a>
                        @endforeach
                    @endif
                    @if ($offer['updated_at'])
                        <span title="{{ $offer['updated_at']->format('d.m.Y H:i') }}">Обновлено {{ $offer['updated_at']->diffForHumans() }}</span>
                    @endif
                </div>
            @if ($compact)
                </details>
            @else
                </div>
            @endif
        @empty
            <p class="manager-product-context__empty">{{ $compact ? 'Нет данных поставщика' : 'Поставщик и остаток ещё не привязаны.' }}</p>
        @endforelse

        @if ($compact && $managerOffers->count() > $visibleOffers->count())
            <a class="manager-product-context__more" href="{{ $productUrl ?? ('/'.($product->category->slug ?? 'catalog').'/'.$product->slug) }}">
                Ещё предложений: {{ $managerOffers->count() - $visibleOffers->count() }}
            </a>
        @endif
    </aside>
@endif
