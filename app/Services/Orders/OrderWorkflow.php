<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCOrderStages;
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
            // Availability is confirmed line by line in the goods; this step confirms the whole order to the customer.
            'label' => 'Подтвердить заказ клиенту',
            'icon' => 'heroicon-o-check-badge',
            'color' => 'success',
            'heading' => 'Подтвердить заказ клиенту?',
            'description' => 'План исполнения проверен. Заказ станет доступен в кабинете, клиент получит письмо с состоянием каждой позиции, включая ожидаемые товары.',
            'submit' => 'Подтвердить заказ',
        ],
        'confirmed' => [
            'next' => 'processing',
            'label' => 'Передать в исполнение',
            'icon' => 'heroicon-o-arrow-path',
            'color' => 'primary',
            'heading' => 'Передать заказ в исполнение?',
            'description' => 'Заказ перейдёт в исполнение. Следующим этапом станет передача в доставку.',
            'submit' => 'Передать в исполнение',
        ],
        'processing' => [
            'next' => 'shipped',
            'label' => 'Подтвердить отгрузку',
            'icon' => 'heroicon-o-truck',
            'color' => 'primary',
            'heading' => 'Заказ фактически отгружен?',
            'description' => 'Подтвердите фактическую передачу собранного заказа перевозчику или нашему водителю. При самовывозе — передачу клиенту.',
            'submit' => 'Подтвердить отгрузку',
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
        if (! self::canAdvanceManually($order) || $order->executionTransferPending()) {
            return null;
        }

        if (self::canConfirmOwnDelivery($order)) {
            return array_replace(self::STEPS['shipped'], [
                'label' => 'Подтвердить получение',
                'heading' => 'Покупатель получил заказ?',
                'description' => 'Подтвердите, что наш водитель вручил заказ покупателю. Заказ перейдёт в этап «Получен».',
                'submit' => 'Подтвердить получение',
            ]);
        }

        return self::STEPS[$order->status] ?? null;
    }

    /**
     * The 1C workflow drives dispatch. Receipt for our own transport is confirmed separately by a manager.
     */
    public static function managedBy1c(Order $order): bool
    {
        return filled($order->onec_order_guid) && ! $order->onec_deleted && in_array($order->status, ['processing', 'shipped'], true);
    }

    public static function canAdvanceManually(Order $order): bool
    {
        return ! self::managedBy1c($order) || self::canConfirmOwnDelivery($order);
    }

    public static function canConfirmOwnDelivery(Order $order): bool
    {
        return $order->status === 'shipped' && $order->onec_posted && self::managedBy1c($order)
            && in_array($order->delivery_type, ['courier', 'own_transport'], true)
            && blank($order->carrier_provider);
    }

    public function blocker(Order $order): ?string
    {
        if ($order->status === 'processing' && blank($order->onec_order_guid) && ! $order->is_internal
            && \App\Models\IntegrationSource::where('code', 'onec')->where('is_active', true)->exists()) {
            return app(OrderDeliveryPreparation::class)->blocker($order)
                ?: 'Доставка согласована. Сначала оформите заказ в 1С; фактическая отгрузка будет отдельным этапом.';
        }
        if ($order->executionTransferPending()) {
            return 'Передача в исполнение уже запущена. Дождитесь результата; повторная отправка не требуется.';
        }

        if (! self::canAdvanceManually($order)) {
            return 'Заказ в 1С ('.$order->onec_order_number.'): этап обновляется по данным 1С и перевозчика.';
        }

        if ($order->status === 'processing' && $order->hasPendingSupply()) {
            return 'В заказе есть ожидаемые или неуточнённые позиции. Сначала подтвердите готовность всего заказа к отгрузке.';
        }

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

        $result = match ($expectedStatus) {
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

        app(OrderStatusCustomerNotifier::class)->notify($order->fresh(['user']));

        // Already in 1C and posted there: the next stages follow 1C (a closed 1C order waits for the next check,
        // which also looks at its lines).
        $moved = app(OneCOrderStages::class)->apply($order, upTo: 'processing');
        if ($moved) {
            $result['body'] .= ' Заказ уже проведён в 1С — этап «'.Order::STATUSES[$moved].'» поставлен по 1С.';
        }

        return $result;
    }

    /** @return array{title:string,body:string} */
    private function confirmAvailability(Order $order, ?User $manager): array
    {
        $result = app(OrderAvailabilityConfirmation::class)->confirmAndNotify($order, $manager);

        return [
            'title' => 'Заказ принят в обработку',
            'body' => $result['notified']
                ? 'Клиенту отправлено уведомление со ссылкой на заказ. Следующий этап — передача в исполнение.'
                : 'Заказ доступен в личном кабинете. Следующий этап — передача в исполнение.',
        ];
    }

    /** @return array{title:string,body:string} */
    private function startProcessing(Order $order, ?User $manager): array
    {
        return $this->transition(
            $order,
            $manager,
            'confirmed',
            'processing',
            'Заказ передан в исполнение. Далее согласуйте доставку; оформление в 1С выполняется отдельно.',
            'Заказ передан в исполнение',
        );
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

            if ($nextStatus === 'delivered' && ! self::canAdvanceManually($locked)) {
                throw new DomainException('Условия доставки изменились. Обновите заказ и проверьте способ получения.');
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
