<div style="overflow-x:auto;border:1px solid rgba(156,163,175,.28);border-radius:12px">
    <table style="width:100%;min-width:760px;border-collapse:collapse;font-size:13px">
        <thead style="background:rgba(156,163,175,.08);color:#9ca3af;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em">
            <tr>
                <th style="padding:11px 14px;width:46%">Товар</th>
                <th style="padding:11px 14px;width:18%">Артикул</th>
                <th style="padding:11px 14px;text-align:right;width:12%">Количество</th>
                <th style="padding:11px 14px;text-align:right;width:12%">Цена</th>
                <th style="padding:11px 14px;text-align:right;width:12%">Сумма</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                @php
                    $quantity = number_format((float) $item->quantity, 3, ',', ' ');
                    $quantity = rtrim(rtrim($quantity, '0'), ',');
                @endphp
                <tr style="border-top:1px solid rgba(156,163,175,.2)">
                    <td style="padding:13px 14px;font-weight:650;line-height:1.35">{{ $item->name }}</td>
                    <td style="padding:13px 14px;font-family:ui-monospace,monospace;font-size:12px;color:#9ca3af">{{ $item->sku ?: '—' }}</td>
                    <td style="padding:13px 14px;text-align:right;white-space:nowrap">{{ $quantity }} {{ $item->unit }}</td>
                    <td style="padding:13px 14px;text-align:right;white-space:nowrap">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} BYN</td>
                    <td style="padding:13px 14px;text-align:right;white-space:nowrap;font-weight:700">{{ number_format((float) $item->line_total, 2, ',', ' ') }} BYN</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
