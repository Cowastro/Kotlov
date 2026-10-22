<x-email.brand-shell
    :subject="$statusDetails['title'].' — заказ '.$order->number.' — KOTLOV.BY'"
    :eyebrow="$statusDetails['eyebrow']"
    :title="$statusDetails['title'].', '.$order->customer_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="route('account.orders.show', $order)"
    button-label="Открыть заказ"
    notice="Ссылка ведёт в ваш защищённый личный кабинет KOTLOV.BY. Для просмотра заказа необходимо войти в аккаунт."
>
    <p style="margin:0 0 18px">
        Статус заказа <strong style="color:#101010">{{ $order->number }}</strong> обновлён.
    </p>
    <p style="margin:0">
        {{ $statusDetails['message'] }}
    </p>
</x-email.brand-shell>
