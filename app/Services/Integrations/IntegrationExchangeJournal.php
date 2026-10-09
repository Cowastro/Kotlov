<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use Throwable;

class IntegrationExchangeJournal
{
    public function start(
        IntegrationSource $source,
        string $direction,
        string $operation,
        ?string $sessionKey = null,
    ): ?IntegrationExchangeRun {
        try {
            return IntegrationExchangeRun::query()->create([
                'integration_source_id' => $source->id,
                'direction' => $direction,
                'operation' => $operation,
                'status' => 'running',
                'session_key' => $sessionKey,
                'started_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function find(mixed $id): ?IntegrationExchangeRun
    {
        if (! $id) {
            return null;
        }

        try {
            return IntegrationExchangeRun::query()->find($id);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function recordFile(?IntegrationExchangeRun $run, int $bytes): void
    {
        if (! $run) {
            return;
        }

        try {
            $run->increment('files_count');
            $run->increment('bytes_received', max(0, $bytes));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $metrics */
    public function progress(?IntegrationExchangeRun $run, array $metrics): void
    {
        if (! $run) {
            return;
        }

        try {
            $run->update($this->allowedMetrics($metrics));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $metrics */
    public function succeed(
        ?IntegrationExchangeRun $run,
        array $metrics = [],
        bool $accumulate = false,
    ): void {
        if ($run && $accumulate) {
            $metrics = $this->accumulateMetrics($run->fresh() ?? $run, $metrics);
        }

        $this->finish($run, 'success', $metrics);
    }

    /** @param array<string, mixed> $metrics */
    public function fail(?IntegrationExchangeRun $run, Throwable|string $error, array $metrics = []): void
    {
        $metrics['error_message'] = mb_substr(
            $error instanceof Throwable ? $error->getMessage() : $error,
            0,
            65535
        );

        $this->finish($run, 'failed', $metrics);
    }

    /** @param array<string, mixed> $metrics */
    private function finish(?IntegrationExchangeRun $run, string $status, array $metrics): void
    {
        if (! $run) {
            return;
        }

        try {
            $finishedAt = now();
            $run->update(array_merge($this->allowedMetrics($metrics), [
                'status' => $status,
                'finished_at' => $finishedAt,
                'duration_ms' => max(0, (int) $run->started_at->diffInMilliseconds($finishedAt)),
            ]));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function allowedMetrics(array $metrics): array
    {
        return array_intersect_key($metrics, array_flip([
            'items_received', 'items_created', 'items_updated', 'items_skipped',
            'orders_count', 'summary', 'error_message',
        ]));
    }

    /** @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function accumulateMetrics(IntegrationExchangeRun $run, array $metrics): array
    {
        foreach (['items_received', 'items_created', 'items_updated', 'items_skipped', 'orders_count'] as $key) {
            if (array_key_exists($key, $metrics)) {
                $metrics[$key] = (int) $run->{$key} + (int) $metrics[$key];
            }
        }

        if (array_key_exists('summary', $metrics)) {
            $metrics['summary'] = $this->mergeSummary(
                is_array($run->summary) ? $run->summary : [],
                is_array($metrics['summary']) ? $metrics['summary'] : [],
            );
        }

        return $metrics;
    }

    /** @param array<string, mixed> $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeSummary(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_numeric($value) && isset($current[$key]) && is_numeric($current[$key])) {
                $current[$key] = $current[$key] + $value;

                continue;
            }

            if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
                $current[$key] = $this->mergeSummary($current[$key], $value);

                continue;
            }

            $current[$key] = $value;
        }

        return $current;
    }
}
