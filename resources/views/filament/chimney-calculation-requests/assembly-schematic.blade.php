<div class="chimney-admin-schematic">
    <style>
        .chimney-admin-schematic{max-width:760px;margin:0 auto 1rem;padding:1.25rem;border:1px solid rgba(148,163,184,.28);border-radius:1rem;background:rgba(148,163,184,.06)}
        .chimney-admin-schematic__head{text-align:center}.chimney-admin-schematic__head strong{display:block;font-size:1rem}.chimney-admin-schematic__head span{display:block;margin-top:.25rem;color:#94a3b8;font-size:.75rem}
        .chimney-admin-schematic__list{display:grid;justify-items:center;margin-top:1rem}.chimney-admin-schematic__item{display:grid;width:min(100%,620px);grid-template-columns:4rem minmax(0,1fr) auto;gap:.875rem;align-items:center;padding:.75rem 1rem;border:1px solid rgba(148,163,184,.28);border-radius:.875rem;background:rgba(255,255,255,.04)}
        .chimney-admin-schematic__item img{width:4rem;height:4rem;object-fit:contain;border-radius:.5rem;background:#fff}.chimney-admin-schematic__item b{display:block;line-height:1.3}.chimney-admin-schematic__item small{display:block;margin-top:.25rem;color:#94a3b8}.chimney-admin-schematic__qty{white-space:nowrap;font-weight:700}
        .chimney-admin-schematic__controls{display:flex;align-items:center;justify-content:flex-end;gap:.35rem}.chimney-admin-schematic__controls button{display:grid;width:2rem;height:2rem;place-items:center;border:1px solid rgba(148,163,184,.4);border-radius:.5rem}.chimney-admin-schematic__remove{color:#ef4444}
        .chimney-admin-schematic__arrow{height:1.5rem;color:#94a3b8;font-size:1.25rem;line-height:1.5rem}.chimney-admin-schematic__end{margin-top:.25rem;color:#94a3b8;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
        @media(max-width:640px){.chimney-admin-schematic{padding:.875rem}.chimney-admin-schematic__item{grid-template-columns:3.25rem minmax(0,1fr);padding:.625rem}.chimney-admin-schematic__item img{width:3.25rem;height:3.25rem}.chimney-admin-schematic__qty{grid-column:2}}
    </style>

    <div class="chimney-admin-schematic__head">
        <strong>Схема сборки дымохода</strong>
        <span>Последовательность сверху вниз — от завершения дымохода к оборудованию</span>
    </div>

    <div class="chimney-admin-schematic__list">
        <div class="chimney-admin-schematic__end">Верх дымохода</div>
        @forelse($items as $item)
            <div class="chimney-admin-schematic__arrow" aria-hidden="true">↓</div>
            <div class="chimney-admin-schematic__item">
                <img src="{{ $item['image'] }}" alt="">
                <div>
                    <b>{{ $item['name'] }}</b>
                    @if($item['sku'] !== '')<small>Артикул: {{ $item['sku'] }}</small>@endif
                </div>
                @if($editable)
                    <div class="chimney-admin-schematic__controls">
                        <button type="button" wire:click="changeItemQuantity({{ $item['index'] }}, -1)" title="Уменьшить">−</button>
                        <span class="chimney-admin-schematic__qty">{{ $item['quantity'] }} шт.</span>
                        <button type="button" wire:click="changeItemQuantity({{ $item['index'] }}, 1)" title="Увеличить">+</button>
                        <button type="button" class="chimney-admin-schematic__remove" wire:click="removeItem({{ $item['index'] }})" title="Удалить">×</button>
                    </div>
                @else
                    <span class="chimney-admin-schematic__qty">{{ $item['quantity'] }} шт.</span>
                @endif
            </div>
        @empty
            <p>Состав комплекта не сохранён.</p>
        @endforelse
        <div class="chimney-admin-schematic__arrow" aria-hidden="true">↓</div>
        <div class="chimney-admin-schematic__end">Печь / оборудование</div>
    </div>
    @if($editable)
        <div style="display:flex;justify-content:center;margin-top:1rem">
            <x-filament::button type="button" color="gray" icon="heroicon-o-plus" wire:click="mountAction('addItem')">Добавить элемент «Теплов и Сухов»</x-filament::button>
        </div>
    @endif
</div>
