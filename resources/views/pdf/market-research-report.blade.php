<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Анализ рынка - {{ $research['product_name'] }}</title>
    <style>
        @page { margin: 24mm 15mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; line-height: 1.45; }
        .header { border-bottom: 3px solid #e85520; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { color: #e85520; font-size: 19pt; font-weight: 800; letter-spacing: .5px; }
        .kicker { color: #64748b; font-size: 8pt; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 5px 0 0; color: #101827; font-size: 17pt; line-height: 1.25; }
        h2 { margin: 18px 0 8px; color: #101827; font-size: 12pt; }
        .muted { color: #64748b; }
        .chips { margin-top: 9px; }
        .chip { display: inline-block; border-radius: 10px; background: #f1f5f9; margin: 0 5px 4px 0; padding: 4px 8px; font-size: 8pt; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 14px; }
        .stats td { width: 25%; border: 1px solid #dbe2ea; border-radius: 7px; padding: 9px; vertical-align: top; }
        .label { color: #64748b; font-size: 7.5pt; }
        .value { margin-top: 4px; color: #101827; font-size: 11pt; font-weight: 700; }
        .panel { border: 1px solid #dbe2ea; border-radius: 8px; margin-bottom: 11px; padding: 11px 13px; page-break-inside: avoid; }
        .verdict { border-left: 3px solid #e85520; background: #fff7ed; padding: 8px 10px; font-weight: 700; }
        .decision { background: #f8fafc; }
        .decision-grid { width: 100%; border-collapse: collapse; }
        .decision-grid td { padding: 3px 7px 3px 0; vertical-align: top; }
        .decision-grid td:first-child { width: 30%; color: #64748b; }
        table.offers { width: 100%; border-collapse: collapse; }
        .offers th { border-bottom: 2px solid #cbd5e1; color: #475569; padding: 7px 6px; text-align: left; font-size: 7.5pt; }
        .offers td { border-bottom: 1px solid #e2e8f0; padding: 7px 6px; vertical-align: top; }
        .offers tr { page-break-inside: avoid; }
        .offers .price { white-space: nowrap; font-weight: 700; text-align: right; }
        a { color: #c2410c; text-decoration: none; }
        .reason { color: #64748b; font-size: 8pt; margin-top: 3px; }
        .rejected { margin: 0; padding-left: 18px; }
        .rejected li { margin-bottom: 6px; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #dbe2ea; color: #64748b; padding-top: 5px; font-size: 7.5pt; text-align: center; }
    </style>
</head>
<body>
@php
    $money = fn ($value) => $value === null ? '-' : number_format((float) $value, 2, ',', ' ').' BYN';
    $market = (array) $research['market'];
    $offers = (array) $research['offers'];
@endphp
<header class="header">
    <div class="brand">KOTLOV.BY</div>
    <div class="kicker">Отчёт по исследованию рынка</div>
    <h1>{{ $research['product_name'] }}</h1>
    <div class="chips">
        <span class="chip">Артикул: {{ $research['sku'] ?: '-' }}</span>
        <span class="chip">Пакет: {{ $decision['batch_code'] }}</span>
        <span class="chip">Исследовано: {{ $research['searched_at']?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '-' }}</span>
        <span class="chip">Достоверность: {{ ['high' => 'высокая', 'medium' => 'средняя', 'low' => 'низкая'][$research['confidence']] ?? $research['confidence'] }}</span>
    </div>
</header>

<table class="stats"><tr>
    <td><div class="label">Цена карточки</div><div class="value">{{ $money($research['quote_price']) }}</div></td>
    <td><div class="label">Диапазон рынка</div><div class="value">{{ $money($market['minimum'] ?? null) }}<br>- {{ $money($market['maximum'] ?? null) }}</div></td>
    <td><div class="label">Медиана</div><div class="value">{{ $money($market['median'] ?? null) }}</div></td>
    <td><div class="label">Предложений</div><div class="value">{{ count($offers) }}</div></td>
</tr></table>

<section class="panel">
    <h2 style="margin-top:0">Рекомендация менеджеру</h2>
    <div class="verdict">{{ $research['verdict'] ?: 'Итоговая рекомендация не указана.' }}</div>
    @if($research['summary'])<p>{{ $research['summary'] }}</p>@endif
</section>

<section class="panel decision">
    <h2 style="margin-top:0">Решение и экономика</h2>
    <table class="decision-grid">
        <tr><td>Решение</td><td><strong>{{ $decision['label'] }}</strong></td></tr>
        <tr><td>Причина проверки</td><td>{{ $decision['review_reason'] ?: '-' }}</td></tr>
        <tr><td>Обоснование</td><td>{{ $decision['reason'] ?: '-' }}</td></tr>
        <tr><td>Цена до / рекомендация / после</td><td>{{ $money($decision['price_before']) }} / {{ $money($decision['recommended_price']) }} / {{ $money($decision['price_after']) }}</td></tr>
        <tr><td>Входная цена</td><td>{{ $money($pricing['purchase_price'] ?? null) }}@if($pricing['supplier_name'] ?? null) - {{ $pricing['supplier_name'] }}@endif</td></tr>
        <tr><td>Маржа текущей цены</td><td>{{ $money($pricing['margin_byn'] ?? null) }}@if(($pricing['margin_percent'] ?? null) !== null) / {{ number_format((float)$pricing['margin_percent'], 1, ',', ' ') }}%@endif</td></tr>
    </table>
</section>

<h2>Подтверждённые предложения</h2>
<table class="offers">
    <thead><tr><th>Источник и предложение</th><th>Наличие</th><th style="text-align:right">Цена</th></tr></thead>
    <tbody>
    @forelse($offers as $offer)
        <tr>
            <td><strong>{{ $offer['seller'] }}</strong><br>@if($offer['url'])<a href="{{ $offer['url'] }}">{{ $offer['title'] }}</a>@else{{ $offer['title'] }}@endif @if($offer['evidence'])<div class="reason">{{ $offer['evidence'] }}</div>@endif</td>
            <td>{{ $offer['availability'] ?: 'Не подтверждено' }}</td>
            <td class="price">{{ $offer['price'] !== null ? number_format((float)$offer['price'], 2, ',', ' ').' '.($offer['currency'] ?: 'BYN') : '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="muted">Точных предложений с проверяемой ценой не найдено.</td></tr>
    @endforelse
    </tbody>
</table>

@if($research['rejected_candidates'] !== [])
    <div class="panel" style="margin-top:18px"><h2 style="margin-top:0">Отклонённые варианты</h2><ol class="rejected">
        @foreach($research['rejected_candidates'] as $candidate)
            <li><strong>{{ $candidate['title'] ?? $candidate['url'] ?? 'Похожая позиция' }}</strong><div class="reason">{{ $candidate['reason'] ?? 'Не соответствует проверяемой модели.' }}</div></li>
        @endforeach
    </ol></div>
@endif

<div class="footer">KOTLOV.BY · внутренний аналитический отчёт · сформирован {{ now()->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</div>
</body>
</html>
