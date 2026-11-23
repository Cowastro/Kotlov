@if ($product)
    <div style="display:flex;align-items:center;gap:12px;min-width:0">
        <img src="{{ $product->image_url }}" alt="" style="width:52px;height:52px;object-fit:contain;flex-shrink:0" loading="lazy">
        <div style="min-width:0;overflow-wrap:anywhere">
            <strong>{{ $product->sku }}</strong><br>{{ $product->name }}
            <br><a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $product]) }}" target="_blank" rel="noopener" style="text-decoration:underline">Открыть карточку для проверки ↗</a>
        </div>
    </div>
@else
    <span>Карточка не выбрана. Новая карточка не создаётся.</span>
@endif
