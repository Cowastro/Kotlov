<x-filament-panels::page>
    @php($summary = $this->summary())
    @php($rows = $this->rows())

    <style>
        .market-category { --mc-border: rgba(15,23,42,.12); --mc-card:#fff; --mc-muted:#64748b; display:grid; gap:14px; }
        .dark .market-category { --mc-border:rgba(148,163,184,.18); --mc-card:rgba(255,255,255,.025); --mc-muted:#94a3b8; }
        .mc-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .mc-card { border:1px solid var(--mc-border); border-radius:9px; background:var(--mc-card); }
        .mc-metric { padding:14px 16px; }
        .mc-metric span,.mc-muted { color:var(--mc-muted); }
        .mc-metric span { font-size:12px; }
        .mc-metric strong { display:block; margin-top:5px; font-size:25px; }
        .mc-metric[data-tone="danger"] strong { color:#dc2626; }
        .mc-metric[data-tone="warning"] strong { color:#d97706; }
        .mc-metric[data-tone="success"] strong { color:#16a34a; }
        .mc-metric[data-tone="info"] strong { color:#2563eb; }
        .mc-tools,.mc-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 15px; }
        .mc-field { display:grid; gap:5px; width:min(420px,100%); color:var(--mc-muted); font-size:11px; font-weight:700; text-transform:uppercase; }
        .mc-input { min-height:34px; border:1px solid var(--mc-border); border-radius:7px; padding:5px 10px; background:transparent; color:inherit; font-size:13px; text-transform:none; }
        .mc-table-wrap { overflow:auto; max-height:min(68vh,800px); }
        .mc-table { width:100%; min-width:1280px; border-collapse:collapse; font-size:12px; }
        .mc-table th { position:sticky; top:0; z-index:2; padding:9px 10px; background:#f3f4f6; color:var(--mc-muted); text-align:left; white-space:nowrap; border-bottom:1px solid var(--mc-border); }
        .dark .mc-table th { background:#202124; }
        .mc-table td { padding:10px; vertical-align:top; border-bottom:1px solid var(--mc-border); }
        .mc-category { min-width:220px; font-weight:800; }
        .mc-number { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .mc-trend { font-weight:800; white-space:nowrap; }
        .mc-up { color:#dc2626; }.mc-down { color:#16a34a; }.mc-flat { color:var(--mc-muted); }
        .mc-badge { display:inline-flex; border:1px solid currentColor; border-radius:999px; padding:2px 7px; font-size:10px; font-weight:800; }
        .mc-badge[data-tone="danger"] { color:#dc2626; }.mc-badge[data-tone="warning"] { color:#d97706; }.mc-badge[data-tone="success"] { color:#16a34a; }.mc-badge[data-tone="muted"] { color:var(--mc-muted); }
        .mc-opportunity { min-width:250px; max-width:340px; line-height:1.4; }
        .mc-pager { display:flex; align-items:center; gap:8px; }
        .mc-button { border:1px solid var(--mc-border); border-radius:6px; padding:6px 10px; font-weight:700; }
        .mc-button:disabled { opacity:.4; }
        @media(max-width:900px){.mc-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:600px){.mc-summary{grid-template-columns:1fr;}.mc-tools,.mc-footer{align-items:stretch;flex-direction:column;}}
    </style>

    <div class="market-category">
        <div class="mc-summary">
            @foreach($summary as $metric)
                <div class="mc-card mc-metric" data-tone="{{ $metric['tone'] }}"><span>{{ $metric['label'] }}</span><strong>{{ number_format($metric['value'],0,',',' ') }}</strong></div>
            @endforeach
        </div>

        <div class="mc-card">
            <div class="mc-tools">
                <label class="mc-field">Поиск категории<input class="mc-input" type="search" wire:model.live.debounce.350ms="search" placeholder="Название категории"></label>
                <div class="mc-muted">Динамика цен требует минимум 3 сопоставимые пары. Цены автоматически не меняются.</div>
            </div>
            <div class="mc-table-wrap">
                <table class="mc-table">
                    <thead><tr><th>Категория</th><th>Охват рынка</th><th>В наличии</th><th>7 дней</th><th>30 дней</th><th>90 дней</th><th>Ценовой лидер</th><th>Спрос 90 дней</th><th>Вывод</th><th>Проверено</th></tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="mc-category">{{ $row['category_name'] }}<div class="mc-muted">{{ $row['products_count'] }} товаров в истории</div></td>
                            <td>{{ $row['fresh_products_count'] }} тов. · {{ $row['fresh_offers_count'] }} предл. <div class="mc-muted">{{ $row['fresh_sources_count'] }} источников</div></td>
                            <td class="mc-number">{{ $row['availability_percent'] === null ? '—' : number_format($row['availability_percent'],1,',',' ').'%' }}<div class="mc-muted">{{ $row['available_offers_count'] }} свежих</div></td>
                            @foreach([7,30,90] as $days)
                                @php($trend = $row['trends'][$days])
                                <td class="mc-number">
                                    @if($trend['percent'] === null)<span class="mc-flat">Нет данных</span>
                                    @else<span class="mc-trend {{ $trend['percent'] > 0 ? 'mc-up' : ($trend['percent'] < 0 ? 'mc-down' : 'mc-flat') }}">{{ $trend['percent'] > 0 ? '+' : '' }}{{ number_format($trend['percent'],1,',',' ') }}%</span>@endif
                                    <div class="mc-muted">{{ $trend['pairs'] }} пар</div>
                                </td>
                            @endforeach
                            <td>{{ $row['price_leader']['name'] ?? '—' }}@if($row['price_leader'])<div class="mc-muted">минимум по {{ $row['price_leader']['wins'] }} из {{ $row['price_leader']['products'] }} товаров</div>@endif</td>
                            <td class="mc-number">{{ $row['demand_orders_90d'] }} зак. / {{ $row['demand_quantity_90d'] }} шт.<div class="mc-muted">{{ number_format($row['demand_revenue_90d'],2,',',' ') }} BYN</div></td>
                            <td class="mc-opportunity"><span class="mc-badge" data-tone="{{ $row['opportunity']['tone'] }}">{{ $row['opportunity']['label'] }}</span><div class="mc-muted">{{ $row['opportunity']['description'] }}</div></td>
                            <td>{{ $row['last_checked_at']?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">Нет подтверждённых рыночных наблюдений для исследования категорий.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mc-footer">
                <span>{{ $rows->total() ? 'Показано '.$rows->firstItem().'–'.$rows->lastItem().' из '.$rows->total() : 'Нет данных' }}</span>
                <div class="mc-pager"><select class="mc-input" wire:model.live="perPage"><option>10</option><option>25</option><option>50</option><option>100</option></select><button class="mc-button" wire:click="previousPage('categoryPage')" @disabled($rows->onFirstPage())>Назад</button><span>{{ $rows->currentPage() }} / {{ max(1,$rows->lastPage()) }}</span><button class="mc-button" wire:click="nextPage('categoryPage')" @disabled(!$rows->hasMorePages())>Вперёд</button></div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
