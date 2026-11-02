<x-email.brand-shell
    :subject="'Счёт по B2B-заказу '.$order->number.' — KOTLOV.BY'"
    eyebrow="B2B-ЗАКАЗ СОГЛАСОВАН"
    :title="'Счёт готов, '.$order->contact_name.'!'"
    logo-src="cid:kotlov-logo"
    :button-url="route('b2b.cabinet', ['section' => 'documents'])"
    button-label="Открыть документы B2B"
    notice="Ссылка ведёт в защищённый кабинет вашей компании. Для просмотра необходимо войти в аккаунт."
>
    <p style="margin:0 0 18px">
        Наличие, состав и цены заказа <strong style="color:#101010">{{ $order->number }}</strong> проверены и согласованы.
    </p>
    <p style="margin:0 0 18px">
        Счёт <strong>{{ $order->commercialDocument?->number }}</strong> приложен к письму и сохранён в разделе «Документы» B2B-кабинета.
    </p>
    @if($order->payment_type === 'invoice_deferred')
        <p style="margin:0">Для компании действуют согласованные условия отсрочки. После подтверждения лимита менеджер передаст заказ в комплектацию.</p>
    @else
        <p style="margin:0">После поступления оплаты менеджер передаст заказ в комплектацию.</p>
    @endif
</x-email.brand-shell>
