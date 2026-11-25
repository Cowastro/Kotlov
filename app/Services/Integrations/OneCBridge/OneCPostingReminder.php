<?php

namespace App\Services\Integrations\OneCBridge;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Ramsey\Uuid\Uuid;

/** Internal staff reminder only: posting remains a human action in 1C. */
class OneCPostingReminder
{
    public static function needed(Order $order): bool
    {
        return filled($order->onec_order_guid) && ! $order->onec_posted && ! $order->onec_deleted
            && ! $order->archived_at && ! in_array($order->status, ['cancelled', 'completed', 'delivered'], true);
    }

    public function sync(Order $order): void
    {
        $existing = DatabaseNotification::query()->where('data->onec_posting_order_id', $order->id);
        if (! self::needed($order)) {
            $existing->whereNull('read_at')->update(['read_at' => now()]);

            return;
        }

        $manager = $order->manager ?: User::find(Order::managerIdFor($order->assigned_to));
        $targets = $manager?->is_active && $manager->isManager()
            ? collect([$manager]) : User::query()->where('role', 'admin')->where('is_active', true)->get();
        $ids = [];
        foreach ($targets as $target) {
            // Stable primary key also prevents duplicates from concurrent status checks.
            $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'kotlov:onec-posting:'.$order->id.':'.$order->onec_order_guid.':'.$target->id)->toString();
            $ids[] = $id;
            $data = Notification::make()->title('Провести в 1С: '.e($order->onec_order_number ?: $order->number))
                ->body('Заказ '.e($order->number).'. Проверьте товары, действия строк и доставку в 1С, затем проведите документ. На сайте нажмите «Проверить проведение».')
                ->warning()->icon('heroicon-o-document-check')
                ->actions([Action::make('openOrder')->label('Открыть заказ')
                    ->url(OrderResource::getUrl('view', ['record' => $order->id]))->markAsRead()])
                ->getDatabaseMessage();
            $data['onec_posting_order_id'] = $order->id;
            $target->notifications()->firstOrCreate(['id' => $id], [
                'type' => \Filament\Notifications\DatabaseNotification::class, 'data' => $data, 'read_at' => null,
            ]);
        }
        // Reassignment or relinking retires the old reminder, without erasing its history.
        $existing->whereNotIn('id', $ids)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function syncOpen(): int
    {
        $count = 0;
        Order::query()->whereNotNull('onec_order_guid')->where(fn ($q) => $q->whereNull('onec_posted')->orWhere('onec_posted', false))
            ->eachById(function (Order $order) use (&$count): void {
                $this->sync($order);
                $count += self::needed($order) ? 1 : 0;
            });

        return $count;
    }
}
