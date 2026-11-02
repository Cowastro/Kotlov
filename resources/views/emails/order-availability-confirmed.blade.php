<x-email.brand-shell
    :subject="'Наличие по заказу '.$order->number.' подтверждено — KOTLOV.BY'"
    eyebrow="ЗАКАЗ ПОДТВЕРЖДЁН"
    :title="'Наличие подтверждено, '.$order->customer_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="$order->commercialDocument ? route('b2b.cabinet', ['section' => 'documents']) : route('account.orders.show', $order)"
    :button-label="$order->commercialDocument ? 'Открыть документы B2B' : 'Открыть заказ'"
    notice="Ссылка ведёт в ваш защищённый кабинет KOTLOV.BY. Для просмотра необходимо войти в аккаунт."
>
    <p style="margin:0 0 18px">
        Менеджер подтвердил наличие и возможность исполнения всех позиций заказа
        <strong style="color:#101010">{{ $order->number }}</strong>.
    </p>
    @if($order->commercialDocument)
        <p style="margin:0 0 18px">Счёт <strong>{{ $order->commercialDocument->number }}</strong> приложен к письму и сохранён в разделе «Документы» B2B-кабинета.</p>
        <p style="margin:0">После поступления оплаты заказ будет передан в исполнение.@if($order->payment_type === 'invoice_deferred') Для компании применена согласованная отсрочка платежа.@endif</p>
    @else
        <p style="margin:0">В личном кабинете доступны состав заказа, итоговая сумма, доставка и актуальный статус оплаты.</p>
    @endif
</x-email.brand-shell>
