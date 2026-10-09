<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;

class OneCSetupReadiness
{
    public function __construct(private IntegrationMonitoringWindow $monitoringWindow) {}

    /** @return array<string, mixed> */
    public function snapshot(IntegrationSource $source): array
    {
        $latestCatalog = $this->latestSuccessfulRun($source, 'inbound', 'catalog');
        $latestOrders = $this->latestSuccessfulRun($source, 'outbound', 'orders');
        $latestStatuses = $this->latestSuccessfulRun($source, 'inbound', 'order_statuses');
        $latestRun = $source->exchangeRuns()->latest('started_at')->first();
        $catalogFresh = $latestCatalog?->finished_at?->gte(
            now()->subMinutes($source->staleAfterMinutes())
        ) ?? false;

        $checks = [
            $this->check(
                'Источник включён',
                $source->is_active,
                'Включите источник, иначе сайт отклонит обмен.',
            ),
            $this->check(
                'Учётные данные обмена заданы',
                filled($source->username) && filled($source->password_hash),
                'Задайте отдельного пользователя обмена и пароль в настройках источника.',
            ),
            $this->check(
                'Защита старых заказов включена',
                (bool) $this->monitoringWindow->ordersStartAtFor($source),
                'Сохраните источник: система зафиксирует дату, раньше которой заказы в 1С не отправляются.',
            ),
            $this->check(
                'Каталог, цены и остатки поступают',
                (bool) $latestCatalog,
                'Запустите обмен товарами в 1С и дождитесь успешной записи в журнале.',
                $catalogFresh ? 'success' : ($latestCatalog ? 'warning' : 'pending'),
            ),
            $this->check(
                'Новые заказы запрашиваются из 1С',
                (bool) $latestOrders,
                'Включите обмен заказами в узле 1С и выполните один контрольный цикл.',
            ),
            $this->check(
                'Статусы заказов возвращаются на сайт',
                (bool) $latestStatuses,
                'После загрузки заказа отправьте из 1С его статус в том же регулярном обмене.',
            ),
        ];

        $completed = collect($checks)->where('complete', true)->count();

        return [
            'source' => $source,
            'endpoint' => url('/1c/exchange/'.$source->code),
            'checks' => $checks,
            'completed' => $completed,
            'total' => count($checks),
            'ready' => $completed === count($checks),
            'latest_run' => $latestRun,
            'latest_catalog' => $latestCatalog,
            'latest_orders' => $latestOrders,
            'latest_statuses' => $latestStatuses,
            'catalog_fresh' => $catalogFresh,
        ];
    }

    private function latestSuccessfulRun(
        IntegrationSource $source,
        string $direction,
        string $operation,
    ): ?IntegrationExchangeRun {
        return $source->exchangeRuns()
            ->where('direction', $direction)
            ->where('operation', $operation)
            ->where('status', 'success')
            ->latest('finished_at')
            ->first();
    }

    /** @return array{label: string, complete: bool, status: string, next_step: string} */
    private function check(
        string $label,
        bool $complete,
        string $nextStep,
        ?string $status = null,
    ): array {
        return [
            'label' => $label,
            'complete' => $complete,
            'status' => $status ?? ($complete ? 'success' : 'pending'),
            'next_step' => $complete ? 'Готово' : $nextStep,
        ];
    }
}
