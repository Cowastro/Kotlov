<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Заказ {{ $order->number }} — печать</title>
    <style>
        @page{size:A4 landscape;margin:8mm}
        *{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#111827;font:12px/1.4 Arial,sans-serif}main{max-width:1400px;margin:24px auto;background:#fff;padding:28px;box-shadow:0 1px 6px #0002}h1{margin:0 0 6px;font-size:24px}.heading{display:flex;justify-content:space-between;gap:24px;align-items:flex-start}.qr{text-align:center;flex:0 0 128px}.qr img{display:block;width:116px;height:116px;margin:0 auto 4px}.qr span{font-size:10px;color:#4b5563}.meta{display:flex;flex-wrap:wrap;gap:7px 22px;margin:14px 0 22px;color:#4b5563}.meta strong{color:#111827}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:7px;text-align:left;vertical-align:top}th{background:#f3f4f6;font-size:10px;text-transform:uppercase}.money,.qty{white-space:nowrap;text-align:right}.muted{color:#6b7280}.status{font-weight:700}.actions{display:flex;gap:10px;margin-bottom:18px}button,.back{border:0;border-radius:7px;padding:9px 14px;color:#fff;background:#111827;cursor:pointer;text-decoration:none}.totals{margin-top:18px;text-align:right;font-size:15px}.validity{margin-top:12px;color:#6b7280}
        @media print{html,body{width:281mm;background:#fff;font-size:9px}main{width:100%;max-width:none;margin:0;padding:0;box-shadow:none}.actions{display:none}.heading{gap:8mm}.qr{flex-basis:29mm}.qr img{width:27mm;height:27mm}h1{font-size:18px}.meta{gap:3px 12px;margin:7px 0 10px}table{table-layout:fixed}th,td{padding:3px 4px;overflow-wrap:anywhere}th{background:#eee!important;font-size:8px;print-color-adjust:exact}th:nth-child(1){width:19%}th:nth-child(2){width:4%}th:nth-child(3),th:nth-child(4),th:nth-child(6),th:nth-child(7){width:8%}th:nth-child(5){width:12%}th:nth-child(8){width:16%}th:nth-child(9){width:17%}.totals{margin-top:8px;font-size:11px}.validity{margin-top:6px}}
    </style>
</head>
<body>
<main>
    <div class="actions">
        <button type="button" onclick="window.print()">Печать</button>
        <a class="back" href="{{ app(\App\Services\Orders\OrderPortal::class)->url($link) }}">Вернуться к заказу</a>
    </div>
    <div class="heading">
        <div>
            <h1>Заказ {{ $order->number }}</h1>
            <div class="meta">
                <span>Статус: <strong>{{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</strong></span>
                <span>Создан: <strong>{{ $order->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</strong></span>
                @if($order->corrected_at)<span>Скорректирован: <strong>{{ $order->corrected_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</strong></span>@endif
                <span>Ответственный: <strong>{{ $order->manager?->name ?? $order->assigned_to ?? 'не назначен' }}</strong></span>
                <span>Клиент: <strong>{{ $order->customer_name }}</strong> · {{ $order->customer_phone }}</span>
            </div>
        </div>
        @if($qr)
            <div class="qr">
                <img src="{{ $qr }}" alt="QR-код онлайн-заказа {{ $order->number }}">
                <span>Открыть заказ с телефона</span>
            </div>
        @endif
    </div>

    <table>
        <thead><tr><th>Товар</th><th>Кол-во</th><th>Розница</th><th>Сумма</th><th>Поставщик</th><th>Входная цена</th><th>Наличие</th><th>Контакты</th><th>Статус</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            @php
                $supplier = $item->fulfillmentSupplier ?: $item->supplySupplier;
                $supplierName = $item->fulfillment_supplier_name ?: $supplier?->name ?: $item->supply_supplier_name;
                $purchasePrice = $item->fulfillment_purchase_price ?? $item->supply_purchase_price;
                $request = $item->supplierOrderRequestItem?->request;
                $status = $request?->statusLabel() ?? ($item->fulfillment_confirmed_at ? ($item->fulfillmentRouteLabel() ?: 'Подтверждено') : 'Ожидает решения');
            @endphp
            <tr>
                <td><strong>{{ $item->product_name }}</strong><br><span class="muted">{{ $item->product_sku ?: 'без артикула' }}</span></td>
                <td class="qty">{{ $item->quantity }}</td>
                <td class="money">{{ number_format((float)$item->price, 2, ',', ' ') }} BYN</td>
                <td class="money">{{ number_format((float)$item->total, 2, ',', ' ') }} BYN</td>
                <td>{{ $supplierName ?: ($item->fulfillment_route === 'own_stock' ? 'Наш склад' : 'не определён') }}</td>
                <td class="money">{{ $purchasePrice !== null ? number_format((float)$purchasePrice, 2, ',', ' ').' BYN' : '—' }}</td>
                <td>@if($item->supply_stock_quantity !== null){{ rtrim(rtrim(number_format((float)$item->supply_stock_quantity, 3, ',', ' '), '0'), ',') }} шт.@elseif($item->supply_is_available === true)В наличии@elseif($item->supply_is_available === false)Нет остатка@elseНеизвестно@endif</td>
                <td>{!! nl2br(e($item->currentSupplyContact() ?: '—')) !!}</td>
                <td><span class="status">{{ $status }}</span>@if($request?->supplier_response_note)<br>{{ $request->supplier_response_note }}@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="totals">Товары: <strong>{{ number_format((float)$order->subtotal, 2, ',', ' ') }} BYN</strong> · Итого: <strong>{{ number_format((float)$order->total, 2, ',', ' ') }} BYN</strong></div>
    @if($order->portalEvents->isNotEmpty())
        <div class="validity"><strong>История действий:</strong>
            @foreach($order->portalEvents as $event)<br>{{ $event->occurred_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }} — {{ $event->metadata['label'] ?? 'Изменение заказа' }} · {{ $event->metadata['actor'] ?? 'не указан' }}@endforeach
        </div>
    @endif
    <div class="validity">Сформировано {{ now()->timezone('Europe/Minsk')->format('d.m.Y H:i') }} · онлайн-ссылка действует до {{ $link->expires_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }} или до завершения/отмены заказа.</div>
</main>
</body>
</html>
