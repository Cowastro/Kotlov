<?php

namespace App\Services\Integrations;

use App\Models\IntegrationMonitorHeartbeat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class IntegrationMonitorHealth
{
    /** @return array{health:string, heartbeat:?IntegrationMonitorHeartbeat, message:string} */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        if (! Schema::hasTable('integration_monitor_heartbeats')) {
            return [
                'health' => 'unknown',
                'heartbeat' => null,
                'message' => 'Монитор ожидает обновления базы данных',
            ];
        }

        $heartbeat = IntegrationMonitorHeartbeat::query()
            ->where('name', IntegrationMonitorHeartbeat::ISSUE_SCANNER)
            ->first();

        if (! $heartbeat) {
            return [
                'health' => 'unknown',
                'heartbeat' => null,
                'message' => 'Монитор ещё не запускался',
            ];
        }

        if ($heartbeat->status === 'failed') {
            return [
                'health' => 'failed',
                'heartbeat' => $heartbeat,
                'message' => $heartbeat->error_message ?: 'Последний запуск завершился ошибкой',
            ];
        }

        if ($heartbeat->status === 'running') {
            $isStale = ! $heartbeat->started_at || $heartbeat->started_at->lt($now->subMinutes(5));

            return [
                'health' => $isStale ? 'stale' : 'running',
                'heartbeat' => $heartbeat,
                'message' => $isStale ? 'Проверка выполняется более 5 минут' : 'Проверка выполняется',
            ];
        }

        $isStale = ! $heartbeat->finished_at || $heartbeat->finished_at->lt($now->subMinutes(3));

        return [
            'health' => $isStale ? 'stale' : 'healthy',
            'heartbeat' => $heartbeat,
            'message' => $isStale
                ? 'Планировщик не подтверждал работу более 3 минут'
                : 'Очередь проверяется каждую минуту',
        ];
    }
}
