<?php

namespace App\Services\Market;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceObservation;
use App\Models\MarketPriceSource;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MarketPriceCollectionManager
{
    public function __construct(
        private readonly MarketPriceCollectionPolicy $policy,
        private readonly MarketPriceObservationRecorder $recorder,
    ) {}

    public function start(
        MarketPriceSource $source,
        string $trigger = 'manual',
        ?User $actor = null,
        ?CarbonInterface $now = null,
    ): MarketPriceCollectionRun {
        $now ??= now();
        $decision = $this->policy->canStart($source, $trigger, $now);

        return MarketPriceCollectionRun::query()->create([
            'market_price_source_id' => $source->id,
            'session_uuid' => (string) Str::uuid(),
            'trigger' => $trigger,
            'status' => $decision['allowed'] ? 'running' : 'blocked',
            'events' => $decision['allowed'] ? [] : [[
                'level' => 'error',
                'code' => $decision['code'],
                'message' => $decision['message'],
                'at' => $now->toIso8601String(),
            ]],
            'summary' => $decision['allowed'] ? null : $decision['message'],
            'created_by' => $actor?->id,
            'started_at' => $now,
            'finished_at' => $decision['allowed'] ? null : $now,
            'error_count' => $decision['allowed'] ? 0 : 1,
        ]);
    }

    public function record(
        MarketPriceCollectionRun $run,
        Product $product,
        array $data,
        ?CarbonInterface $now = null,
    ): ?MarketPriceObservation {
        $now ??= now();
        $url = trim((string) ($data['url'] ?? ''));

        return DB::transaction(function () use ($run, $product, $data, $now, $url): ?MarketPriceObservation {
            /** @var MarketPriceCollectionRun $lockedRun */
            $lockedRun = MarketPriceCollectionRun::query()->lockForUpdate()->with('source')->findOrFail($run->id);
            $source = $lockedRun->source;
            $decision = $this->policy->canRequest($source, $lockedRun, $url, $now);

            if (! $decision['allowed']) {
                $this->appendEvent($lockedRun, 'error', $decision['code'], $decision['message'], $now);
                $lockedRun->increment('skipped_count');
                $lockedRun->increment('error_count');

                return null;
            }

            $lockedRun->increment('requested_count');

            try {
                $observation = $this->recorder->record($source, $product, $data);
                $lockedRun->increment('recorded_count');

                return $observation;
            } catch (Throwable $exception) {
                $lockedRun->increment('error_count');
                $this->appendEvent($lockedRun, 'error', 'record_failed', $exception->getMessage(), $now);

                return null;
            }
        });
    }

    public function warn(MarketPriceCollectionRun $run, string $code, string $message, ?CarbonInterface $now = null): void
    {
        DB::transaction(function () use ($run, $code, $message, $now): void {
            $lockedRun = MarketPriceCollectionRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->status !== 'running') {
                return;
            }

            $lockedRun->increment('warning_count');
            $this->appendEvent($lockedRun, 'warning', $code, $message, $now ?? now());
        });
    }

    public function finish(MarketPriceCollectionRun $run, ?CarbonInterface $now = null): MarketPriceCollectionRun
    {
        $now ??= now();

        return DB::transaction(function () use ($run, $now): MarketPriceCollectionRun {
            /** @var MarketPriceCollectionRun $lockedRun */
            $lockedRun = MarketPriceCollectionRun::query()->lockForUpdate()->with('source')->findOrFail($run->id);
            if ($lockedRun->status !== 'running') {
                return $lockedRun;
            }

            $status = match (true) {
                $lockedRun->error_count > 0 && $lockedRun->recorded_count === 0 => 'failed',
                $lockedRun->error_count > 0 || $lockedRun->warning_count > 0 => 'warning',
                default => 'success',
            };
            $summary = 'Запросов: '.$lockedRun->requested_count
                .' · записано: '.$lockedRun->recorded_count
                .' · пропущено: '.$lockedRun->skipped_count
                .' · ошибок: '.$lockedRun->error_count;

            $lockedRun->update([
                'status' => $status,
                'summary' => $summary,
                'finished_at' => $now,
            ]);

            $lockedRun->source->update([
                'last_collection_at' => $now,
                'next_collection_at' => $now->copy()->addMinutes($lockedRun->source->collection_interval_minutes),
            ]);

            return $lockedRun->fresh('source');
        });
    }

    private function appendEvent(
        MarketPriceCollectionRun $run,
        string $level,
        ?string $code,
        ?string $message,
        CarbonInterface $at,
    ): void {
        $events = collect($run->events ?? [])->push([
            'level' => $level,
            'code' => $code,
            'message' => mb_substr((string) $message, 0, 1000),
            'at' => $at->toIso8601String(),
        ])->take(-100)->values()->all();

        $run->update(['events' => $events]);
    }
}
