@php
    $lines = $order->items->reject->isExcluded();
    $pickup = $order->delivery_type === 'pickup';
    [$title, $intro] = match($stage) {
        'received' => ['Заказ получен', 'Спасибо! Заказ зарегистрирован и передан вашему B2B-менеджеру. Мы проверим наличие по каждой позиции и пришлём условия на подтверждение.'],
        'accepted' => ['Заказ принят в работу', 'Менеджер принял заказ и проверяет наличие на складах. Как только всё будет подтверждено, товар встанет в резерв и придёт письмо с условиями.'],
        'proposal' => ['Заказ проверен и зарезервирован', 'Менеджер проверил наличие по каждой позиции и поставил товар в резерв. Подтвердите условия в B2B-кабинете — счёт сформируется сразу и появится в разделе «КП и счета».'],
        'paid' => ['Оплата получена', 'Оплата по заказу поступила. Передаём заказ на склад в комплектацию — сообщим, когда он будет готов к отгрузке.'],
        'credit_paid' => ['Оплата по заказу получена', 'Оплата по заказу с отсрочкой поступила. Спасибо! Кредитный лимит компании снова доступен для новых заказов.'],
        'assembling' => ['Заказ комплектуется', 'Склад собирает ваш заказ.'],
        'ready_to_ship' => ['Заказ готов к отгрузке', $pickup
            ? 'Заказ собран и ждёт вас на складе. Менеджер подскажет время самовывоза.'
            : 'Заказ собран. Менеджер согласует с вами время доставки.'],
        'shipped' => ['Заказ отгружен', 'Заказ передан в доставку или готов к самовывозу.'],
        'completed' => ['Заказ завершён', 'Заказ выполнен и закрыт. Спасибо за работу с KOTLOV.BY! Документы по заказу остаются в B2B-кабинете.'],
        default => ['Заказ отменён', 'Резерв по заказу снят.'],
    };
@endphp
<x-email.brand-shell
    :subject="\App\Mail\B2bOrderStageMail::SUBJECTS[$stage].' · '.$order->number.' — KOTLOV.BY'"
    :eyebrow="'B2B-ЗАКАЗ '.$order->number"
    :title="$title"
    logo-src="cid:kotlov-logo"
    :button-url="route('b2b.cabinet', ['section' => 'orders'])"
    :button-label="$stage === 'proposal' ? 'Подтвердить и получить счёт' : 'Открыть заказ в кабинете'"
    notice="Ссылка ведёт в защищённый кабинет вашей компании. Для просмотра необходимо войти в аккаунт."
>
    <p style="margin:0 0 18px">{{ $intro }}</p>
    @if($note)
        <p style="margin:0 0 18px;padding:12px 14px;background:#f5f7fa;border-radius:8px">{{ $note }}</p>
    @endif
    @if($stage !== 'cancelled')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:0 0 18px">
            @foreach($lines as $line)
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid #edf1f5">{{ $line->product_name }}@if($line->delivery_days !== null)<br><span style="color:#78889b;font-size:12px">срок {{ $line->delivery_days }} дн.</span>@endif</td>
                    <td style="padding:8px 0 8px 12px;border-bottom:1px solid #edf1f5;white-space:nowrap;text-align:right">{{ $line->orderedQuantity() }} шт. × {{ number_format((float) $line->price, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding:10px 0;font-weight:700">Итого</td>
                <td style="padding:10px 0 10px 12px;font-weight:700;text-align:right;white-space:nowrap">{{ number_format((float) $order->total, 2, ',', ' ') }} BYN</td>
            </tr>
        </table>
        @if($order->items->contains->isExcluded())
            <p style="margin:0 0 18px;color:#78889b;font-size:13px">Часть позиций убрана из заказа — причина указана в кабинете.</p>
        @endif
    @endif
</x-email.brand-shell>
