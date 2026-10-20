<x-email.brand-shell
    :subject="'Заказ '.$order->number.' принят — KOTLOV.BY'"
    eyebrow="ЗАКАЗ ПРИНЯТ"
    :title="'Спасибо за заказ, '.$order->customer_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="route('checkout.success', $order->number)"
    button-label="Посмотреть заказ"
    notice="Это автоматическое подтверждение заказа. Менеджер свяжется с вами для уточнения наличия и доставки."
>
    <p style="margin:0 0 18px">Заказ <strong style="color:#101010">{{ $order->number }}</strong> принят. Мы проверим наличие и свяжемся с вами.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:12px 0;border-top:1px solid #ececec;color:#101010">
                    {{ $item->product_name }}<br>
                    <span style="font-size:13px;color:#777">{{ $item->quantity }} шт.{{ $item->product_sku ? ' · '.$item->product_sku : '' }}</span>
                </td>
                <td align="right" style="padding:12px 0;border-top:1px solid #ececec;white-space:nowrap;color:#101010;font-weight:700">
                    {{ number_format((float) $item->total, 2, ',', ' ') }} BYN
                </td>
            </tr>
        @endforeach
        @if ((float) $order->delivery_price > 0)
            <tr>
                <td style="padding:10px 0;border-top:1px solid #ececec">Доставка</td>
                <td align="right" style="padding:10px 0;border-top:1px solid #ececec;white-space:nowrap">{{ number_format((float) $order->delivery_price, 2, ',', ' ') }} BYN</td>
            </tr>
        @endif
        <tr>
            <td style="padding:16px 0 4px;border-top:2px solid #101010;color:#101010;font-size:18px;font-weight:700">Итого</td>
            <td align="right" style="padding:16px 0 4px;border-top:2px solid #101010;white-space:nowrap;color:#DC4646;font-size:18px;font-weight:700">{{ number_format((float) $order->total, 2, ',', ' ') }} BYN</td>
        </tr>
    </table>
</x-email.brand-shell>
