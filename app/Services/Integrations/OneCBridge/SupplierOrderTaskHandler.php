<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OneCAgentTask;
use App\Models\OneCSupplierOrder;

/** «Заказ поставщику» the agent creates UNPOSTED from what OneCSupplierOrderExport prepared. */
class SupplierOrderTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'onec_supplier_order';

    public function label(): string
    {
        return 'Заказ поставщику';
    }

    public function lines(OneCAgentTask $task): array
    {
        return ['entity|'.OneCSupplierOrderExport::ENTITY,
            'body|'.base64_encode(json_encode(data_get($task->payload, 'body'), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION))];
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        $record = $task->subject;
        if (! $record instanceof OneCSupplierOrder) {
            return null;
        }
        if (! $result['ok']) {
            $record->forceFill(['status' => OneCSupplierOrder::FAILED])->save();

            return null;
        }
        $number = (string) ($result['data']['document_number'] ?? '');
        $record->forceFill(['status' => OneCSupplierOrder::CREATED, 'onec_order_guid' => strtolower((string) ($result['data']['document_key'] ?? '')) ?: null,
            'onec_order_number' => $number ?: null, 'onec_posted' => false, 'onec_deleted' => false,
            'onec_doc_status' => data_get($task->payload, 'body.Статус')])->save();
        $record->order?->managerComments()->create(['user_id' => $task->created_by, 'author_name' => 'Мост 1С',
            'body' => 'Создан заказ поставщику в 1С: '.($number ?: '—').' — '.$record->supplier?->name.' (не проведён).']);

        return self::summary($record);
    }

    public static function summary(OneCSupplierOrder $record): string
    {
        return 'Заказ поставщику '.($record->onec_order_number ?: 'создан').', '
            .($record->onec_deleted ? 'помечен на удаление' : ($record->onec_posted ? 'проведён' : 'не проведён'));
    }

    public function requeued(OneCAgentTask $task): void
    {
        if ($task->subject instanceof OneCSupplierOrder) {
            $task->subject->forceFill(['status' => OneCSupplierOrder::QUEUED])->save();
        }
    }

    public function cancelled(OneCAgentTask $task): void
    {
        if ($task->subject instanceof OneCSupplierOrder) {
            $task->subject->forceFill(['status' => OneCSupplierOrder::FAILED])->save();
        }
    }

    public function url(OneCAgentTask $task): ?string
    {
        $orderId = $task->subject instanceof OneCSupplierOrder ? $task->subject->order_id : null;

        return $orderId ? OrderResource::getUrl('view', ['record' => $orderId]) : null;
    }
}
