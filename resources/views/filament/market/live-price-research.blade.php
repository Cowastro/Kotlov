@php
    $ready = ($research['status'] ?? null) === 'ready';
    $market = (array) ($research['market'] ?? []);
    $offers = (array) ($research['offers'] ?? []);
    $sources = (array) ($research['sources'] ?? []);
    $rejected = (array) ($research['rejected_candidates'] ?? []);
    $minimumSafe = isset($pricing['minimum_safe_price']) ? (float) $pricing['minimum_safe_price'] : null;
    $median = isset($market['median']) ? (float) $market['median'] : null;
    $suggested = $median !== null ? max($median, (float) ($minimumSafe ?? 0)) : $minimumSafe;
    $maximum = isset($market['maximum']) ? (float) $market['maximum'] : null;
    $conflict = $suggested !== null && $maximum !== null && $suggested > $maximum;
    $confidence = $research['confidence'] ?? 'low';
    $confidenceLabels = ['high' => 'Высокая', 'medium' => 'Средняя', 'low' => 'Низкая'];
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', ' ').' BYN';
    $quoteDelta = $market['quote_delta_percent'] ?? null;
    $hasPricing = ! empty($pricing['supplier_name'])
        || ($pricing['purchase_price'] ?? null) !== null
        || ($pricing['margin_byn'] ?? null) !== null
        || $minimumSafe !== null;
@endphp

<style>
    .market-report{--b:#e5e7eb;--card:#fff;--soft:#f8fafc;--text:#111827;--muted:#64748b;--blue:#2563eb;--blue-bg:#eff6ff;--green:#047857;--green-bg:#ecfdf5;--amber:#b45309;--amber-bg:#fffbeb;--red:#b91c1c;--red-bg:#fef2f2;color:var(--text);font-size:14px;line-height:1.5}
    .dark .market-report{--b:rgba(255,255,255,.12);--card:rgba(255,255,255,.035);--soft:rgba(255,255,255,.06);--text:#f8fafc;--muted:#94a3b8;--blue:#60a5fa;--blue-bg:rgba(37,99,235,.16);--green:#6ee7b7;--green-bg:rgba(5,150,105,.15);--amber:#fcd34d;--amber-bg:rgba(217,119,6,.14);--red:#fca5a5;--red-bg:rgba(220,38,38,.14)}
    .mr-stack{display:grid;gap:16px}.mr-panel{border:1px solid var(--b);border-radius:12px;background:var(--card);padding:16px}.mr-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
    .mr-eyebrow{color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.mr-title{margin-top:4px;font-size:16px;font-weight:700;line-height:1.35}.mr-meta,.mr-offer-meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}
    .mr-chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:var(--soft);color:var(--muted);padding:4px 9px;font-size:12px;line-height:1.25}.mr-chip--blue{background:var(--blue-bg);color:var(--blue)}.mr-chip--green{background:var(--green-bg);color:var(--green)}.mr-chip--amber{background:var(--amber-bg);color:var(--amber)}.mr-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
    .mr-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.mr-stat{min-width:0;border:1px solid var(--b);border-radius:10px;background:var(--card);padding:13px 14px}.mr-stat-label{color:var(--muted);font-size:12px}.mr-stat-value{margin-top:5px;font-size:17px;font-weight:750;line-height:1.25}.mr-stat-note{margin-top:3px;color:var(--muted);font-size:11px}
    .mr-columns{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(260px,.85fr);gap:12px}.mr-section-title{margin:0 0 10px;font-size:14px;font-weight:700}.mr-verdict{border-left:3px solid var(--blue);border-radius:0 8px 8px 0;background:var(--blue-bg);padding:11px 13px;font-weight:650;line-height:1.55}.mr-summary{margin:12px 0 0;color:var(--muted);line-height:1.65}
    .mr-facts{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 14px;margin:0}.mr-facts dt{color:var(--muted)}.mr-facts dd{margin:0;font-weight:650;text-align:right}.mr-empty{border-radius:9px;background:var(--amber-bg);color:var(--amber);padding:11px 12px}.mr-alert{border-radius:9px;margin-top:12px;padding:10px 12px;font-size:12px}.mr-alert--red{background:var(--red-bg);color:var(--red)}.mr-alert--amber{background:var(--amber-bg);color:var(--amber)}
    .mr-offers-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:10px}.mr-offers-head h3{margin:0;font-size:15px;font-weight:750}.mr-count{color:var(--muted);font-size:12px}.mr-offers{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.mr-offer{min-width:0;border:1px solid var(--b);border-radius:11px;background:var(--card);padding:14px}.mr-offer-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.mr-seller{color:var(--blue);font-weight:750;text-decoration:none}.mr-seller:hover{text-decoration:underline}.mr-price{flex:0 0 auto;font-size:16px;font-weight:800;white-space:nowrap;text-align:right}.mr-converted{color:var(--muted);font-size:11px;font-weight:400}.mr-offer-title{margin-top:5px;color:var(--muted);font-size:12px;line-height:1.45}.mr-evidence{margin-top:10px;border-radius:8px;background:var(--soft);color:var(--muted);padding:9px 10px;font-size:12px;line-height:1.5}.mr-evidence strong{color:var(--text)}
    .mr-details{border:1px solid var(--b);border-radius:10px;background:var(--card)}.mr-details summary{cursor:pointer;list-style:none;padding:12px 14px;font-weight:700}.mr-details summary::-webkit-details-marker{display:none}.mr-details summary:after{content:'+';float:right;color:var(--muted);font-size:18px;line-height:1}.mr-details[open] summary:after{content:'−'}.mr-details-body{border-top:1px solid var(--b);padding:12px 14px}.mr-source-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 20px}.mr-source{display:block;overflow-wrap:anywhere;color:var(--blue);font-size:12px;text-decoration:none}.mr-source:hover{text-decoration:underline}.mr-rejected{display:grid;gap:9px;color:var(--muted);font-size:12px}.mr-footer{color:var(--muted);font-size:11px;text-align:right}
    @media(max-width:900px){.mr-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mr-columns,.mr-offers{grid-template-columns:1fr}}@media(max-width:560px){.mr-hero{display:block}.mr-stats,.mr-source-grid{grid-template-columns:1fr}.mr-price{font-size:14px}}
