@php
    $money = static fn ($value): string => $value === null ? '—' : number_format((float) $value, 2, ',', ' ') . ' BYN';
    $percent = static fn ($value): string => $value === null ? '—' : number_format((float) $value, 1, ',', ' ') . '%';
    $market = $recommendation['market'];
@endphp

<style>
    .price-rec { display:grid; gap:1rem; color:#18181b; }
    .price-rec__notice { padding:.8rem 1rem; border:1px solid #fde68a; border-radius:.75rem; background:#fffbeb; color:#92400e; font-size:.85rem; }
    .price-rec__grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
    .price-rec__card { padding:.85rem; border:1px solid #e4e4e7; border-radius:.75rem; background:#fafafa; }
    .price-rec__label { color:#71717a; font-size:.72rem; }
    .price-rec__value { margin-top:.25rem; font-size:1rem; font-weight:700; }
    .price-rec__explain { padding:1rem; border:1px solid #e4e4e7; border-radius:.75rem; line-height:1.55; }
    .price-rec__muted { margin-top:.3rem; color:#71717a; font-size:.78rem; }
    .dark .price-rec { color:#fafafa; }
    .dark .price-rec__card,.dark .price-rec__explain { border-color:rgba(255,255,255,.1); background:rgba(255,255,255,.035); }
    .dark .price-rec__label,.dark .price-rec__muted { color:#a1a1aa; }
    .dark .price-rec__notice { border-color:rgba(245,158,11,.25); background:rgba(245,158,11,.08); color:#fbbf24; }
    @media(max-width:760px){ .price-rec__grid { grid-template-columns:1fr; } }
</style>

<div class="price-rec">
    <div class="price-rec__notice">
        Рекомендация не меняет цену автоматически. Изменение доступно только администратору после подтверждения и записывается в аудит.
    </div>

    <div class="price-rec__grid">
        <div class="price-rec__card"><div class="price-rec__label">Текущая цена</div><div class="price-rec__value">{{ $money($recommendation['current_price']) }}</div></div>
        <div class="price-rec__card"><div class="price-rec__label">Рекомендованная цена</div><div class="price-rec__value">{{ $money($recommendation['recommended_price']) }}</div></div>
        <div class="price-rec__card"><div class="price-rec__label">Изменение</div><div class="price-rec__value">{{ $money($recommendation['price_change']) }}</div></div>
        <div class="price-rec__card"><div class="price-rec__label">Входная цена</div><div class="price-rec__value">{{ $money($recommendation['purchase_price']) }}</div><div class="price-rec__muted">{{ $recommendation['supplier_name'] ?: 'Поставщик не определён' }}</div></div>
        <div class="price-rec__card"><div class="price-rec__label">Текущая маржа</div><div class="price-rec__value">{{ $money($recommendation['current_margin_byn']) }} · {{ $percent($recommendation['current_margin_percent']) }}</div></div>
        <div class="price-rec__card"><div class="price-rec__label">Маржа после изменения</div><div class="price-rec__value">{{ $money($recommendation['recommended_margin_byn']) }} · {{ $percent($recommendation['recommended_margin_percent']) }}</div></div>
    </div>

    <div class="price-rec__explain">
        <strong>{{ $recommendation['label'] }}</strong>
        <div>{{ $recommendation['reason'] }}</div>
        <div class="price-rec__muted">
            Рынок: {{ $money($market['minimum']) }} – {{ $money($market['maximum']) }} · медиана {{ $money($market['median']) }} · {{ $market['sources_count'] }} источника.
        </div>
        @if($recommendation['purchase_price_label'])
            <div class="price-rec__muted">{{ $recommendation['purchase_price_label'] }}{{ $recommendation['source_label'] ? ' · '.$recommendation['source_label'] : '' }}</div>
        @endif
    </div>
</div>
