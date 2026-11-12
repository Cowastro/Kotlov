<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OrderDealControlNotifier
{
    public function notify(): int
    {
        $cutoff = now('Europe/Minsk')->startOfDay()->utc();
        $ids = OrderResource::getEloquentQuery()->dealControl('overdue')
            ->where(fn ($query) => $query->whereNull('deal_control_notified_at')
                ->orWhere('deal_control_notified_at', '<', $cutoff))
            ->pluck('id');
        $count = 0;

        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id, $cutoff): int {
                $order = Order::query()->lockForUpdate()->find($id);
                if (! $order || ! $order->dealIsActive() || ! $order->deal_control_at?->isPast()
                    || $order->deal_control_notified_at?->greaterThanOrEqualTo($cutoff)) {
                    return 0;
                }
                $manager = $order->manager ?: User::find(Order::managerIdFor($order->assigned_to));
                $targets = $manager?->is_active && in_array($manager->role, ['admin', 'manager', 'sales_manager'], true)
                    ? collect([$manager])
                    : User::query()->where('role', 'admin')->where('is_active', true)->get();
                if ($targets->isEmpty()) {
                    return 0;
                }
                foreach ($targets as $target) {
                    $target->notifyNow(Notification::make()
                        ->title('Заказ '.e($order->number).' — пора выполнить следующий шаг')
                        ->body(e($order->deal_next_action ?: 'Уточнить результат и назначить следующий шаг')
                            .' · '.e($order->dealControlLabel()))
                        ->warning()->icon('heroicon-o-clock')
                        ->actions([
                            Action::make('openOrder')->label('Открыть заказ')
                                ->url(OrderResource::getUrl('view', ['record' => $order->id]))
                                ->markAsRead(),
                        ])->toDatabase());
                }
                $order->forceFill(['deal_control_notified_at' => now()])->save();

                return 1;
            });
        }

        return $count;
    }
}
