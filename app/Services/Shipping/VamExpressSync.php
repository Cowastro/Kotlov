<?php

namespace App\Services\Shipping;

use App\Models\VamExpressShipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VamExpressSync
{
    public function run(): string
    {
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            return 'Обновление уже выполняется.';
        }
        $settings = app(VamExpressSettings::class);
        try {
            if (! $settings->due()) {
                return 'Обновление пока не требуется.';
            }
            $ids = $settings->markAttempt();
            $credentials = $settings->get();
            $portal = app(VamExpressPortal::class);
            $portal->login($credentials['username'], $credentials['password']);
            $rows = $portal->recent();
            $recentIds = array_column($rows, 'remote_id');
            // Round-robin refresh of older linked shipments; a missing row never means cancellation.
            $linked = VamExpressShipment::whereNotNull('order_id')->whereNotIn('remote_id', $recentIds)
                ->orderBy('checked_at')->orderBy('id')->limit(max(0, 10 - count($ids)))->pluck('remote_id')->all();
            $missing = 0;
            $checkedIds = [];
            foreach (array_unique(array_merge($ids, $linked)) as $id) {
                $found = $portal->find($id);
                $missing += count($found) === 0 ? 1 : 0;
                $rows = array_merge($rows, $found);
                if (strlen($id) === 32) {
                    $checkedIds[] = $id;
                }
            }
            DB::transaction(function () use ($rows, $checkedIds): void {
                foreach ($rows as $row) {
                    VamExpressShipment::updateOrCreate(['remote_id' => $row['remote_id']], array_merge($row, ['synced_at' => now(), 'checked_at' => now()]));
                }
                VamExpressShipment::whereIn('remote_id', $checkedIds)->update(['checked_at' => now()]);
            });
            $count = count(array_unique(array_column($rows, 'remote_id')));
            $message = "Обновлено заявок: {$count}.".($missing ? " Не найдены по ID: {$missing}; сохранённые данные оставлены." : '');
            try {
                app(VamExpressProfiles::class)->fill($portal);
            } catch (VamExpressException) {
                $message .= ' Некоторые профили отправителей ещё не загружены.';
            }
            $settings->finish(true, $message, $ids);

            return $message;
        } catch (\Throwable $exception) {
            $message = $exception instanceof VamExpressException ? $exception->getMessage() : 'Не удалось обновить заявки ВАМ Экспресс. Сохранённые данные оставлены.';
            $settings->finish(false, $message);

            return $message;
        } finally {
            $lock->release();
        }
    }
}
