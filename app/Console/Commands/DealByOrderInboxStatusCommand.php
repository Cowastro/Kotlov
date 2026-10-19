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

        return self::SUCCESS;
    }
}
