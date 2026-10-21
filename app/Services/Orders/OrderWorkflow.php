<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Integrations\OneCOrderQueue;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderWorkflow
{
    /** @var array<string, array{next:string,label:string,icon:string,color:string,heading:string,description:string,submit:string}> */
    private const STEPS = [
        'new' => [
            'next' => 'checking',
            'label' => 'Проверить наличие',
            'icon' => 'heroicon-o-magnifying-glass',
            'color' => 'warning',
            'heading' => 'Начать проверку наличия?',
            'description' => 'Заказ перейдёт на этап проверки наличия. Следующим действием будет подтверждение наличия всех позиций.',
            'submit' => 'Начать проверку',
        ],
        'checking' => [
            'next' => 'confirmed',
            'label' => 'Подтвердить наличие',
            'icon' => 'heroicon-o-check-badge',
            'color' => 'success',
            'heading' => 'Подтвердить наличие клиенту?',
            'description' => 'Заказ станет доступен клиенту в личном кабинете. Клиенту будет отправлено уведомление со ссылкой на заказ.',
            'submit' => 'Подтвердить наличие',
        ],
        'confirmed' => [
            'next' => 'processing',
            'label' => 'Передать в исполнение',
            'icon' => 'heroicon-o-arrow-path',
            'color' => 'primary',
            'heading' => 'Передать заказ в исполнение?',
            'description' => 'Заказ будет поставлен в очередь центральной 1С. Следующим этапом станет передача в доставку.',
            'submit' => 'Передать в исполнение',
        ],
        'processing' => [
            'next' => 'shipped',
            'label' => 'Передать в доставку',
            'icon' => 'heroicon-o-truck',
            'color' => 'primary',
            'heading' => 'Передать заказ в доставку?',
            'description' => 'Подтвердите, что заказ собран и передан в доставку или готов к выдаче клиенту.',
            'submit' => 'Передать в доставку',
        ],
        'shipped' => [
            'next' => 'delivered',
            'label' => 'Отметить доставленным',
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'heading' => 'Заказ получен клиентом?',
            'description' => 'Это завершит последовательность исполнения заказа. Действие сохранится в истории.',
            'submit' => 'Заказ доставлен',
        ],
    ];

    /** @return array{next:string,label:string,icon:string,color:string,heading:string,description:string,submit:string}|null */
    public function step(Order $order): ?array
    {
        return self::STEPS[$order->status] ?? null;
    }

    public function blocker(Order $order): ?string
    {
        if ($order->status === 'checking' && ! $order->hasConfirmedFulfillmentForAllItems()) {
            return 'Сначала подтвердите наличие и способ исполнения каждой позиции в карточке заказа.';
        }

        if ($order->status === 'confirmed' && $order->items()->whereNull('fulfillment_confirmed_at')->exists()) {
            return 'Сначала подтвердите исполнение всех позиций заказа.';
        }

        return null;
    }

    /** @return array{title:string,body:string} */
    public function advance(Order $order, ?User $manager): array
    {
        $expectedStatus = $order->status;
        $blocker = $this->blocker($order);

        if ($blocker !== null) {
            throw new DomainException($blocker);
        }

        return match ($expectedStatus) {
            'new' => $this->transition(
                $order,
                $manager,
                'new',
                'checking',
                'Заказ принят. Начата проверка наличия.',
                'Проверка наличия начата',
            ),
            'checking' => $this->confirmAvailability($order, $manager),
            'confirmed' => $this->startProcessing($order, $manager),
            'processing' => $this->transition(
                $order,
                $manager,
                'processing',
                'shipped',
                'Заказ передан в доставку.',
                'Заказ передан в доставку',
            ),
            'shipped' => $this->transition(
                $order,
                $manager,
                'shipped',
                'delivered',
                'Клиент получил заказ.',
                'Заказ отмечен доставленным',
            ),
            default => throw new DomainException('Для текущего статуса нет следующего этапа.'),
        };
    }

    /** @return array{title:string,body:string} */
    private function confirmAvailability(Order $order, ?User $manager): array
    {
        $result = app(OrderAvailabilityConfirmation::class)->confirmAndNotify($order, $manager);

        return [
            'title' => 'Наличие подтверждено',
            'body' => $result['notified']
                ? 'Клиенту отправлено уведомление со ссылкой на заказ. Следующий этап — передача в исполнение.'
                : 'Заказ доступен в личном кабинете. Следующий этап — передача в исполнение.',
        ];
    }

    /** @return array{title:string,body:string} */
    private function startProcessing(Order $order, ?User $manager): array
    {
        app(OneCOrderQueue::class)->queue($order, $manager, $this->managerAttributes($order, $manager));

        return [
            'title' => 'Заказ передан в исполнение',
            'body' => 'Заказ поставлен в очередь центральной 1С. Следующий этап — передача в доставку.',
        ];
    }

    /** @return array{title:string,body:string} */
    private function transition(
        Order $order,
        ?User $manager,
        string $expectedStatus,
        string $nextStatus,
        string $comment,
        string $title,
    ): array {
        $updatedOrder = DB::transaction(function () use ($order, $manager, $expectedStatus, $nextStatus, $comment): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== $expectedStatus) {
                throw new DomainException('Статус заказа уже изменился. Обновите страницу и повторите действие.');
            }

            $locked->transitionTo($nextStatus, $comment, $this->managerAttributes($locked, $manager));

            return $locked;
        });

        return [
            'title' => $title,
            'body' => $updatedOrder->workflowNextStep(),
        ];
    }

    /** @return array<string, int|string|null> */
    private function managerAttributes(Order $order, ?User $manager): array
    {
        if (! $manager) {
            return [];
        }

        return [
            'manager_id' => $order->manager_id ?: $manager->id,
            'assigned_to' => $order->assigned_to ?: '@'.($manager->telegram_username ?: $manager->name),
        ];
    }
}
