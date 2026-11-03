<x-email.brand-shell
    :subject="'Новый заказ клиента · '.$order->number.' — KOTLOV.BY'"
    :eyebrow="'ЗАКАЗ КЛИЕНТА '.$order->number"
    title="Вам передан заказ клиента"
    logo-src="cid:kotlov-logo"
    :button-url="route('b2b.cabinet', ['section' => 'leads'])"
    button-label="Принять заказ в кабинете"
    notice="Контакты клиента откроются в кабинете после того, как вы примете заказ."
>
    <p style="margin:0 0 18px">KOTLOV передаёт заказ с сайта вашей точке продаж «{{ $branch->name }}»{{ $order->delivery_city ? ' — клиент из '.$order->delivery_city : '' }}. Примите его в B2B-кабинете: корзина для закупки по вашей цене соберётся автоматически.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:0 0 18px">
        @foreach($order->items as $line)
            <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf1f5">{{ $line->product_name }}</td>
                <td style="padding:8px 0 8px 12px;border-bottom:1px solid #edf1f5;white-space:nowrap;text-align:right">{{ (int) $line->quantity }} шт.</td>
            </tr>
        @endforeach
    </table>
    @if($order->partner_note)
        <p style="margin:0 0 18px;padding:12px 14px;background:#f5f7fa;border-radius:8px">{{ $order->partner_note }}</p>
    @endif
</x-email.brand-shell>
