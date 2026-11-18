{{-- Staff workspace: the items of a category as a table, a row per kind and a column per diameter.
     A cell is a quantity field of the selection, saved by b2b-workspace.js on change or Enter. --}}
@php
    $gridCell = function ($product) use ($workspaceLines, $workspaceOffers) {
        $line = $workspaceLines->get($product->id);
        $offer = $workspaceOffers->get($product->id, collect())->first();
        $cost = $offer['purchase_price'] ?? null;
        $title = $product->name.($product->sku ? ' · '.$product->sku : '')
            .($cost !== null ? ' · входная '.number_format($cost, 2, ',', ' ') : '')
            .(isset($offer['stock']) ? ' · '.$offer['stock'] : '');

        return [
            'line' => $line,
            'title' => $title,
            'price' => (float) $product->price > 0 ? number_format((float) $product->price, 2, ',', ' ') : '—',
            'stock' => ! $offer ? 'none' : (! empty($offer['in_stock']) ? 'yes' : 'no'),
        ];
    };
@endphp
<div class="b2b-diameter-grid" data-diameter-grid>
    @foreach ($diameterGrid['tables'] as $table)
    <div class="b2b-diameter-grid__scroll">
        <table>
            <thead>
                <tr>
                    <th scope="col">Изделие</th>
                    @foreach ($table['columns'] as $diameter)<th scope="col">Ø {{ $diameter }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($table['rows'] as $kind => $cells)
                    <tr>
                        <th scope="row">{{ $kind }}</th>
                        @foreach ($table['columns'] as $diameter)
                            @php $product = $cells[$diameter] ?? null; @endphp
                            <td>
                                @if ($product)
                                    @php $cell = $gridCell($product); @endphp
                                    <div class="b2b-grid-cell{{ $cell['line'] ? ' is-selected' : '' }}" data-workspace-product="{{ $product->id }}" title="{{ $cell['title'] }}">
                                        <input type="number" min="0" max="999" step="1" inputmode="numeric" placeholder="0" value="{{ $cell['line']['quantity'] ?? '' }}"
                                            aria-label="Количество: {{ $product->name }}" data-workspace-quantity>
                                        <small><i class="is-{{ $cell['stock'] }}"></i>{{ $cell['price'] }}</small>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
    @if ($diameterGrid['rest'] !== [])
        <h3 class="b2b-diameter-grid__rest-title">Без диаметра в названии · {{ count($diameterGrid['rest']) }}</h3>
        <ul class="b2b-diameter-grid__rest">
            @foreach ($diameterGrid['rest'] as $product)
                @php $cell = $gridCell($product); @endphp
                <li>
                    <span title="{{ $cell['title'] }}">{{ $product->name }}</span>
                    <div class="b2b-grid-cell{{ $cell['line'] ? ' is-selected' : '' }}" data-workspace-product="{{ $product->id }}" title="{{ $cell['title'] }}">
                        <input type="number" min="0" max="999" step="1" inputmode="numeric" placeholder="0" value="{{ $cell['line']['quantity'] ?? '' }}"
                            aria-label="Количество: {{ $product->name }}" data-workspace-quantity>
                        <small><i class="is-{{ $cell['stock'] }}"></i>{{ $cell['price'] }}</small>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="b2b-diameter-grid__hint">Количество сохраняется в подбор сразу · Enter — следующая ячейка · 0 — убрать из подбора</p>
</div>
<style>
    .b2b-diameter-grid { margin-top: 8px; }
    .b2b-diameter-grid__scroll + .b2b-diameter-grid__scroll { margin-top: 14px; }
    .b2b-diameter-grid__scroll { overflow: auto; max-height: calc(100vh - 220px); border: 1px solid #e3e6ea; border-radius: 10px; background: #fff; }
    .b2b-diameter-grid table { border-collapse: separate; border-spacing: 0; width: 100%; font-size: 13px; }
    .b2b-diameter-grid th, .b2b-diameter-grid td { border-bottom: 1px solid #eef0f2; border-right: 1px solid #eef0f2; padding: 6px; vertical-align: middle; }
    .b2b-diameter-grid thead th { position: sticky; top: 0; z-index: 2; background: #f6f7f9; font-weight: 600; text-align: center; white-space: nowrap; }
    .b2b-diameter-grid tbody th { position: sticky; left: 0; z-index: 1; background: #fff; text-align: left; font-weight: 500; min-width: 220px; max-width: 320px; }
    .b2b-diameter-grid thead th:first-child { left: 0; z-index: 3; text-align: left; }
    .b2b-diameter-grid tbody tr:hover th, .b2b-diameter-grid tbody tr:hover td { background: #fafbfc; }
    .b2b-grid-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 64px; }
    .b2b-grid-cell input { width: 56px; height: 30px; text-align: center; border: 1px solid #d5d9de; border-radius: 6px; font: inherit; }
    .b2b-grid-cell input:focus { outline: 2px solid #c8102e33; border-color: #c8102e; }
    .b2b-grid-cell.is-selected input { background: #fdecef; border-color: #c8102e; font-weight: 700; }
    .b2b-grid-cell small { color: #6b7280; font-size: 11px; white-space: nowrap; display: flex; align-items: center; gap: 3px; }
    .b2b-grid-cell i { width: 6px; height: 6px; border-radius: 50%; background: #cbd0d6; display: inline-block; }
    .b2b-grid-cell i.is-yes { background: #1f9d55; }
    .b2b-grid-cell i.is-no { background: #e3a008; }
    .b2b-diameter-grid__rest-title { font-size: 14px; margin: 16px 0 6px; }
    .b2b-diameter-grid__rest { list-style: none; margin: 0; padding: 0; border: 1px solid #e3e6ea; border-radius: 10px; background: #fff; }
    .b2b-diameter-grid__rest li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 6px 10px; border-bottom: 1px solid #eef0f2; font-size: 13px; }
    .b2b-diameter-grid__rest li:last-child { border-bottom: 0; }
    .b2b-diameter-grid__rest .b2b-grid-cell { flex-direction: row; }
    .b2b-diameter-grid__hint { color: #6b7280; font-size: 12px; margin: 8px 0 0; }
    .b2b-grid-toggle { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid #d5d9de; border-radius: 8px; font-size: 13px; color: inherit; text-decoration: none; background: #fff; }
    @media (max-width: 767px) { .b2b-diameter-grid tbody th { min-width: 130px; max-width: 160px; font-size: 12px; } .b2b-grid-cell { min-width: 52px; } .b2b-grid-cell input { width: 46px; } }
    .b2b-grid-toggle:hover { border-color: #c8102e; color: #c8102e; }
</style>
