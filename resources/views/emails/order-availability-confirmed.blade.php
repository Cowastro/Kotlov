<x-email.brand-shell
    :subject="'Заказ '.$order->number.': состав и состояние — KOTLOV.BY'"
    eyebrow="ВАШ ЗАКАЗ"
    :title="$order->customerStatusLabel()"
    logo-src="cid:kotlov-logo"
    :button-url="$order->user_id ? ($order->commercialDocument ? route('b2b.cabinet', ['section' => 'documents']) : route('account.orders.show', $order)) : url('/')"
    :button-label="$order->user_id ? 'Открыть заказ в кабинете' : 'Открыть сайт KOTLOV.BY'"
    :notice="$order->user_id ? 'Для просмотра заказа в кабинете войдите в аккаунт.' : 'По вопросам состава и сроков ответьте на это письмо или свяжитесь с магазином.'"
>
    <p style="margin:0 0 18px">
        Мы приняли в работу все позиции заказа <strong>{{ $order->number }}</strong>.
        Мы оформляем резерв доступных товаров. Недостающие сохраняются в заказе для резервирования по мере поступления.
    </p>
    @include('emails.partials.order-customer-lines')
    @if($order->fulfillment_customer_note)<p>{{ $order->fulfillment_customer_note }}</p>@endif
    @if($order->hasPendingSupply())<p>Часть товаров ожидается или уточняется. Менеджер согласует с вами сроки, комплектность и отгрузку.</p>@endif
    @if($order->commercialDocument)
        <p style="margin:0 0 18px">Счёт <strong>{{ $order->commercialDocument->number }}</strong> приложен к письму и сохранён в разделе «Документы» B2B-кабинета.</p>
        <p style="margin:0">После поступления оплаты заказ будет передан в исполнение.@if($order->payment_type === 'invoice_deferred') Для компании применена согласованная отсрочка платежа.@endif</p>
    @else
        <p style="margin:0">В личном кабинете доступны состав заказа, итоговая сумма, доставка и актуальный статус оплаты.</p>
    @endif
</x-email.brand-shell>
