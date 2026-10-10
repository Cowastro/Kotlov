@php
    $formatMoney = static fn ($value): string => $value === null
        ? '—'
        : number_format((float) $value, 2, ',', ' ') . ' BYN';
@endphp

<style>
    .market-details { display: flex; flex-direction: column; gap: 1.25rem; color: #18181b; }
    .market-details__summary,
    .market-details__row { border: 1px solid #e4e4e7; border-radius: .85rem; overflow: hidden; background: #fff; }
    .market-details__summary { padding: 1rem; background: #f4f4f5; }
    .market-details__summary-line { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .market-details__strong { font-size: .9rem; font-weight: 700; color: #18181b; }
    .market-details__muted { color: #71717a; }
    .market-details__note { margin: .5rem 0 0; font-size: .75rem; line-height: 1.4; color: #71717a; }
    .market-details__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem; border-bottom: 1px solid #e4e4e7; }
    .market-details__title { font-weight: 700; color: #18181b; }
    .market-details__sku { margin-top: .25rem; font-size: .75rem; color: #71717a; }
    .market-details__position { flex: 0 0 auto; text-align: right; }
    .market-details__position-label { font-size: .875rem; font-weight: 700; color: #18181b; }
    .market-details__position-copy { margin-top: .25rem; max-width: 30rem; font-size: .75rem; color: #71717a; }
    .market-details__stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; padding: 1rem; background: #fafafa; }
    .market-details__stat { min-width: 0; padding: .75rem; border-radius: .65rem; background: #fff; border: 1px solid #e4e4e7; }
    .market-details__stat-label { font-size: .7rem; color: #71717a; }
    .market-details__stat-value { margin-top: .25rem; font-size: .875rem; font-weight: 650; color: #18181b; }
    .market-details__warning { padding: .75rem 1rem; border-top: 1px solid #fde68a; background: #fffbeb; color: #a16207; font-size: .875rem; }
    .market-details__warning + .market-details__warning { border-top-style: dashed; }
    .market-details__table-wrap { overflow-x: auto; }
    .market-details__table { width: 100%; min-width: 760px; border-collapse: collapse; text-align: left; font-size: .82rem; }
    .market-details__table th { padding: .65rem 1rem; background: #f4f4f5; color: #71717a; font-size: .7rem; font-weight: 650; }
    .market-details__table td { padding: .8rem 1rem; border-top: 1px solid #e4e4e7; vertical-align: top; }
    .market-details__table-source { font-weight: 650; color: #18181b; }
    .market-details__offer { display: block; max-width: 20rem; color: #2563eb; text-decoration: none; }
    .market-details__offer:hover { text-decoration: underline; }
    .market-details__sub { margin-top: .25rem; font-size: .7rem; color: #71717a; }
    .market-details__nowrap { white-space: nowrap; }
    .market-details__empty { padding: 1.25rem 1rem; color: #71717a; font-size: .875rem; }
    @media (max-width: 1100px) { .market-details__stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) {
        .market-details__head { flex-direction: column; }
        .market-details__position { text-align: left; }
        .market-details__stats { grid-template-columns: 1fr; }
    }
    .dark .market-details { color: #f4f4f5; }
    .dark .market-details__summary,
    .dark .market-details__row { border-color: rgba(255,255,255,.1); background: #18181b; }
    .dark .market-details__summary { background: rgba(255,255,255,.045); }
    .dark .market-details__strong,
    .dark .market-details__title,
    .dark .market-details__position-label,
    .dark .market-details__stat-value,
    .dark .market-details__table-source { color: #fafafa; }
    .dark .market-details__muted,
    .dark .market-details__note,
    .dark .market-details__sku,
    .dark .market-details__position-copy,
    .dark .market-details__stat-label,
    .dark .market-details__sub,
    .dark .market-details__empty { color: #a1a1aa; }
    .dark .market-details__head { border-color: rgba(255,255,255,.1); }
    .dark .market-details__stats { background: rgba(255,255,255,.025); }
    .dark .market-details__stat { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
    .dark .market-details__warning { border-color: rgba(245,158,11,.22); background: rgba(245,158,11,.08); color: #fbbf24; }
    .dark .market-details__table th { background: rgba(255,255,255,.05); color: #a1a1aa; }
    .dark .market-details__table td { border-color: rgba(255,255,255,.08); }
    .dark .market-details__offer { color: #60a5fa; }
</style>

<div class="market-details">
    <div class="market-details__summary">
        <div class="market-details__summary-line">
            <span class="market-details__strong">{{ $overview['label'] }}</span>
            <span class="market-details__muted">{{ $overview['description'] }}</span>
        </div>
        <p class="market-details__note">
            Рыночные предложения не являются закупочными ценами. Цена товара автоматически не изменяется.
        </p>
    </div>

    @foreach ($rows as $row)
        @php
            $indicator = $row['indicator'];
            $product = $row['product'];
            $item = $row['item'] ?? null;
            $checkedAt = $indicator['last_checked_at'] ?? $indicator['latest_observed_at'] ?? null;
        @endphp

        <section class="market-details__row">
            <div class="market-details__head">
                <div>
                    <div class="market-details__title">
                        {{ $item?->product_name ?? $product?->name ?? 'Позиция без карточки kotlov.by' }}
                    </div>
                    <div class="market-details__sku">
                        {{ $item?->product_sku ?? $product?->sku ?? 'Артикул не указан' }}
                    </div>
                </div>
                <div class="market-details__position">
                    <div class="market-details__position-label">{{ $indicator['indicator_label'] }}</div>
                    <div class="market-details__position-copy">{{ $indicator['indicator_description'] }}</div>
                </div>
            </div>

            <div class="market-details__stats">
                <div class="market-details__stat"><div class="market-details__stat-label">Наша цена</div><div class="market-details__stat-value">{{ $formatMoney($indicator['our_price']) }}</div></div>
                <div class="market-details__stat"><div class="market-details__stat-label">Медиана</div><div class="market-details__stat-value">{{ $formatMoney($indicator['median']) }}</div></div>
                <div class="market-details__stat"><div class="market-details__stat-label">Коридор</div><div class="market-details__stat-value">{{ $indicator['minimum'] === null ? '—' : $formatMoney($indicator['minimum']) . ' – ' . $formatMoney($indicator['maximum']) }}</div></div>
                <div class="market-details__stat"><div class="market-details__stat-label">Источники</div><div class="market-details__stat-value">{{ $indicator['sources_count'] }} из {{ \App\Services\Market\MarketPriceSummary::MINIMUM_SOURCES }} минимум</div></div>
                <div class="market-details__stat"><div class="market-details__stat-label">Проверено</div><div class="market-details__stat-value">{{ $checkedAt?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? 'Нет данных' }}</div></div>
            </div>

            @forelse ($indicator['warnings'] ?? [] as $warning)
                <div class="market-details__warning"><strong>{{ $warning['label'] }}.</strong> {{ $warning['description'] }}</div>
            @empty
                @if ($indicator['status'] !== 'ready')
                    <div class="market-details__warning">{{ $indicator['reason'] }}</div>
                @endif
            @endforelse

            @if ($indicator['evidence']->isNotEmpty())
                <div class="market-details__table-wrap">
                    <table class="market-details__table">
                        <thead>
                            <tr>
                                <th>Источник</th><th>Предложение</th><th>Цена</th><th>Наличие</th><th>Сопоставление</th><th>Проверено</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($indicator['evidence'] as $evidence)
                                @php($observation = $evidence['observation'])
                                <tr>
                                    <td class="market-details__table-source">{{ $observation->source?->name ?? 'Источник недоступен' }}</td>
                                    <td>
                                        <a class="market-details__offer" href="{{ $observation->url }}" target="_blank" rel="noopener noreferrer">{{ $observation->external_name ?: 'Открыть предложение' }}</a>
                                        <div class="market-details__sub">{{ $observation->external_sku ?: $observation->model }}</div>
                                    </td>
                                    <td class="market-details__nowrap">{{ $formatMoney($observation->price_byn) }}</td>
                                    <td>{{ $observation->availabilityLabel() }}</td>
                                    <td><div>{{ $evidence['label'] }}</div><div class="market-details__sub">Уверенность {{ number_format((float) $observation->match_confidence * 100, 0) }}%</div></td>
                                    <td class="market-details__nowrap">{{ $observation->observed_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="market-details__empty">Предложений рынка для этой карточки пока нет.</div>
            @endif
        </section>
    @endforeach
</div>
