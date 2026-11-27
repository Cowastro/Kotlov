<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusSms;
use App\Services\SmsByClient;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderStatusSmsNotifier
{
    private const REVIEW_EVENT = 'customer-review';

    public function __construct(private readonly SmsByClient $sms) {}

    public function record(Order $order, string $status, string $eventKey): void
    {
        if ($order->is_internal || ! $this->sms->configuredForSms() || str_starts_with($order->number, 'DBY-')) {
            return;
        }

        $text = match ($status) {
            'new' => 'принят.',
            'checking' => 'проверяем наличие.',
            'confirmed' => 'принят в обработку. Состав и сроки — в письме или у менеджера.',
            'processing' => 'комплектация.',
            'waiting_payment' => 'ожидает оплаты.',
            'paid' => 'оплачен.',
            'shipped' => $order->isOneCOrderShipped() ? 'отгружен.' : ($order->delivery_type === 'pickup' ? 'готов к самовывозу.' : 'передан в доставку.'),
            'delivered' => 'доставлен.',
            'completed' => 'завершён.',
            'cancelled' => 'отменён.',
            default => null,
        };
        if ($text === null) {
            return;
        }

        $message = 'KOTLOV: '.$order->number.' '.$text;
        // Self-pickup: one number for the customer — the 1C order the warehouse looks for.
        if ($status === 'shipped' && $order->delivery_type === 'pickup' && filled($order->onec_order_number) && ! $order->onec_deleted) {
            $message = 'KOTLOV: заказ '.$order->onec_order_number.' '.$text;
        }
        if ($status === 'shipped' && $order->delivery_type === 'evropochta'
            && preg_match('/^BY\d{12}$/D', (string) $order->evropochta_tracking_number)) {
            $message .= ' Трек '.$order->evropochta_tracking_number.'.';
        }

        DB::table('order_status_sms')->insertOrIgnore([
            'order_id' => $order->getKey(),
            'event_key' => $eventKey,
            'order_status' => $status,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status === 'delivered'
            && ! in_array($order->getRawOriginal('status'), ['delivered', 'completed', 'cancelled'], true)
            && $order->canAutoRequestCustomerReview()) {
            DB::table('order_status_sms')->insertOrIgnore([
                'order_id' => $order->getKey(),
                'event_key' => self::REVIEW_EVENT,
                'order_status' => 'customer_review',
                'message' => 'KOTLOV: Оставьте отзыв',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function sendPending(): int
    {
        if (! $this->sms->configuredForSms()) {
            return 0;
        }

        $sent = 0;
        $messages = OrderStatusSms::query()->where('state', 'pending')
            ->with('order:id,customer_phone,status,payment_status')->orderBy('id')->limit(30)->get();
        foreach ($messages as $message) {
            $sent += (int) ($message->event_key === self::REVIEW_EVENT
                ? app(OrderReviewSms::class)->sendAutomatically($message)
                : $this->send($message));
        }

        return $sent;
    }

    private function send(OrderStatusSms $message): bool
    {
        $stage = $message->order_status;
        if (array_key_exists($stage, Order::STATUSES) && $stage !== $message->order?->status
            && ! ($stage === 'new' && in_array($message->order?->status, ['new', 'checking', 'confirmed', 'processing'], true))) {
            OrderStatusSms::whereKey($message->id)->where('state', 'pending')->update(['state' => 'outdated']);

            return false;
        }
        $phone = $this->recipientPhone($message->order?->customer_phone);
        $row = OrderStatusSms::query()->whereKey($message->id);
        $claimed = (clone $row)->where('state', 'pending')
            ->update(['state' => $phone === null ? 'no_phone' : 'unconfirmed']);
        if ($claimed !== 1 || $phone === null) {
            return false;
        }

        try {
            $id = $this->sms->sendSms($phone, (string) config('services.sms_by.alphaname_id'), $message->message);
        } catch (RuntimeException) {
            // A timeout may mean the provider accepted the message. Keep the result for manual verification.
            return false;
        }

        $row->update(['state' => 'accepted', 'sms_id' => $id, 'sent_at' => now()]);

        return true;
    }

    private function recipientPhone(?string $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\+?[0-9 ().-]+$/D', $value)) {
            return null;
        }

        $local = PhoneNumber::format($value);
        if ($local !== null) {
            return PhoneNumber::normalize($local);
        }

        $international = str_replace([' ', '(', ')', '.', '-'], '', $value);

        return preg_match('/^\+[1-9]\d{7,14}$/D', $international) ? substr($international, 1) : null;
    }
}
