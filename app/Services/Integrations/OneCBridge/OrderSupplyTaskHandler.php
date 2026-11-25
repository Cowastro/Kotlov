<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\OneCAgentTask;
use App\Models\Order;
use DomainException;

/** The existing agent checks live 1C stock and updates only supply actions of linked open orders. */
class OrderSupplyTaskHandler implements OneCAgentTaskHandler
{
    public const TYPE = 'onec_order_supply';

    public function label(): string
    {
        return 'Обеспечение и отгрузка заказа';
    }

    public function enqueueOpen(int $limit = 20, bool $preview = false): int
    {
        if (! $preview && ! config('onec.supply_sync_enabled')) {
            return 0;
        }
        // An older agent must keep processing its other work, without receiving unknown task types.
        $health = app(OneCAgentTasks::class)->health();
        if (! $health['online'] || version_compare((string) $health['version'], '12', '<')) {
            return 0;
        }
        $count = 0;
        $orders = Order::query()->whereNotNull('onec_order_guid')->whereNotNull('onec_order_number')
            ->where('onec_posted', true)->where('onec_deleted', false)->whereNull('archived_at')
            ->whereIn('status', ['confirmed', 'processing', 'shipped'])
            ->where(fn ($q) => $q->whereNull('onec_doc_status')->orWhere('onec_doc_status', '!=', 'Закрыт'))
            ->where(fn ($q) => $q->whereNull('onec_state')->orWhere('onec_state', '!=', 'Закрыт'))
            ->where('onec_exported_at', '>', now()->subDays(60))->orderBy('onec_exported_at')->orderBy('id')->get();
        foreach ($orders as $order) {
            if ($count >= max(1, min(100, $limit))) {
                break;
            }
            $last = OneCAgentTask::query()->where('type', self::TYPE)->whereMorphedTo('subject', $order)->latest('id')->first();
            // A write failure needs review, not another automatic attempt every half hour.
            if ($last && ($last->isActive() || $last->status === OneCAgentTask::FAILED
                || (! $preview && ! data_get($last->payload, 'preview') && $last->created_at->gt(now()->subMinutes(25))))) {
                continue;
            }
            try {
                app(OneCAgentTasks::class)->enqueue(self::TYPE, $order,
                    ($preview ? 'Проверка без записи: ' : 'Обеспечение → отгрузка: ').$order->number, null,
                    ['key' => $order->onec_order_guid, 'number' => $order->onec_order_number, 'preview' => $preview]);
                $count++;
            } catch (DomainException) {
                // Another scheduler run may have queued this order while we inspected it.
            }
        }

        return $count;
    }

    public function lines(OneCAgentTask $task): array
    {
        return ['key|'.data_get($task->payload, 'key'), 'number|'.data_get($task->payload, 'number'),
            'preview|'.(data_get($task->payload, 'preview') ? '1' : '0')];
    }

    public function claimed(OneCAgentTask $task): void {}

    public function finished(OneCAgentTask $task, array $result): ?string
    {
        if (! $result['ok']) {
            if ($task->subject instanceof Order && ! $task->subject->managerComments()->where('body', 'like', '%Задача обеспечения №'.$task->id.':%')->exists()) {
                $task->subject->managerComments()->create(['author_name' => 'Мост 1С',
                    'body' => 'Задача обеспечения №'.$task->id.': обновление действий строк не подтверждено. Проверьте ошибку задачи и документ в 1С. Автоматические повторы остановлены.']);
            }
            return null;
        }
        if (strtolower((string) data_get($result, 'data.document_key')) !== strtolower((string) data_get($task->payload, 'key'))) {
            throw new DomainException('Агент вернул другой заказ 1С: требуется проверка.');
        }
        $changed = (int) data_get($result, 'data.changed', 0);
        if (data_get($task->payload, 'preview')) {
            return 'Без записи в 1С: строк для изменения '.$changed
                .'; к отгрузке '.(int) data_get($result, 'data.ready_lines', 0)
                .', к обеспечению '.(int) data_get($result, 'data.waiting_lines', 0);
        }
        if ($changed > 0 && $task->subject instanceof Order) {
            $task->subject->managerComments()->create(['author_name' => 'Мост 1С',
                'body' => 'Обновлены действия '.$changed.' строк заказа 1С: свободный товар — «Отгрузить», недостающий — «К обеспечению». Это подготовка к отгрузке, не подтверждение отправки.']);
            try {
                app(DocumentStatusTaskHandler::class)->enqueueCheck(null);
            } catch (DomainException) {
                // The already queued status read will pick up the result.
            }
        }

        return $changed > 0 ? 'Действия строк обновлены в 1С: '.$changed : 'Изменений обеспечения нет';
    }

    public function requeued(OneCAgentTask $task): void {}

    public function cancelled(OneCAgentTask $task): void {}

    public function url(OneCAgentTask $task): ?string
    {
        return $task->subject_id ? OrderResource::getUrl('view', ['record' => $task->subject_id]) : null;
    }
}
