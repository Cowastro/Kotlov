<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByClient;
use Illuminate\Console\Command;
use Throwable;

class ProbeDealByCommand extends Command
{
    protected $signature = 'deal:probe';

    protected $description = 'Safely verify read-only access to the Deal.by products and orders API';

    public function handle(DealByClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('DEAL_BY_API_TOKEN не задан. Добавьте токен в .env и повторите команду.');

            return self::FAILURE;
        }

        $this->info('Проверяем Deal.by только на чтение. Данные не изменяются.');

        try {
            $products = $client->listProducts();
            $this->line('Товары: доступ подтверждён, получено в пробе: '.$this->itemsCount($products, 'products'));

            $orders = $client->listOrders();
            $this->line('Заказы: доступ подтверждён, получено в пробе: '.$this->itemsCount($orders, 'orders'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Deal.by API недоступен: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Подключение готово. Записывающие методы не вызывались.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $payload */
    private function itemsCount(array $payload, string $key): int
    {
        $items = $payload[$key] ?? [];

        return is_array($items) ? count($items) : 0;
    }
}
