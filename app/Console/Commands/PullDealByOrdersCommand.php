<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByOrderCapture;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class PullDealByOrdersCommand extends Command
{
    protected $signature = 'deal:pull-orders
        {--from= : Начало периода в ISO 8601; только для контролируемого повторного импорта}
        {--to= : Конец периода в ISO 8601}
        {--force : Запустить даже когда автоматический приём выключен в конфигурации}';

    protected $description = 'Capture Deal.by orders into an encrypted idempotent inbox without creating local orders';

    public function handle(DealByOrderCapture $capture): int
    {
        if (! config('services.deal_by.orders_capture_enabled') && ! $this->option('force')) {
            $this->warn('Приём заказов Deal.by выключен: DEAL_BY_ORDERS_CAPTURE_ENABLED=false.');

            return self::SUCCESS;
        }

        try {
            $from = $this->option('from') ? CarbonImmutable::parse((string) $this->option('from'))->utc() : null;
            $to = $this->option('to') ? CarbonImmutable::parse((string) $this->option('to'))->utc() : null;
            $result = $capture->capture($from, $to);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Приём заказов Deal.by завершился ошибкой: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Период', 'Получено', 'Новых', 'Изменено', 'Без изменений'], [[
            $result['from'].' — '.$result['to'],
            $result['received'],
            $result['created'],
            $result['updated'],
            $result['unchanged'],
        ]]);
        $this->info('Заказы сохранены во входящий журнал и переданы в рабочую очередь менеджеров.');

        return self::SUCCESS;
    }
}
