<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderDealControl
{
    public function save(Order $order, User $actor, array $data): Order
    {
        $advance = false;
        $saved = DB::transaction(function () use ($order, $actor, $data, &$advance): Order {
            $locked = Order::query()->whereDoesntHave('dealByInbox')->whereDoesntHave('legacyB2bOrder')
                ->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('update', $locked);
            if (! $locked->dealIsActive()) {
                throw ValidationException::withMessages(['stage' => 'Заказ уже завершён или закрыт.']);
            }
            if ((int) $locked->deal_version !== (int) $data['version'] || $locked->status !== $data['status']
                || (int) $locked->manager_id !== (int) $data['expected_manager_id']
                || (string) $locked->assigned_to !== (string) $data['expected_assigned_to']) {
                throw ValidationException::withMessages(['stage' => 'Заказ изменил другой сотрудник. Обновите страницу и повторите действие.']);
            }

            $stages = array_keys(Order::DEAL_STAGES);
            $stage = (string) ($data['stage'] ?? '');
            if (! isset(Order::DEAL_STAGES[$stage])) {
                throw ValidationException::withMessages(['stage' => 'Выберите этап сделки.']);
            }
            $actualIndex = array_search($locked->fulfillmentDealStage(), $stages, true);
            $targetIndex = array_search($stage, $stages, true);
            $nextFulfillmentStage = match ($locked->status) {
                'confirmed' => 'processing',
                'processing' => 'handover',
                'shipped' => 'completed',
                default => null,
            };
            $advance = $stage === $nextFulfillmentStage;
            if (! $advance && ($targetIndex < $actualIndex || ($targetIndex > 2 && $stage !== $locked->fulfillmentDealStage()))) {
                throw ValidationException::withMessages(['stage' => 'Этап должен соответствовать исполнению заказа. Сначала подтвердите наличие и предыдущий этап исполнения.']);
            }
            if ($advance && ($blocker = app(OrderWorkflow::class)->blocker($locked))) {
                throw ValidationException::withMessages(['stage' => $blocker]);
            }

            $beforeStage = $locked->effectiveDealStage();
            $comment = trim((string) ($data['comment'] ?? ''));
            if ($targetIndex < array_search($beforeStage, $stages, true) && $comment === '') {
                throw ValidationException::withMessages(['comment' => 'Укажите причину возврата на предыдущий этап.']);
            }
            $nextAction = trim((string) ($data['next_action'] ?? ''));
            $controlAt = filled($data['control_at'] ?? null) ? Carbon::parse($data['control_at']) : null;
            if ($stage !== 'completed' && ($nextAction === '' || ! $controlAt || $controlAt->isPast())) {
                throw ValidationException::withMessages(['control_at' => 'Укажите следующий шаг и будущий срок контроля.']);
            }
            if ($stage === 'completed') {
                $nextAction = '';
                $controlAt = null;
            }
            $manager = User::query()->whereKey($data['manager_id'])
                ->where('is_active', true)->whereIn('role', ['admin', 'manager', 'sales_manager'])->first();
            if (! $manager) {
                throw ValidationException::withMessages(['manager_id' => 'Выберите действующего менеджера.']);
            }
            $onHold = $stage !== 'completed' && (bool) ($data['on_hold'] ?? false);
            // Fulfillment stages follow the actual order status, including later corrections.
            $storedStage = $targetIndex > 2 ? ($locked->deal_stage ?: 'received') : $stage;
            $previous = collect([
                Order::DEAL_STAGES[$beforeStage], $locked->deal_next_action,
                $locked->deal_control_at?->timezone('Europe/Minsk')->format('d.m.Y H:i'),
                $locked->deal_on_hold ? 'Отложен' : null,
                $locked->manager?->name ?: $locked->assigned_to,
            ])->filter()->implode(' · ');

            $locked->forceFill([
                'deal_stage' => $storedStage,
                'deal_next_action' => $nextAction ?: null,
                'deal_control_at' => $controlAt,
                'deal_on_hold' => $onHold,
                'deal_version' => (int) $locked->deal_version + 1,
                'deal_stage_changed_at' => $beforeStage !== $storedStage ? now() : $locked->deal_stage_changed_at,
                'deal_control_notified_at' => null,
                'manager_id' => $manager->id,
                'assigned_to' => '@'.($manager->telegram_username ?: $manager->name),
            ])->save();
            $locked->statusHistory()->create([
                'user_id' => $actor->id,
                'status_from' => $locked->status,
                'status_to' => $locked->status,
                'deal_stage_from' => ! $advance && $beforeStage !== $stage ? $beforeStage : null,
                'deal_stage_to' => ! $advance && $beforeStage !== $stage ? $stage : null,
                'comment' => 'Контроль сделки. Было: '.$previous
                    .($advance ? "\nЗапрошен переход: " : "\nСтало: ").Order::DEAL_STAGES[$stage].' · '.$nextAction
                    .' · '.($controlAt?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? 'Контроль завершён').' · '.$manager->name
                    .($onHold ? ' · Отложен до срока контроля' : '')
                    .($comment !== '' ? "\n".$comment : ''),
            ]);

            return $locked;
        });

        if ($advance) {
            try {
                app(OrderWorkflow::class)->advance($saved, $actor);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    'stage' => 'Следующий шаг сохранён. Переход этапа не выполнен: '.$exception->getMessage().' Обновите заказ.',
                ]);
            }
        }

        return $saved->fresh();
    }
}
