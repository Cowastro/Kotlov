<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\PhoneVerificationService;
use App\Services\SmsByClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $message = $this->message($order);
            $order->forceFill(['review_sms_attempted_at' => now()])->save();
            $id = app(SmsByClient::class)->sendSms($phone, $sender, $message);
            DB::transaction(function () use ($order, $id, $phone): void {
                $order->forceFill(['review_sms_sent_at' => now(), 'review_sms_id' => $id])->save();
                $order->statusHistory()->create([
                    'user_id' => auth()->id(), 'status_from' => $order->status, 'status_to' => $order->status,
                    'comment' => 'Ссылка на отзыв отправлена по СМС на +'.$phone.'. Принято SMS.BY, ID: '.$id.'.',
                ]);
            });

            return $id;
        } finally {
            $lock->release();
        }
    }
}
