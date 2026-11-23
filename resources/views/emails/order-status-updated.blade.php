<x-email.brand-shell
    :subject="$statusDetails['title'].' — заказ '.$order->number.' — KOTLOV.BY'"
    :eyebrow="$statusDetails['eyebrow']"
    :title="$statusDetails['title'].', '.$order->customer_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="$order->user_id ? route('account.orders.show', $order) : url('/')"
    :button-label="$order->user_id ? 'Открыть заказ' : 'Открыть сайт KOTLOV.BY'"
    :notice="$order->user_id ? 'Для просмотра заказа в кабинете войдите в аккаунт.' : 'По вопросам заказа ответьте на это письмо или свяжитесь с магазином.'"
>
    <p style="margin:0 0 18px">
        Статус заказа <strong style="color:#101010">{{ $order->number }}</strong> обновлён.
    </p>
    <p style="margin:0">
        {{ $statusDetails['message'] }}
    </p>
    @include('emails.partials.order-customer-lines')
    @if($order->status === 'shipped' && $order->delivery_type === 'pickup' && $order->onec_order_number)
        <p>Номер для получения: <strong>{{ $order->onec_order_number }}</strong>.</p>
    @elseif($order->status === 'shipped' && $order->evropochta_tracking_number)
        <p>Номер отправления: <strong>{{ $order->evropochta_tracking_number }}</strong>.</p>
    @endif
</x-email.brand-shell>
