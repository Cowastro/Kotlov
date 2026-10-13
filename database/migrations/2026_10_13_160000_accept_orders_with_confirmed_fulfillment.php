<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')
            ->where('status', 'new')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereNotNull('order_items.fulfillment_confirmed_at'))
            ->get(['id', 'manager_id', 'assigned_to']);

        foreach ($orders as $order) {
            $confirmation = DB::table('order_items')
                ->where('order_id', $order->id)
                ->whereNotNull('fulfillment_confirmed_at')
                ->orderBy('fulfillment_confirmed_at')
                ->first(['fulfillment_confirmed_by', 'fulfillment_confirmed_at']);

            $manager = $confirmation?->fulfillment_confirmed_by
                ? DB::table('users')->find($confirmation->fulfillment_confirmed_by)
                : null;

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'confirmed',
                'manager_id' => $order->manager_id ?: $manager?->id,
                'assigned_to' => $order->assigned_to ?: ($manager ? '@'.($manager->telegram_username ?: $manager->name) : null),
                'updated_at' => now(),
            ]);

            DB::table('order_status_history')->insert([
                'order_id' => $order->id,
                'user_id' => $manager?->id,
                'status_from' => 'new',
                'status_to' => 'confirmed',
                'comment' => 'Статус восстановлен по ранее подтверждённому исполнению позиции.',
                'created_at' => $confirmation?->fulfillment_confirmed_at ?: now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Рабочие статусы заказов не откатываются автоматически.
    }
};
