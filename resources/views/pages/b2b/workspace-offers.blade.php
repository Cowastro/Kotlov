@if($offers->isNotEmpty())
    <div class="b2b-workspace-supply{{ $offers->count() === 1 ? ' is-single' : '' }}">
    @php $displayOffer = $offers->firstWhere('supplier_key', data_get($savedLine, 'workspace_purchase.supplier_key')) ?? $offers->first(); @endphp
    <label class="b2b-workspace-supplier"><span class="visually-hidden">Поставщик: {{ $product->name }}</span>
        <span class="b2b-workspace-supplier-caption" data-workspace-supplier-caption aria-hidden="true" title="{{ $displayOffer['supplier'] }}">{{ $displayOffer['supplier'] }}</span>
        <select data-workspace-offer data-product-id="{{ $product->id }}" aria-label="Поставщик: {{ $product->name }}">
            @foreach($offers as $offer)
                @php $stockIndicator = \App\Services\Suppliers\SupplierStockIndicator::fromOffer($offer); @endphp
                <option data-stock-indicator='@json($stockIndicator)' value="{{ $offer['supplier_key'] }}" data-supplier-name="{{ $offer['supplier'] }}" data-order-rules='@json($offer['order_rules'] ?? [])' data-rules-label="{{ \App\Services\Suppliers\SupplierOrderRules::label($offer['order_rules'] ?? []) }}" data-confirmed="{{ $offer['is_fresh'] && $offer['in_stock'] ? 1 : 0 }}" data-stock-label="{{ $offer['stock'].($offer['warehouse'] ? ' · '.$offer['warehouse'] : '').(! $offer['is_fresh'] ? ' · проверить остаток' : '') }}" data-cost="{{ $offer['purchase_price'] }}" data-margin="{{ $offer['margin'] }}" data-margin-percent="{{ $offer['margin_percent'] }}" @selected(data_get($savedLine, 'workspace_purchase.supplier_key') === $offer['supplier_key'])>{{ $offer['supplier'] }} · {{ $offer['stock'] }}{{ ! $offer['is_fresh'] ? ' · проверить остаток' : '' }}</option>
            @endforeach
        </select>
    </label>
    @php $stockIndicator = \App\Services\Suppliers\SupplierStockIndicator::fromOffer($displayOffer); @endphp
    <span class="b2b-stock-meter is-{{ $stockIndicator['state'] }}" data-workspace-stock-meter data-level="{{ $stockIndicator['level'] }}" title="{{ $stockIndicator['title'] }}"><span class="b2b-stock-bars" aria-hidden="true"><i></i><i></i><i></i></span><span data-workspace-stock>{{ $stockIndicator['label'] }}</span></span>
    <details class="b2b-workspace-offers">
        <summary><span class="b2b-offers-full-label">Поставщики и склады</span><span class="b2b-offers-short-label">Склады</span> · {{ $offers->count() }}</summary>
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
    </div>
@else
    <small class="b2b-workspace-price-note">Поставщик и входная цена не привязаны</small>
@endif
