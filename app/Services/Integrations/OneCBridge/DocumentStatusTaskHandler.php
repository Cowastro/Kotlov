<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Models\OneCAgentTask;
use App\Models\OneCPriceBatch;
use App\Models\User;
use DomainException;

/**
 * Read-only: asks 1C whether the documents the agent created are posted or marked for deletion, so the site
 * shows what the accountant did. Uses the read answers of ODataReadTaskHandler.
 */
class DocumentStatusTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'doc_status';

    /** Keys per request: the OData filter goes in the URL, IIS takes 2 KB of query by default. */
    private const CHUNK = 15;

    /** Documents older than this are not asked about any more. */
    private const DAYS = 60;

    public function __construct(
        private readonly OneCAgentTasks $tasks,
        private readonly ODataReadTaskHandler $reads,
    ) {}

    public function label(): string
    {
        return 'Статусы документов';
    }

    /** Null when nothing waits for the accountant or a check is already queued. */
    public function enqueueCheck(?User $user): ?OneCAgentTask
    {
        $keys = OneCPriceBatch::query()->where('status', OneCPriceBatch::CREATED)->whereNotNull('onec_document_key')
            ->where(fn ($q) => $q->whereNull('onec_posted')->orWhere('onec_posted', false))
            ->where('onec_deleted', false)->where('finished_at', '>', now()->subDays(self::DAYS))
            ->orderBy('id')->pluck('onec_document_key')->unique()->values();
        if ($keys->isEmpty()) {
            return null;
        }
        $queries = [];
        foreach ($keys->chunk(self::CHUNK)->values() as $i => $chunk) {
            $filter = $chunk->map(fn (string $key): string => "Ref_Key eq guid'".$key."'")->implode(' or ');
            $queries['prices_'.($i + 1)] = ODataReadTaskHandler::path('Document_УстановкаЦенНоменклатуры',
                ['$filter' => $filter, '$select' => 'Ref_Key,Number,Posted,DeletionMark']);
        }
        try {
            return $this->tasks->enqueue(self::TYPE, null, 'Проверить документы в 1С: '.$keys->count(), $user, ['queries' => $queries]);
        } catch (DomainException) {
            return null;
        }
    }

    public function lines(OneCAgentTask $task): array
    {
        return $this->reads->lines($task);
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        if (! $result['ok']) {
            return null;
        }
        $task->refresh();
        $checked = 0;
        $posted = 0;
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            $part = ODataReadTaskHandler::part($task, $name);
            foreach ($part['code'] === 200 ? (array) data_get($part['json'], 'value', []) : [] as $row) {
                $key = strtolower((string) ($row['Ref_Key'] ?? ''));
                $batch = $key !== '' ? OneCPriceBatch::query()->where('onec_document_key', $key)->first() : null;
                if (! $batch) {
                    continue;
                }
                $checked++;
                $posted += ($row['Posted'] ?? false) ? 1 : 0;
                $batch->forceFill(['onec_posted' => (bool) ($row['Posted'] ?? false), 'onec_deleted' => (bool) ($row['DeletionMark'] ?? false),
                    'onec_checked_at' => now(), 'onec_document_number' => ($row['Number'] ?? '') ?: $batch->onec_document_number])->save();
                OneCAgentTask::query()->where('type', PriceBatchTaskHandler::TYPE)->where('subject_type', $batch->getMorphClass())
                    ->where('subject_id', $batch->id)->where('status', OneCAgentTask::DONE)
                    ->update(['summary' => PriceBatchTaskHandler::documentSummary($batch)]);
            }
        }

        return 'Проверено '.$checked.', проведено '.$posted;
    }

    public function requeued(OneCAgentTask $task): void
    {
        $this->reads->requeued($task);
    }

    public function cancelled(OneCAgentTask $task): void {}

    public function url(OneCAgentTask $task): ?string
    {
        return OneCAgentTaskResource::getUrl('view', ['record' => $task]);
    }
}
