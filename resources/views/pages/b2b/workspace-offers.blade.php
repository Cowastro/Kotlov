@if($offers->isNotEmpty())
    @php $displayOffer = $offers->firstWhere('supplier_key', data_get($savedLine, 'workspace_purchase.supplier_key')) ?? $offers->first(); @endphp
    <label class="b2b-workspace-supplier"><span class="visually-hidden">Поставщик: {{ $product->name }}</span>
        <select data-workspace-offer data-product-id="{{ $product->id }}" aria-label="Поставщик: {{ $product->name }}">
            @foreach($offers as $offer)
                <option value="{{ $offer['supplier_key'] }}" data-order-rules='@json($offer['order_rules'] ?? [])' data-rules-label="{{ \App\Services\Suppliers\SupplierOrderRules::label($offer['order_rules'] ?? []) }}" data-confirmed="{{ $offer['is_fresh'] && $offer['in_stock'] ? 1 : 0 }}" data-stock-label="{{ $offer['stock'].($offer['warehouse'] ? ' · '.$offer['warehouse'] : '').(! $offer['is_fresh'] ? ' · проверить остаток' : '') }}" data-cost="{{ $offer['purchase_price'] }}" data-margin="{{ $offer['margin'] }}" data-margin-percent="{{ $offer['margin_percent'] }}" @selected(data_get($savedLine, 'workspace_purchase.supplier_key') === $offer['supplier_key'])>{{ $offer['supplier'] }} · {{ $offer['stock'] }}{{ ! $offer['is_fresh'] ? ' · проверить остаток' : '' }}</option>
            @endforeach
        </select>
    </label>
    <small class="b2b-workspace-selected-stock {{ $displayOffer['is_fresh'] && $displayOffer['in_stock'] ? 'is-confirmed' : '' }}" data-workspace-stock>{{ $displayOffer['stock'] }}@if($displayOffer['warehouse']) · {{ $displayOffer['warehouse'] }}@endif{{ ! $displayOffer['is_fresh'] ? ' · проверить остаток' : '' }}</small>
    <details class="b2b-workspace-offers">
        <summary>Поставщики и склады · {{ $offers->count() }}</summary>
        @foreach($offers as $offer)
            <article><strong>{{ $offer['supplier'] }}</strong><span>Входная: {{ $offer['purchase_price'] !== null ? number_format($offer['purchase_price'], 2, ',', ' ').' BYN' : 'уточняется' }}@if($offer['margin'] !== null) · Маржа {{ number_format($offer['margin'], 2, ',', ' ') }} BYN / {{ number_format($offer['margin_percent'], 1, ',', ' ') }}%@endif</span>
                <span>{{ $offer['stock'] }}{{ ! $offer['is_fresh'] ? ' · остаток требует проверки' : '' }}</span>
                @if($offer['article'])<span>Арт. поставщика: {{ $offer['article'] }}</span>@endif
                @if(!empty($offer['order_rules']))<span>{{ \App\Services\Suppliers\SupplierOrderRules::label($offer['order_rules']) }}</span>@endif
                @if(!empty($offer['catalog_code']))<span>Код каталога: {{ $offer['catalog_code'] }}</span>@endif
                @if($offer['warehouse'])<span>Склад: {{ $offer['warehouse'] }}</span>@endif
                @foreach(($offer['warehouse_breakdown'] ?? []) as $warehouse)<span>{{ $warehouse }}</span>@endforeach
                @if(count($offer['locations'] ?? []) > 1)@foreach($offer['locations'] as $location)<span>{{ $location['warehouse'] ?: 'Склад не указан' }}: {{ $location['stock'] }}{{ ! $location['is_fresh'] ? ' · проверить' : '' }}</span>@endforeach @endif
                @if($offer['updated_at'])<small>Обновлено {{ $offer['updated_at'] }}</small>@endif
            </article>
        @endforeach
    </details>
@else
    <small class="b2b-workspace-price-note">Поставщик и входная цена не привязаны</small>
@endif
