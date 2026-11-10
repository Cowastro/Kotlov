@php
    $stockParameters = request()->except('in_stock', 'page');
    if (! $inStockOnly) {
        $stockParameters['in_stock'] = '1';
    }
    $stockUrl = url()->current().($stockParameters ? '?'.http_build_query($stockParameters) : '').'#catalog-products';
@endphp
<a href="{{ $stockUrl }}" rel="nofollow" class="tf-btn s-small {{ $inStockOnly ? 'btn-primary' : 'btn-stroke' }}">
    {{ $inStockOnly ? 'В наличии ✓' : 'В наличии' }}
</a>
