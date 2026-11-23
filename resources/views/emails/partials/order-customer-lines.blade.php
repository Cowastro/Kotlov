<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px">
    <thead><tr><th style="text-align:left;padding:8px">Товар</th><th>Кол-во</th><th>Сумма</th></tr></thead>
    <tbody>
    @foreach($order->items as $item)
        <tr style="border-top:1px solid #ddd">
            <td style="padding:10px 8px">{{ $item->product_name }}<br><small>{{ $item->product_sku }}</small><br>{{ $order->customerItemFulfillment($item) }}</td>
            <td style="vertical-align:top;padding:10px">{{ $item->quantity }}</td>
            <td style="vertical-align:top;padding:10px;white-space:nowrap">{{ number_format((float)$item->total, 2, ',', ' ') }} BYN</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p><strong>Итого: {{ number_format((float)$order->total, 2, ',', ' ') }} BYN.</strong></p>
