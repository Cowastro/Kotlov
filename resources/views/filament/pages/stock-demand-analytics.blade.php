<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $rows = $this->rows();
        $periods = $this->periodOptions();
        $sources = $this->sourceOptions();
        $stockDataOptions = $this->stockDataOptions();
    @endphp

    <style>
        .stock-analysis { --sa-border: rgba(15, 23, 42, .12); --sa-card: #fff; --sa-muted: #64748b; display: grid; gap: 14px; }
        .dark .stock-analysis { --sa-border: rgba(148, 163, 184, .18); --sa-card: rgba(255,255,255,.025); --sa-muted: #94a3b8; }
        .stock-summary { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 12px; }
        .stock-card { border: 1px solid var(--sa-border); border-radius: 9px; background: var(--sa-card); }
        .stock-metric { padding: 14px 16px; }
        .stock-metric span { color: var(--sa-muted); font-size: 12px; }
        .stock-metric strong { display: block; margin-top: 5px; font-size: 25px; }
        .stock-metric[data-tone="warning"] strong { color: #d97706; }
        .stock-metric[data-tone="danger"] strong { color: #dc2626; }
        .stock-metric[data-tone="info"] strong { color: #2563eb; }
        .stock-tools { display: grid; grid-template-columns: 150px minmax(200px,1fr) 170px minmax(220px,1.2fr) auto; gap: 10px; align-items: end; padding: 13px 15px; }
        .stock-field { display: grid; gap: 5px; color: var(--sa-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .stock-input { min-height: 34px; border: 1px solid var(--sa-border); border-radius: 7px; padding: 5px 10px; background: transparent; color: inherit; font-size: 13px; text-transform: none; }
        .stock-toggle { display: inline-flex; align-items: center; gap: 7px; min-height: 34px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .stock-table-wrap { overflow: auto; max-height: min(66vh,780px); }
        .stock-table { width: 100%; min-width: 1180px; border-collapse: collapse; font-size: 12px; }
        .stock-table th { position: sticky; top: 0; z-index: 2; padding: 9px 10px; background: #f3f4f6; color: var(--sa-muted); text-align: left; white-space: nowrap; border-bottom: 1px solid var(--sa-border); }
        .dark .stock-table th { background: #202124; }
        .stock-table td { padding: 10px; vertical-align: top; border-bottom: 1px solid var(--sa-border); }
        .stock-product { min-width: 260px; font-weight: 700; line-height: 1.35; }
        .stock-muted { margin-top: 3px; color: var(--sa-muted); font-size: 11px; }
        .stock-number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .stock-recommend { color: #dc2626; font-size: 15px; font-weight: 800; }
        .stock-ok { color: #16a34a; font-weight: 700; }
        .stock-pending { color: #d97706; font-weight: 800; }
        .stock-evidence { min-width: 150px; }
        .stock-badge { display: inline-flex; align-items: center; border: 1px solid currentColor; border-radius: 999px; padding: 2px 7px; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .stock-badge[data-ready="1"] { color: #16a34a; }
        .stock-badge[data-ready="0"] { color: #d97706; }
        .stock-explanation { min-width: 360px; max-width: 520px; line-height: 1.4; }
        .stock-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 15px; color: var(--sa-muted); font-size: 12px; }
        .stock-pager { display: flex; align-items: center; gap: 8px; }
        .stock-button { border: 1px solid var(--sa-border); border-radius: 6px; padding: 6px 10px; font-weight: 700; color: inherit; }
        .stock-button:disabled { opacity: .4; }
        @media(max-width:900px){ .stock-summary{grid-template-columns:repeat(2,minmax(0,1fr));}.stock-tools{grid-template-columns:1fr 1fr;} }
        @media(max-width:600px){ .stock-summary,.stock-tools{grid-template-columns:1fr;} }
    </style>

    <div class="stock-analysis">
        <div class="stock-summary">
            @foreach ($summary as $metric)
                <div class="stock-card stock-metric" data-tone="{{ $metric['tone'] }}">
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ number_format((float) $metric['value'], 0, ',', ' ') }}{{ $metric['suffix'] }}</strong>
                </div>
            @endforeach
        </div>

        <div class="stock-card">
            <div class="stock-tools">
                <label class="stock-field">Период спроса
                    <select class="stock-input" wire:model.live="period">
                        @foreach ($periods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="stock-field">Источник собственного склада
                    <select class="stock-input" wire:model.live="sourceCode">
                        @foreach ($sources as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="stock-field">Достоверность остатка
                    <select class="stock-input" wire:model.live="stockDataFilter">
                        @foreach ($stockDataOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="stock-field">Поиск по товару или SKU
                    <input class="stock-input" type="search" wire:model.live.debounce.350ms="search" placeholder="Название или артикул">
                </label>
                <label class="stock-toggle"><input type="checkbox" wire:model.live="purchaseOnly"> Только требующие решения</label>
            </div>

            <div class="stock-table-wrap">
                <table class="stock-table">
                    <thead><tr>
                        <th>Товар</th><th>Заказы / продажи</th><th>Спрос / мес.</th><th>Склад</th><th>Данные склада</th><th>Покрытие</th><th>Цель</th><th>Пополнить</th><th>Последний спрос</th><th>Почему</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="stock-product">{{ $row['name'] }}<div class="stock-muted">{{ $row['sku'] ?: 'SKU не указан' }}</div></td>
                            <td class="stock-number">{{ $row['orders_recent'] }} / {{ $row['quantity_recent'] }} шт.<div class="stock-muted">Всего: {{ $row['orders_all'] }} / {{ $row['quantity_all'] }} шт.</div></td>
                            <td class="stock-number">{{ number_format($row['monthly_velocity'], 2, ',', ' ') }} шт.</td>
                            <td class="stock-number">{{ $row['current_own_stock'] === null ? '—' : rtrim(rtrim(number_format($row['current_own_stock'], 3, ',', ' '), '0'), ',') }}</td>
                            <td class="stock-evidence">
                                <span class="stock-badge" data-ready="{{ $row['stock_data_ready'] ? '1' : '0' }}">{{ $row['stock_data_label'] }}</span>
                                <div class="stock-muted">
                                    {{ $row['stock_confirmed_at']?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? 'нет времени подтверждения' }}
                                    @if ($row['stock_offer_count'] > 1) · {{ $row['stock_offer_count'] }} позиций 1С @endif
                                </div>
                            </td>
                            <td class="stock-number">{{ $row['stock_coverage_days'] === null ? '—' : number_format($row['stock_coverage_days'], 0, ',', ' ') . ' дн.' }}</td>
                            <td class="stock-number">{{ $row['target_stock'] }}</td>
                            <td class="stock-number">
                                @if ($row['recommended_purchase'] === null)
                                    <span class="stock-pending">После проверки</span>
                                @else
                                    <span class="{{ $row['recommended_purchase'] > 0 ? 'stock-recommend' : 'stock-ok' }}">{{ $row['recommended_purchase'] > 0 ? '+' . $row['recommended_purchase'] : 'Достаточно' }}</span>
                                @endif
                            </td>
                            <td class="stock-number">{{ $row['last_ordered_at']?->timezone('Europe/Minsk')->format('d.m.Y') ?? '—' }}</td>
                            <td class="stock-explanation">{{ $row['explanation'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">Нет товаров, соответствующих фильтрам. Расчёт не изменяет заказы и остатки.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="stock-footer">
                <span>{{ $rows->total() ? 'Показано ' . $rows->firstItem() . '–' . $rows->lastItem() . ' из ' . $rows->total() : 'Нет данных' }}</span>
                <div class="stock-pager">
                    <select class="stock-input" wire:model.live="perPage"><option>10</option><option>25</option><option>50</option><option>100</option></select>
                    <button class="stock-button" wire:click="previousPage('stockPage')" @disabled($rows->onFirstPage())>Назад</button>
                    <span>{{ $rows->currentPage() }} / {{ max(1, $rows->lastPage()) }}</span>
                    <button class="stock-button" wire:click="nextPage('stockPage')" @disabled(! $rows->hasMorePages())>Вперёд</button>
                </div>
            </div>
        </div>

        <div class="stock-card stock-footer">
            <span>Формула цели: крупнейший заказ за период или двухмесячный спрос — выбирается большее. Решение рассчитывается только по свежему подтверждённому остатку 1С.</span>
            <strong>Нет привязки или актуального остатка — закупка блокируется до проверки.</strong>
        </div>
    </div>
</x-filament-panels::page>
