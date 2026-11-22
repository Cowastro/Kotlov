<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Models\OneCAgentTask;
use App\Models\OneCPriceBatch;
use App\Models\Order;
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
        // Orders until 1C closes them: the manager moves them through «К обеспечению», «К выполнению»…
        $orders = Order::query()->whereNotNull('onec_order_guid')->where(fn ($q) => $q->whereNotNull('onec_profile_id')->orWhereNotNull('onec_checked_at'))->where('onec_deleted', false)
            ->where(fn ($q) => $q->whereNull('onec_doc_status')->orWhere('onec_doc_status', '!=', 'Закрыт'))
            ->where('onec_exported_at', '>', now()->subDays(self::DAYS))->orderBy('id')->pluck('onec_order_guid')->unique()->values();
        if ($keys->isEmpty() && $orders->isEmpty()) {
            return null;
        }
        $queries = [];
        $filter = fn ($chunk): string => $chunk->map(fn (string $key): string => "Ref_Key eq guid'".$key."'")->implode(' or ');
        foreach ($keys->chunk(self::CHUNK)->values() as $i => $chunk) {
            $queries['prices_'.($i + 1)] = ODataReadTaskHandler::path('Document_УстановкаЦенНоменклатуры',
                ['$filter' => $filter($chunk), '$select' => 'Ref_Key,Number,Posted,DeletionMark']);
        }
        foreach ($orders->chunk(self::CHUNK)->values() as $i => $chunk) {
            $queries['orders_'.($i + 1)] = ODataReadTaskHandler::path('Document_ЗаказКлиента',
                ['$filter' => $filter($chunk)]); // whole orders: the lines tell a closed order from a cancelled one
        }
        try {
            return $this->tasks->enqueue(self::TYPE, null, 'Проверить документы в 1С: '.($keys->count() + $orders->count()), $user, ['queries' => $queries]);
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
        $moved = 0;
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            $part = ODataReadTaskHandler::part($task, $name);
            foreach ($part['code'] === 200 ? (array) data_get($part['json'], 'value', []) : [] as $row) {
                $key = strtolower((string) ($row['Ref_Key'] ?? ''));
                if (str_starts_with($name, 'orders_')) {
                    $order = $key !== '' ? Order::query()->where('onec_order_guid', $key)->first() : null;
                    if ($order) {
                        $checked++;
                        $posted += ($row['Posted'] ?? false) ? 1 : 0;
                        $order->forceFill(['onec_posted' => (bool) ($row['Posted'] ?? false), 'onec_deleted' => (bool) ($row['DeletionMark'] ?? false),
                            'onec_doc_status' => ($row['Статус'] ?? '') ?: $order->onec_doc_status, 'onec_checked_at' => now(),
                            'onec_order_number' => ($row['Number'] ?? '') ?: $order->onec_order_number])->save();
                        OneCAgentTask::query()->where('type', OrderTaskHandler::TYPE)->where('subject_type', $order->getMorphClass())
                            ->where('subject_id', $order->id)->where('status', OneCAgentTask::DONE)
                            ->update(['summary' => OrderTaskHandler::summary($order)]);
                        $lines = collect((array) ($row['Товары'] ?? []));
                        $moved += app(OneCOrderStages::class)->apply($order, $lines->isNotEmpty() && $lines->every(fn ($l): bool => (bool) ($l['Отменено'] ?? false))) ? 1 : 0;
                    }

                    continue;
                }
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

        return 'Проверено '.$checked.', проведено '.$posted.($moved ? ', этапов заказов обновлено: '.$moved : '');
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
