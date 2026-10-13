<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 30px 30px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: #171717; font-size: 10px; line-height: 1.35; }
        .header { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { width: 155px; padding: 7px 8px 7px 0; vertical-align: middle; }
        .logo img { width: 145px; max-height: 42px; object-fit: contain; }
        .company { padding-left: 14px; font-size: 9px; color: #444; word-wrap: break-word; }
        .company strong { display: block; color: #111; font-size: 15px; margin-bottom: 4px; }
        .doc-box { width: 220px; text-align: right; }
        .doc-type { font-size: 17px; font-weight: bold; text-transform: uppercase; color: #d97706; line-height: 1.25; }
        .doc-number { font-size: 11px; font-weight: bold; margin-top: 6px; }
        .accent { height: 4px; background: #f59e0b; margin: 8px 0 16px; }
        .party { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 14px; }
        .party td { width: 50%; border: 1px solid #d4d4d4; padding: 8px 10px; vertical-align: top; }
        .party-title { font-size: 8px; text-transform: uppercase; color: #737373; margin-bottom: 3px; }
        .party strong { display: block; max-width: 250px; font-size: 11px; line-height: 1.45; word-wrap: break-word; }
        .items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th { background: #292929; color: #fff; padding: 7px 5px; font-size: 8px; }
        .items td { border: 1px solid #d4d4d4; padding: 7px 5px; }
        .items .number { text-align: right; white-space: nowrap; }
        .summary { width: 310px; margin-left: auto; border-collapse: collapse; margin-top: 10px; }
        .summary td { padding: 4px 6px; border-bottom: 1px solid #e5e5e5; }
        .summary td:last-child { text-align: right; font-weight: bold; }
        .summary .total td { font-size: 13px; color: #b45309; border-top: 2px solid #f59e0b; border-bottom: 0; }
        .words { margin-top: 12px; padding: 8px 10px; background: #fff7ed; border-left: 4px solid #f59e0b; font-weight: bold; }
        .terms { margin-top: 16px; width: 100%; border-collapse: collapse; }
        .terms td { width: 50%; vertical-align: top; padding-right: 16px; }
        .terms strong { display: block; margin-bottom: 3px; }
        .note { margin-top: 12px; color: #525252; white-space: pre-line; }
        .footer { margin-top: 26px; border-top: 1px solid #d4d4d4; padding-top: 10px; }
        .signature { width: 100%; border-collapse: collapse; }
        .signature td { width: 50%; vertical-align: bottom; }
        .muted { color: #737373; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td class="logo">@if($logo)<img src="{{ $logo }}" alt="KOTLOV.BY">@endif</td>
        <td class="company">
            <strong>{{ $seller['short_name'] }}</strong>
            УНП {{ $seller['unp'] }}@if($seller['okpo'] ?? null), ОКПО {{ $seller['okpo'] }}@endif<br>
            {{ $seller['legal_address'] }}<br>
            {{ implode(', ', $seller['phones'] ?? []) }} · {{ $seller['email'] }}
        </td>
        <td class="doc-box">
            <div class="doc-type">{!! $document->type === 'invoice' ? 'Счёт<br>на оплату' : 'Коммерческое<br>предложение' !!}</div>
            <div class="doc-number">№ {{ $document->number }} от {{ $document->issued_at->format('d.m.Y') }}</div>
            @if($document->valid_until)<div class="muted">действителен до {{ $document->valid_until->format('d.m.Y') }}</div>@endif
        </td>
    </tr>
</table>
<div class="accent"></div>

<table class="party">
    <tr>
        <td>
            <div class="party-title">Поставщик</div>
            <strong>{{ $seller['name'] }}</strong><br>
            УНП {{ $seller['unp'] }}<br>
            р/с {{ $seller['iban'] }}<br>
            {{ $seller['bank'] }}, БИК {{ $seller['bic'] }}
        </td>
        <td>
            <div class="party-title">Покупатель</div>
            <strong>{{ $document->client_company ?: ($document->client_name ?: 'Не указан') }}</strong><br>
            @if($document->client_unp)УНП {{ $document->client_unp }}<br>@endif
            @if($document->client_name && $document->client_company)Контакт: {{ $document->client_name }}<br>@endif
            @if($document->client_phone){{ $document->client_phone }}<br>@endif
            @if($document->client_email){{ $document->client_email }}@endif
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th style="width: 26px">№</th>
        <th>Товар / услуга</th>
        <th style="width: 72px">Артикул</th>
        <th style="width: 40px">Ед.</th>
        <th style="width: 48px">Кол-во</th>
        <th style="width: 78px">Цена, BYN</th>
        <th style="width: 82px">Сумма, BYN</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $index => $item)
        <tr>
            <td class="number">{{ $index + 1 }}</td>
            <td><strong>{{ $item->name }}</strong>@if((float)$item->discount_percent > 0)<br><span class="muted">Скидка {{ number_format((float)$item->discount_percent, 2, ',', ' ') }}%</span>@endif</td>
            <td>{{ $item->sku ?: '—' }}</td>
            <td>{{ $item->unit }}</td>
            <td class="number">{{ rtrim(rtrim(number_format((float)$item->quantity, 3, ',', ' '), '0'), ',') }}</td>
            <td class="number">{{ number_format((float)$item->unit_price, 2, ',', ' ') }}</td>
            <td class="number"><strong>{{ number_format((float)$item->line_total, 2, ',', ' ') }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="summary">
    <tr><td>Товары:</td><td>{{ number_format((float)$document->subtotal, 2, ',', ' ') }} BYN</td></tr>
    @if((float)$document->discount_total > 0)<tr><td>Дополнительная скидка:</td><td>- {{ number_format((float)$document->discount_total, 2, ',', ' ') }} BYN</td></tr>@endif
    @if((float)$document->delivery_price > 0)<tr><td>Доставка:</td><td>{{ number_format((float)$document->delivery_price, 2, ',', ' ') }} BYN</td></tr>@endif
    <tr><td>В том числе НДС {{ number_format((float)$document->vat_rate, 0) }}%:</td><td>{{ number_format((float)$document->total * ((float)$document->vat_rate / (100 + (float)$document->vat_rate)), 2, ',', ' ') }} BYN</td></tr>
    <tr class="total"><td>Итого:</td><td>{{ number_format((float)$document->total, 2, ',', ' ') }} BYN</td></tr>
</table>

<div class="words">Всего к оплате: {{ ucfirst($amountWords) }}</div>

<table class="terms">
    <tr>
        <td><strong>Условия оплаты</strong>{{ $document->payment_terms ?: 'Безналичный расчёт по выставленному счёту.' }}</td>
        <td><strong>Условия поставки</strong>{{ $document->delivery_terms ?: 'Срок и способ поставки согласовываются с менеджером.' }}</td>
    </tr>
</table>
@if($document->notes)<div class="note"><strong>Примечание</strong><br>{{ $document->notes }}</div>@endif

<div class="footer">
    <table class="signature">
        <tr>
            <td>
                <strong>{{ $document->manager?->name ?: 'Менеджер KOTLOV.BY' }}</strong><br>
                <span class="muted">{{ $document->manager?->job_title ?: 'Менеджер' }}@if($document->manager?->phone) · {{ $document->manager->phone }}@endif</span>
            </td>
            <td style="text-align:right">{{ $seller['website'] }} · {{ $seller['email'] }}</td>
        </tr>
    </table>
</div>
</body>
</html>
