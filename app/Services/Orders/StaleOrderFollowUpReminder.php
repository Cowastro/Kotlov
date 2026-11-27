<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\Uuid;

class StaleOrderFollowUpReminder
{
    public const TIMEZONE = 'Europe/Minsk';

    private function targets(): Builder
    {
        return User::query()->where('is_active', true)->where('receives_stale_order_reminders', true)
            ->whereIn('role', ['admin', 'manager', 'sales_manager']);
    }

    /** @return array{orders: int, recipients: int} */
    public function preview(): array
    {
        return [
            'orders' => OrderResource::getEloquentQuery()->staleFollowUp()->count(),
            'recipients' => $this->targets()->count(),
        ];
    }

    public function notify(): int
    {
        $orders = OrderResource::getEloquentQuery()->staleFollowUp();
        $total = (clone $orders)->count();
        if ($total === 0) {
            return 0;
        }

        $unassigned = $orders->withoutResponsible()->count();
        $period = now(self::TIMEZONE)->format('Y-m-d-H');
        $count = 0;
        foreach ($this->targets()->get() as $target) {
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'kotlov:stale-orders:'.$target->id.':'.$period)->toString();
            $data = Notification::make()
                ->title('Зависшие заказы — '.$total)
                ->body('Без смены статуса 48 часов и без запланированного контроля. Без ответственного: '.$unassigned
                    .'. Уточните состояние заказа и зафиксируйте результат или следующий шаг со сроком.')
                ->warning()->icon('heroicon-o-clock')
                ->actions([
                    Action::make('openStaleOrders')->label('Разобрать заказы')
                        ->url(OrderResource::getUrl('index', ['tab' => 'stale_follow_up']))
                        ->markAsRead(),
                ])->getDatabaseMessage();
            $reminder = $target->notifications()->firstOrCreate(['id' => $id], [
                'type' => DatabaseNotification::class,
                'data' => $data,
            ]);
            $count += (int) $reminder->wasRecentlyCreated;
        }

        return $count;
    }
}
