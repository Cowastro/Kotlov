<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\OneCAgentTask;
use App\Models\Product;

/**
 * «Номенклатура» in 1C made from a site card (OneCItemCreator): the agent looks for the same article once more and
 * creates the item only when 1C still has none, then the card is linked with it.
 */
class ItemTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'onec_item';

    public function label(): string
    {
        return 'Товар в 1С';
    }

    public function lines(OneCAgentTask $task): array
    {
        $lines = ['entity|'.OneCItemCreator::ENTITY];
        if ($find = data_get($task->payload, 'find')) {
            $lines[] = 'find|'.$find;
        }
        $lines[] = 'body|'.base64_encode(json_encode(data_get($task->payload, 'body'), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $lines;
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        if (data_get($task->payload, 'create_group')) {
            return app(OneCGroupCreator::class)->finished($task, $result);
        }
        $product = $task->subject;
        if (! $result['ok'] || ! $product instanceof Product) {
            return null;
        }
        $key = (string) ($result['data']['document_key'] ?? '');
        if (! preg_match('/^[0-9a-f-]{36}$/i', $key)) {
            return 'Агент не вернул ключ товара 1С';
        }
        $code = (string) ($result['data']['document_code'] ?? $result['data']['document_number'] ?? '');
        $existed = ($result['data']['existed'] ?? '') === '1';
        $summary = app(OneCItemCreator::class)->link($product, $key, $code, (string) data_get($task->payload, 'body.Description'), ! $existed,
            $existed ? null : data_get($task->payload, 'body.Parent_Key'), data_get($task->payload, 'body.Артикул'));

        return 'Товар 1С '.($code ?: $key).($existed ? ' уже был в 1С: ' : ' ').$summary;
    }

    public function requeued(OneCAgentTask $task): void {}

    public function cancelled(OneCAgentTask $task): void {}

    public function url(OneCAgentTask $task): ?string
    {
        if (data_get($task->payload, 'create_group')) {
            return OneCItemResource::getUrl('index');
        }

        return $task->subject_id ? ProductResource::getUrl('edit', ['record' => $task->subject_id]) : null;
    }
}
