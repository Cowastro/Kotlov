<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusSms;
use App\Services\PhoneVerificationService;
use App\Services\SmsByClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OrderReviewSms
{
    public function message(Order $order): string
    {
        $token = DB::transaction(function () use ($order): string {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->review_sms_token) {
                $locked->forceFill(['review_sms_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')])->save();
            }

            return $locked->review_sms_token;
        });
        $message = 'KOTLOV: Оставьте отзыв: '.rtrim(config('app.url'), '/').'/r/'.$token;
        if (strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')) / 2 > 70) {
            throw ValidationException::withMessages(['phone' => 'Ссылка слишком длинная для одной СМС. Обратитесь к администратору.']);
        }

        return $message;
    }

    public function send(Order $order, string $phone): string
    {
        abort_unless(OrderResource::canEdit($order), 403);
        $phone = app(PhoneVerificationService::class)->normalize($phone);
        $sender = (string) config('services.sms_by.alphaname_id');
        if ($sender === '' || ! config('services.sms_by.enabled')) {
            throw ValidationException::withMessages(['phone' => 'Отправка СМС не настроена. Обратитесь к администратору.']);
        }
        $lock = Cache::lock('order-review-sms:'.$order->id, 60);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['phone' => 'СМС уже отправляется. Подождите.']);
        }
        try {
            $order->refresh();
            if (! $order->canRequestCustomerReview()) {
                throw ValidationException::withMessages(['phone' => 'Запрос отзыва доступен после завершения сделки.']);
            }
            if ($order->review_sms_attempted_at && Carbon::parse($order->review_sms_attempted_at)->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['phone' => 'Повторная отправка доступна через минуту. При ошибке сначала проверьте журнал SMS.BY.']);
            }
            return $this->deliver($order, $phone, $sender, $this->message($order), auth()->id());
        } finally {
            $lock->release();
        }
    }

    public function sendAutomatically(OrderStatusSms $request): bool
    {
        $lock = Cache::lock('order-review-sms:'.$request->order_id, 60);
        if (! $lock->get()) {
            return false;
        }

        try {
            $pending = OrderStatusSms::whereKey($request->id)->where('state', 'pending');
            if (! $pending->exists()) {
                return false;
            }

            $order = $request->order?->fresh();
            if (! $order?->canAutoRequestCustomerReview() || $order->customerReviews()->exists()) {
                $pending->update(['state' => 'not_needed']);

                return false;
            }

            try {
                $phone = app(PhoneVerificationService::class)->normalize($order->customer_phone);
            } catch (ValidationException) {
                $pending->update(['state' => 'no_phone']);

                return false;
            }

            try {
                $message = $this->message($order);
            } catch (ValidationException) {
                $pending->update(['state' => 'unavailable']);

                return false;
            }

            // Claim before contacting the provider. A timeout can mean the SMS was accepted.
            if ($pending->update(['state' => 'unconfirmed', 'message' => $message]) !== 1) {
                return false;
            }

            try {
                $this->deliver($order, $phone, (string) config('services.sms_by.alphaname_id'), $message, null, $request);
            } catch (RuntimeException) {
                return false;
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    private function deliver(Order $order, string $phone, string $sender, string $message, ?int $userId, ?OrderStatusSms $request = null): string
    {
        $order->forceFill(['review_sms_attempted_at' => now()])->save();
        $id = app(SmsByClient::class)->sendSms($phone, $sender, $message);
        DB::transaction(function () use ($order, $id, $phone, $userId, $request): void {
            $sentAt = now();
            $order->forceFill(['review_sms_sent_at' => $sentAt, 'review_sms_id' => $id])->save();
            $comment = $request ? 'Автоматически отправлена ссылка на отзыв' : 'Ссылка на отзыв отправлена';
            $order->statusHistory()->create([
                'user_id' => $userId, 'status_from' => $order->status, 'status_to' => $order->status,
                'comment' => $comment.' по СМС на +'.$phone.'. Принято SMS.BY, ID: '.$id.'.',
            ]);
            if ($request) {
                OrderStatusSms::whereKey($request->id)->update(['state' => 'accepted', 'sms_id' => $id, 'sent_at' => $sentAt]);
            }
        });

        return $id;
    }
}
