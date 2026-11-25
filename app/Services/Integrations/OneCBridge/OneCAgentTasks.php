<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «Мост 1С»: the queue of tasks for the agent on the 1C server. A subject (a price batch, an order) has at most one
 * active task; the agent takes the oldest one, and a task taken but never answered goes back after half an hour.
 */
class OneCAgentTasks
{
    public const STALE_MINUTES = 30;

    /** After this many takes without an answer the task stops being handed out. */
    public const MAX_ATTEMPTS = 5;

    /** The agent is considered offline after this many minutes without a call (it runs every 5 minutes). */
    public const OFFLINE_MINUTES = 15;

    public function handler(string $type): OneCAgentTaskHandler
    {
        $class = config('onec.agent.handlers.'.$type);
        if (! $class) {
            throw new DomainException('Неизвестный тип задачи 1С: '.$type);
        }

        return app($class);
    }

    /** @return array<string, string> type => label */
    public function types(): array
    {
        return collect(config('onec.agent.handlers', []))->map(fn (string $class): string => app($class)->label())->all();
    }

    /** @param array<string, mixed>|null $payload for a task without a subject: what exactly to do */
    public function enqueue(string $type, ?Model $subject, string $title, ?User $user, ?array $payload = null): OneCAgentTask
    {
        $this->handler($type);
        // The same work is not queued twice; different reads without a subject (lists, recent orders, purchases)
        // are told apart by their title and may wait together.
        $active = OneCAgentTask::query()->where('type', $type)
            ->when($subject, fn ($q) => $q->whereMorphedTo('subject', $subject), fn ($q) => $q->whereNull('subject_id')->where('title', mb_substr($title, 0, 250)))
            ->whereIn('status', OneCAgentTask::ACTIVE)->exists();
        if ($active) {
            throw new DomainException('Задача уже в очереди агента 1С.');
        }

        return OneCAgentTask::query()->create([
            'type' => $type, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'title' => mb_substr($title, 0, 250), 'payload' => $payload, 'status' => OneCAgentTask::QUEUED, 'created_by' => $user?->id,
        ]);
    }

    /** @param list<string>|null $types only these types (an old agent knows prices only) */
    public function claimNext(?array $types = null): ?OneCAgentTask
    {
        $task = DB::transaction(function () use ($types): ?OneCAgentTask {
            $this->giveUpStale();
            $task = OneCAgentTask::query()
                ->when($types !== null, fn ($q) => $q->whereIn('type', $types))
                ->where(fn ($q) => $q->where('status', OneCAgentTask::QUEUED)
                    ->orWhere(fn ($q) => $q->where('status', OneCAgentTask::SENDING)->where('picked_at', '<', now()->subMinutes(self::STALE_MINUTES))))
                ->orderBy('id')->lockForUpdate()->first();
            if (! $task) {
                return null;
            }
            $task->forceFill(['status' => OneCAgentTask::SENDING, 'picked_at' => now(), 'attempts' => $task->attempts + 1])->save();
            $this->handler($task->type)->claimed($task);

            return $task;
        });

        return $task;
    }

    /** @param array{ok?: mixed, error?: ?string, data?: array<string, mixed>} $result */
    public function finish(OneCAgentTask $task, array $result): OneCAgentTask
    {
        return DB::transaction(function () use ($task, $result): OneCAgentTask {
            $task = OneCAgentTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($task->status !== OneCAgentTask::SENDING) {
                throw new DomainException('Задача не ждёт ответа агента.');
            }
            $ok = filter_var($result['ok'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $data = collect($result['data'] ?? [])->filter(fn ($v): bool => is_scalar($v))
                ->map(fn ($v): string => mb_substr((string) $v, 0, 200))->take(20)->all();
            $error = $ok ? null : mb_substr(trim((string) ($result['error'] ?? '')) ?: 'Агент не сообщил причину', 0, 2000);
            try {
                $summary = $this->handler($task->type)->finished($task, ['ok' => $ok, 'error' => $error, 'data' => $data]);
            } catch (DomainException $exception) {
                if (! data_get($task->payload, 'create_group')) {
                    throw $exception;
                }
                $ok = false;
                $error = mb_substr($exception->getMessage(), 0, 2000);
                $summary = null;
            }
            $task->forceFill([
                'status' => $ok ? OneCAgentTask::DONE : OneCAgentTask::FAILED, 'finished_at' => now(),
                'result' => $data ?: null, 'error' => $error, 'summary' => $summary ? mb_substr($summary, 0, 250) : null,
            ])->save();

            return $task;
        });
    }

    public function retry(OneCAgentTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = OneCAgentTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($task->status !== OneCAgentTask::FAILED) {
                throw new DomainException('Повторить можно только задачу с ошибкой.');
            }
            $task->forceFill(['status' => OneCAgentTask::QUEUED, 'picked_at' => null, 'finished_at' => null, 'error' => null, 'attempts' => 0])->save();
            $this->handler($task->type)->requeued($task);
        });
    }

