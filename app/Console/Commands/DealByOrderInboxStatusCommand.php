<?php

namespace App\Console\Commands;

use App\Models\DealByOrderInbox;
use App\Models\MarketplaceSyncCursor;
use Illuminate\Console\Command;

class DealByOrderInboxStatusCommand extends Command
{
    protected $signature = 'deal:order-inbox-status';

    protected $description = 'Show Deal.by order capture health without exposing customer data';

    public function handle(): int
    {
        $cursor = MarketplaceSyncCursor::query()
            ->where('channel', 'deal_by')
            ->where('stream', 'orders')
            ->first();

        $this->table(['Показатель', 'Значение'], [
            ['Приём включён', config('services.deal_by.orders_capture_enabled') ? 'да' : 'нет'],
            ['Всего во входящем журнале', DealByOrderInbox::query()->count()],
            ['Ожидают обработки', DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_PENDING)->count()],
            ['Обработаны', DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_PROCESSED)->count()],
            ['Ошибки обработки', DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_FAILED)->count()],
            ['Последний увиденный заказ', DealByOrderInbox::query()->max('last_seen_at') ?: '—'],
            ['Курсор API', $cursor?->cursor_at?->toIso8601String() ?: '—'],
            ['Последний успешный запуск', $cursor?->last_succeeded_at?->toIso8601String() ?: '—'],
            ['Последняя ошибка загрузки', $cursor?->last_error ?: '—'],
        ]);

        // The same health as counts and minutes only: safe to print in the server workflow.
        $minutes = fn ($at): int => $at ? (int) now()->diffInMinutes($at, true) : -1;
        $error = (string) ($cursor?->last_error ?? '');
        $metrics = [
            'enabled' => (int) (bool) config('services.deal_by.orders_capture_enabled'),
            'token' => (int) filled(config('services.deal_by.token')),
            'inbox_total' => DealByOrderInbox::query()->count(),
            'pending' => DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_PENDING)->count(),
            'processed' => DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_PROCESSED)->count(),
            'failed' => DealByOrderInbox::query()->where('processing_status', DealByOrderInbox::STATUS_FAILED)->count(),
            'last_seen_min' => $minutes(DealByOrderInbox::query()->max('last_seen_at')),
            'newest_order_min' => $minutes(DealByOrderInbox::query()->max('remote_created_at')),
            'cursor_min' => $minutes($cursor?->cursor_at),
            'last_started_min' => $minutes($cursor?->last_started_at),
            'last_success_min' => $minutes($cursor?->last_succeeded_at),
            'last_failed_min' => $minutes($cursor?->last_failed_at),
            'has_error' => (int) ($error !== ''),
            'error_http' => preg_match('/\b([45]\d\d)\b/', $error, $m) ? (int) $m[1] : 0,
            'error_lock' => (int) str_contains($error, 'Другой процесс'),
            'error_timeout' => (int) (bool) preg_match('/timed? ?out|cURL error 28/i', $error),
            'error_params' => (int) str_contains($error, 'отклонил параметры'),
        ];
        foreach ($metrics as $key => $value) {
            $this->line("metric.deal_order_inbox.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
