<x-email.brand-shell
    :subject="'Наличие по заказу '.$order->number.' подтверждено — KOTLOV.BY'"
    eyebrow="ЗАКАЗ ПОДТВЕРЖДЁН"
    :title="'Наличие подтверждено, '.$order->customer_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="route('account.orders.show', $order)"
    button-label="Открыть заказ"
    notice="Ссылка ведёт в ваш защищённый личный кабинет KOTLOV.BY. Для просмотра заказа необходимо войти в аккаунт."
>
    <p style="margin:0 0 18px">
        Менеджер подтвердил наличие и возможность исполнения всех позиций заказа
        <strong style="color:#101010">{{ $order->number }}</strong>.
    </p>
    <p style="margin:0">
        В личном кабинете доступны состав заказа, итоговая сумма, доставка и актуальный статус оплаты.
    </p>
</x-email.brand-shell>