    /** A run of the agent lasts at most 15 minutes (the scheduler stops it): a task taken longer ago is hung. */
    public const HUNG_MINUTES = 15;

    public static function cancellable(OneCAgentTask $task): bool
    {
        return in_array($task->status, [OneCAgentTask::QUEUED, OneCAgentTask::FAILED], true)
            || ($task->status === OneCAgentTask::SENDING && $task->picked_at?->lt(now()->subMinutes(self::HUNG_MINUTES)));
    }

    public function cancel(OneCAgentTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = OneCAgentTask::query()->lockForUpdate()->findOrFail($task->id);
            if (! self::cancellable($task)) {
                throw new DomainException('Отменить можно задачу в очереди, с ошибкой или зависшую (агент молчит больше '.self::HUNG_MINUTES.' минут).');
            }
            $task->forceFill(['status' => OneCAgentTask::CANCELLED, 'finished_at' => now()])->save();
            $this->handler($task->type)->cancelled($task);
        });
    }

    /** The active task of a subject, if any. */
    public function activeFor(Model $subject, ?string $type = null): ?OneCAgentTask
    {
        return OneCAgentTask::query()->whereMorphedTo('subject', $subject)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->whereIn('status', OneCAgentTask::ACTIVE)->latest('id')->first();
    }

    /** @return array{seen_at: ?Carbon, online: bool, version: ?string, outdated: bool, queued: int, failed: int, stuck: int} */
    public function health(): array
    {
        $token = OneCAgentToken::query()->whereNull('revoked_at')->whereNotNull('last_seen_at')->latest('last_seen_at')->first();
        $seen = $token?->last_seen_at;
        $latest = (string) config('onec.agent.version');

        return [
            'seen_at' => $seen,
            'online' => $seen !== null && $seen->gt(now()->subMinutes(self::OFFLINE_MINUTES)),
            'version' => $token?->agent_version,
            'outdated' => $token !== null && version_compare((string) ($token->agent_version ?: '1'), $latest, '<'),
            'queued' => OneCAgentTask::query()->whereIn('status', OneCAgentTask::ACTIVE)->count(),
            'failed' => OneCAgentTask::query()->where('status', OneCAgentTask::FAILED)->count(),
            'stuck' => OneCAgentTask::query()->where('status', OneCAgentTask::QUEUED)->where('created_at', '<', now()->subMinutes(self::OFFLINE_MINUTES))->count(),
        ];
    }

    /** A task taken MAX_ATTEMPTS times without an answer fails instead of going round forever. */
    private function giveUpStale(): void
    {
        $stale = OneCAgentTask::query()->where('status', OneCAgentTask::SENDING)
            ->where('picked_at', '<', now()->subMinutes(self::STALE_MINUTES))->where('attempts', '>=', self::MAX_ATTEMPTS)->get();
        foreach ($stale as $task) {
            $error = 'Агент брал задачу '.$task->attempts.' раз и не ответил. Проверьте agent.log на сервере 1С.';
            $this->handler($task->type)->finished($task, ['ok' => false, 'error' => $error, 'data' => []]);
            $task->forceFill(['status' => OneCAgentTask::FAILED, 'finished_at' => now(), 'error' => $error])->save();
        }
    }
}
