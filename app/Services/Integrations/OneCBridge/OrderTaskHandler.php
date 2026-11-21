<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OneCAgentTask;
use App\Models\Order;

/**
 * «Заказ клиента» in 1C: the agent first looks for an order with the same «номер по данным клиента» (an order never
 * goes twice), otherwise creates it UNPOSTED from the document the site prepared.
 */
class OrderTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'onec_order';

    public function label(): string
    {
        return 'Заказ клиента';
    }

    public function lines(OneCAgentTask $task): array
    {
        $lines = ['order|'.$task->subject_id];
        if ($find = data_get($task->payload, 'find')) {
            $lines[] = 'find|'.$find;
        }
        $lines[] = 'body|'.base64_encode(json_encode(data_get($task->payload, 'body'), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $lines;
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        $order = $task->subject;
        if (! $result['ok'] || ! $order instanceof Order) {
            return null;
        }
        $number = (string) ($result['data']['document_number'] ?? '');
        $key = strtolower((string) ($result['data']['document_key'] ?? ''));
        $existed = ($result['data']['existed'] ?? '') === '1';
        $order->forceFill([
            'onec_order_guid' => $key ?: null, 'onec_order_number' => $number ?: null, 'onec_exported_at' => now(),
            'onec_profile_id' => data_get($task->payload, 'profile_id'),
            'onec_doc_status' => data_get($task->payload, 'body.Статус'), 'onec_posted' => false, 'onec_deleted' => false,
        ])->save();
        $order->managerComments()->create([
            'user_id' => $task->created_by,
            'author_name' => 'Мост 1С',
            'body' => ($existed ? 'Заказ уже был в 1С: ' : 'Создан заказ клиента в 1С: ').($number ?: $key).' (не проведён).',
        ]);

        return self::summary($order, $existed);
    }

    public static function summary(Order $order, bool $existed = false): string
    {
        $state = match (true) {
            (bool) $order->onec_deleted => 'помечен на удаление',
            (bool) $order->onec_posted => 'проведён',
            default => 'не проведён',
        };
        $status = OneCOrderProfiles::STATUSES[$order->onec_doc_status] ?? $order->onec_doc_status;

        return 'Заказ 1С '.($order->onec_order_number ?: 'создан').($existed ? ' (уже был в 1С)' : '').', '.$state.($status ? ', '.mb_strtolower($status) : '');
    }

    public function requeued(OneCAgentTask $task): void {}

    public function cancelled(OneCAgentTask $task): void {}

    public function url(OneCAgentTask $task): ?string
    {
        return $task->subject_id ? OrderResource::getUrl('view', ['record' => $task->subject_id]) : null;
    }
}
