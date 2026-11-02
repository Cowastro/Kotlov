@php
    $lines = $order->items->reject->isExcluded();
    $title = match($stage) {
        'proposal' => 'Заказ проверен и зарезервирован',
        'shipped' => 'Заказ отгружен',
        default => 'Заказ отменён',
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
    @if($stage === 'proposal')
        <p style="margin:0 0 18px">Менеджер проверил наличие по каждой позиции и поставил товар в резерв. Подтвердите условия в B2B-кабинете — счёт сформируется сразу и появится в разделе «КП и счета».</p>
    @elseif($stage === 'shipped')
        <p style="margin:0 0 18px">Заказ передан в доставку или готов к самовывозу.</p>
    @else
        <p style="margin:0 0 18px">Резерв по заказу снят.</p>
    @endif
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
