<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class StaleOrderFollowUpReminder
{
    public const TIMEZONE = 'Europe/Minsk';

    /** @return Collection<int, Collection<int, Order>> */
    private function ordersByManager(): Collection
    {
        return OrderResource::getEloquentQuery()->staleFollowUp()->orderBy('orders.created_at')
            ->get(['orders.id', 'orders.number', 'orders.manager_id', 'orders.assigned_to'])
            ->groupBy(fn (Order $order): int => (int) ($order->manager_id ?? Order::managerIdFor($order->assigned_to)));
    }

    private function targets(Collection $orders): Collection
    {
        return User::query()->whereKey($orders->keys()->filter()->all())->where('is_active', true)
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])->get();
    }

    /** @return array{orders: int, recipients: int} */
    public function preview(): array
    {
        $orders = $this->ordersByManager();

        return ['orders' => $orders->sum(fn (Collection $group): int => $group->count()), 'recipients' => $this->targets($orders)->count()];
    }

    public function notify(): int
    {
        $orders = $this->ordersByManager();
        $period = now(self::TIMEZONE)->format('Y-m-d-H');
        $count = 0;
        foreach ($this->targets($orders) as $target) {
            $assigned = $orders[$target->id];
            $numbers = $assigned->take(5)->pluck('number')->map(fn (string $number): string => e($number))->implode(', ');
            $remaining = max(0, $assigned->count() - 5);
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'kotlov:stale-orders:'.$target->id.':'.$period)->toString();
            $data = Notification::make()
                ->title('Ваши зависшие заказы — '.$assigned->count())
                ->body($numbers.($remaining ? ' и ещё '.$remaining : '').'. Уточните состояние и зафиксируйте результат или следующий шаг со сроком.')
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
