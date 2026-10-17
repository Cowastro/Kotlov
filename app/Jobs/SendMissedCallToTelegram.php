<?php

namespace App\Jobs;

use App\Models\TelephonyCall;
use App\Services\Telephony\MissedCallTelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendMissedCallToTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $telephonyCallId) {}

    public function handle(MissedCallTelegramNotifier $notifier): void
    {
        if (app()->environment('testing') && ! config('services.telegram.allow_in_tests', false)) {
            return;
        }

        $call = TelephonyCall::query()->find($this->telephonyCallId);

        if (! $call || $call->telegram_notified_at) {
            return;
        }

        if (! config('services.telegram.bot_token') || ! config('services.telegram.telephony_chat_id')) {
            return;
        }

        if (! $notifier->send($call)) {
            throw new RuntimeException('Не удалось отправить уведомление о пропущенном звонке в Telegram.');
        }
    }
}