</style>

<div class="market-report">
    @if (! $ready)
        <div class="mr-panel" style="background:var(--red-bg);color:var(--red)">{{ $research['message'] ?? 'Не удалось выполнить исследование рынка.' }}</div>
    @else
        <div class="mr-stack">
            <section class="mr-panel mr-hero">
                <div>
                    <div class="mr-eyebrow">Проверяемая позиция</div>
                    <div class="mr-title">{{ $research['product_name'] }}</div>
                    <div class="mr-meta">
                        <span class="mr-chip">Артикул: {{ $research['sku'] ?: 'не указан' }}</span>
                        <span class="mr-chip mr-chip--blue">Цена КП: {{ $money($research['quote_price']) }}</span>
                        <span class="mr-chip mr-chip--{{ $confidence === 'high' ? 'green' : ($confidence === 'medium' ? 'blue' : 'amber') }}"><span class="mr-dot"></span>Достоверность: {{ $confidenceLabels[$confidence] ?? 'Низкая' }}</span>
                    </div>
                </div>
            </section>

            <section class="mr-stats" aria-label="Ключевые показатели рынка">
                <div class="mr-stat"><div class="mr-stat-label">Продавцов подтверждено</div><div class="mr-stat-value">{{ (int) ($market['count'] ?? 0) }}</div><div class="mr-stat-note">независимых источников</div></div>
                <div class="mr-stat"><div class="mr-stat-label">Рыночный диапазон</div><div class="mr-stat-value">{{ $money($market['minimum'] ?? null) }} — {{ $money($market['maximum'] ?? null) }}</div><div class="mr-stat-note">минимум и максимум</div></div>
                <div class="mr-stat"><div class="mr-stat-label">Медиана рынка</div><div class="mr-stat-value">{{ $money($market['median'] ?? null) }}</div><div class="mr-stat-note">@if ($quoteDelta !== null)Ваша цена: {{ (float) $quoteDelta > 0 ? '+' : '' }}{{ number_format((float) $quoteDelta, 1, ',', ' ') }}%@else отклонение не рассчитано @endif</div></div>
                <div class="mr-stat" style="border-color:{{ $conflict ? 'var(--red)' : 'var(--green)' }}"><div class="mr-stat-label">Безопасный ориентир</div><div class="mr-stat-value">{{ $money($suggested) }}</div><div class="mr-stat-note">цена не меняется автоматически</div></div>
            </section>

            <section class="mr-columns">
                <div class="mr-panel">
                    <h3 class="mr-section-title">Рекомендация менеджеру</h3>
                    <div class="mr-verdict">{{ $research['verdict'] ?: 'Нет итоговой рекомендации.' }}</div>
                    @if (! empty($research['summary']))<p class="mr-summary">{{ $research['summary'] }}</p>@endif
                </div>
                <div class="mr-panel">
                    <h3 class="mr-section-title">Закупка и маржа</h3>
                    @if ($hasPricing)
                        <dl class="mr-facts">
                            <dt>Поставщик</dt><dd>{{ $pricing['supplier_name'] ?? 'нет данных' }}</dd>
                            <dt>Закупочная цена</dt><dd>{{ $money($pricing['purchase_price'] ?? null) }}</dd>
                            <dt>Маржа КП</dt><dd>{{ $money($pricing['margin_byn'] ?? null) }}@if (($pricing['margin_percent'] ?? null) !== null) / {{ number_format((float) $pricing['margin_percent'], 1, ',', ' ') }}%@endif</dd>
                            <dt>Минимум для маржи</dt><dd>{{ $money($minimumSafe) }}</dd>
                        </dl>
                    @else
                        <div class="mr-empty"><strong>⚠ Нет закупочных данных.</strong><br>Привяжите поставщика и закупочную цену для расчёта маржи.</div>
                    @endif
                    @if ($conflict)<div class="mr-alert mr-alert--red">Минимальная цена для маржи выше рынка. Нужна новая закупочная цена или решение менеджера.</div>
                    @elseif (($market['count'] ?? 0) < 3)<div class="mr-alert mr-alert--amber">Меньше трёх сопоставимых продавцов. Цену лучше подтвердить вручную.</div>@endif
                </div>
            </section>

            <section>
                <div class="mr-offers-head"><h3>Подтверждённые предложения</h3><span class="mr-count">Найдено: {{ count($offers) }}</span></div>
                @if ($offers === [])
                    <div class="mr-panel" style="color:var(--muted)">Точных предложений с проверяемой ценой не найдено.</div>
                @else
                    <div class="mr-offers">
                        @foreach ($offers as $offer)
                            @php
                                $offerPrice = $offer['price'] ?? null;
                                $currency = $offer['currency'] ?? null;
                                $priceByn = $offer['price_byn'] ?? null;
                                $exact = ($offer['match'] ?? null) === 'exact';
                            @endphp
                            <article class="mr-offer">
                                <div class="mr-offer-top">
                                    <div style="min-width:0"><a href="{{ $offer['url'] }}" target="_blank" rel="noopener noreferrer" class="mr-seller">{{ ($offer['seller'] ?? null) ?: ($offer['domain'] ?? 'Открыть источник') }} ↗</a><div class="mr-offer-title">{{ $offer['title'] ?? 'Без названия' }}</div></div>
                                    <div class="mr-price">{{ $offerPrice !== null ? number_format((float) $offerPrice, 2, ',', ' ').' '.($currency ?: '') : '—' }}@if ($priceByn !== null && $currency !== 'BYN')<div class="mr-converted">≈ {{ $money($priceByn) }}</div>@endif</div>
                                </div>
                                <div class="mr-offer-meta">
                                    <span class="mr-chip mr-chip--{{ $exact ? 'green' : 'amber' }}">{{ $exact ? 'Точное совпадение' : 'Вероятное совпадение' }}</span>
                                    <span class="mr-chip">{{ ($offer['availability'] ?? null) ?: 'Наличие не подтверждено' }}</span>
                                    @if (! empty($offer['vat']))<span class="mr-chip">{{ $offer['vat'] }}</span>@endif
                                    @if (! empty($offer['package']))<span class="mr-chip">{{ $offer['package'] }}</span>@endif
                                </div>
                                @if (! empty($offer['evidence']))<div class="mr-evidence"><strong>Почему засчитано:</strong> {{ $offer['evidence'] }}</div>@endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($rejected !== [])
                <details class="mr-details"><summary>Отбракованные похожие товары ({{ count($rejected) }})</summary><div class="mr-details-body mr-rejected">
                    @foreach ($rejected as $candidate)<div>@if (! empty($candidate['url']))<a href="{{ $candidate['url'] }}" target="_blank" rel="noopener noreferrer" class="mr-seller">{{ $candidate['title'] ?? $candidate['url'] }} ↗</a>@else<strong style="color:var(--text)">{{ $candidate['title'] ?? 'Похожая позиция' }}</strong>@endif<div>{{ $candidate['reason'] ?? 'Не соответствует проверяемой модели.' }}</div></div>@endforeach
                </div></details>
            @endif

            <details class="mr-details"><summary>Источники и поисковые запросы ({{ count($sources) }})</summary><div class="mr-details-body">
                <div class="mr-source-grid">@forelse ($sources as $source)<a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="mr-source">{{ ($source['title'] ?? null) ?: $source['url'] }} ↗</a>@empty<span style="color:var(--muted)">Список источников не получен.</span>@endforelse</div>
                @if (! empty($research['search_queries']))<div class="mr-eyebrow" style="margin-top:14px">Выполненные запросы</div><ol style="margin:7px 0 0;padding-left:20px;color:var(--muted);font-size:12px">@foreach ((array) $research['search_queries'] as $query)<li>{{ $query }}</li>@endforeach</ol>@endif
            </div></details>

            <div class="mr-footer">Исследовано {{ $research['searched_at']?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }} · {{ $research['model'] ?? 'модель не указана' }} · Цена каталога не изменена</div>
        </div>
    @endif
</div>
