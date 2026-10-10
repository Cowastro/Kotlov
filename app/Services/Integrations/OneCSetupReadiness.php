<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;

class OneCSetupReadiness
{
    public function __construct(
        private IntegrationMonitoringWindow $monitoringWindow,
        private IntegrationFlowHealth $flowHealth,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(IntegrationSource $source): array
    {
        $flowSnapshot = $this->flowHealth->snapshot($source);
        $flows = $flowSnapshot['flows'];
        $latestCatalog = $flows['catalog']['latest_success'];
        $latestOrders = $flows['orders']['latest_success'];
        $latestStatuses = $flows['order_statuses']['latest_success'];
        $latestRun = $source->exchangeRuns()->latest('started_at')->first();
        $catalogFresh = $flows['catalog']['status'] === 'healthy';
        $stagedProductsCount = $source->products()->count();
        $latestStagedAt = $source->products()
            ->whereNotNull('last_seen_at')
            ->latest('last_seen_at')
            ->first(['last_seen_at'])
            ?->last_seen_at;

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
                '1С успешно авторизовалась на сайте',
                (bool) $source->last_authenticated_at,
                'Запустите проверку соединения в 1С. После правильного URL, логина и пароля этот пункт станет зелёным.',
            ),
            $this->check(
                'Защита старых заказов включена',
                (bool) $this->monitoringWindow->ordersStartAtFor($source),
                'Сохраните источник: система зафиксирует дату, раньше которой заказы в 1С не отправляются.',
            ),
            $this->flowCheck(
                'Каталог, цены и остатки поступают',
                $flows['catalog'],
                'Запустите обмен товарами в 1С и дождитесь успешной записи в журнале.',
            ),
            $this->flowCheck(
                'Новые заказы запрашиваются из 1С',
                $flows['orders'],
                'Включите обмен заказами в узле 1С и выполните один контрольный цикл.',
            ),
            $this->flowCheck(
                'Статусы заказов возвращаются на сайт',
                $flows['order_statuses'],
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
            'flow_health' => $flowSnapshot['health'],
            'staged_products_count' => $stagedProductsCount,
            'latest_staged_at' => $latestStagedAt,
        ];
    }

    /** @return array{label: string, complete: bool, status: string, icon: string, next_step: string} */
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
            'icon' => $complete ? '✓' : '!',
            'next_step' => $complete ? 'Готово' : $nextStep,
        ];
    }

    /**
     * @param  array<string, mixed>  $flow
     * @return array{label: string, complete: bool, status: string, icon: string, next_step: string}
     */
    private function flowCheck(string $label, array $flow, string $firstRunStep): array
    {
        return match ($flow['status']) {
            'healthy' => [
                'label' => $label,
                'complete' => true,
                'status' => 'success',
                'icon' => '✓',
                'next_step' => 'Готово — обмен укладывается в заданный интервал.',
            ],
            'disabled' => [
                'label' => $label,
                'complete' => true,
                'status' => 'disabled',
                'icon' => '—',
                'next_step' => 'Не используется для этого источника.',
            ],
            'running' => [
                'label' => $label,
                'complete' => false,
                'status' => 'running',
                'icon' => '↻',
                'next_step' => 'Обмен выполняется. Дождитесь завершения и обновите страницу.',
            ],
            'failed' => [
                'label' => $label,
                'complete' => false,
                'status' => 'failed',
                'icon' => '×',
                'next_step' => 'Последняя попытка завершилась ошибкой. Откройте журнал, устраните причину и повторите обмен.',
            ],
            'stale' => [
                'label' => $label,
                'complete' => false,
                'status' => 'warning',
                'icon' => '!',
                'next_step' => 'Последний успешный обмен устарел. Проверьте регламентное задание и запустите контрольный цикл.',
            ],
            default => [
                'label' => $label,
                'complete' => false,
                'status' => 'pending',
                'icon' => '!',
                'next_step' => $firstRunStep,
            ],
        };
    }
}
