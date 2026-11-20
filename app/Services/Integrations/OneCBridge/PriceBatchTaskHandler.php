<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\OneCAgentTask;
use App\Models\OneCPriceBatch;
use App\Services\Integrations\OneCPriceBatches;

/** «Цены для 1С»: the agent creates an unposted «Установка цен номенклатуры» from an approved batch. */
class PriceBatchTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'price_batch';

    public function __construct(private readonly OneCPriceBatches $batches) {}

    public function label(): string
    {
        return 'Установка цен';
    }

    public function lines(OneCAgentTask $task): array
    {
        return self::batchLines($this->batches->payload($this->batch($task)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function batchLines(array $payload): array
    {
        $clean = fn ($value): string => str_replace(['|', "\r", "\n"], ' ', (string) $value);
        $lines = ['batch|'.$payload['batch_id'], 'comment|'.$clean($payload['comment']), 'currency|'.$payload['currency_key']];
        foreach ($payload['price_type_keys'] as $key) {
            $lines[] = 'type|'.$key;
        }
        foreach ($payload['rows'] as $row) {
            $lines[] = implode('|', ['row', $row['item_key'], $row['characteristic_key'], $row['price_type_key'],
                number_format((float) $row['price'], 2, '.', ''), $row['manual'] ? '1' : '0', $clean($row['formula'])]);
        }

        return $lines;
    }

    public function claimed(OneCAgentTask $task): void
    {
        $this->batch($task)->forceFill(['status' => OneCPriceBatch::SENDING, 'picked_at' => now()])->save();
    }

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        $batch = $this->batch($task);
        if ($batch->status === OneCPriceBatch::SENDING) {
            $batch = $this->batches->finish($batch, [
                'ok' => $result['ok'], 'error' => $result['error'],
                'document_number' => $result['data']['document_number'] ?? null,
                'document_key' => $result['data']['document_key'] ?? null,
            ]);
        }

        return $result['ok'] ? 'Документ '.($batch->onec_document_number ?: 'создан').', не проведён' : null;
    }

    public function requeued(OneCAgentTask $task): void
    {
        $this->batch($task)->forceFill(['status' => OneCPriceBatch::APPROVED, 'picked_at' => null, 'finished_at' => null, 'error' => null])->save();
    }

    public function cancelled(OneCAgentTask $task): void
    {
        $batch = $this->batch($task);
        if ($batch->status !== OneCPriceBatch::CREATED) {
            $batch->forceFill(['status' => OneCPriceBatch::DRAFT, 'picked_at' => null, 'finished_at' => null])->save();
        }
    }

    public function url(OneCAgentTask $task): ?string
    {
        return $task->subject_id ? OneCPriceBatchResource::getUrl('view', ['record' => $task->subject_id]) : null;
    }

    private function batch(OneCAgentTask $task): OneCPriceBatch
    {
        return OneCPriceBatch::query()->findOrFail($task->subject_id);
    }
}
