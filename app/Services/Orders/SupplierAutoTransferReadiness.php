<?php

namespace App\Services\Orders;

use App\Models\Supplier;
use App\Models\SupplierOrderRequest;

class SupplierAutoTransferReadiness
{
    /** @return array<string, mixed> */
    public function snapshot(Supplier $supplier): array
    {
        $minimumSuccessful = (int) config('shop.supplier_auto_transfer.minimum_successful_requests', 3);
        $minimumDays = (int) config('shop.supplier_auto_transfer.minimum_control_days', 7);
        $sampleSize = max($minimumSuccessful, (int) config('shop.supplier_auto_transfer.sample_size', 5));

        $manualRequests = SupplierOrderRequest::query()
            ->where('supplier_id', $supplier->id)
            ->where('transfer_mode', 'manual')
            ->whereNotNull('sent_at');
        $sample = (clone $manualRequests)
            ->whereIn('status', ['acknowledged', 'rejected', 'fulfilled'])
            ->latest('sent_at')
            ->limit($sampleSize)
            ->get();
        $successful = $sample->whereIn('status', ['acknowledged', 'fulfilled'])->count();
        $rejected = $sample->where('status', 'rejected')->count();
        $firstSentAt = (clone $manualRequests)->oldest('sent_at')->value('sent_at');
        $controlDays = $firstSentAt ? (int) now()->diffInDays($firstSentAt, true) : 0;
        $hasPortalUser = $supplier->users()
            ->where('role', 'supplier')
            ->where('is_active', true)
            ->exists();

        $blockers = collect();
        if (! $supplier->is_active) {
            $blockers->push('Поставщик отключён');
        }
        if (! $hasPortalUser) {
            $blockers->push('Нет активного пользователя кабинета');
        }
        if ($successful < $minimumSuccessful) {
            $blockers->push("Успешных контрольных заявок: {$successful} из {$minimumSuccessful}");
        }
        if ($controlDays < $minimumDays) {
            $blockers->push("Контрольный период: {$controlDays} из {$minimumDays} дн.");
        }
        if ($rejected > 0) {
            $blockers->push("В последних {$sampleSize} контрольных заявках есть отклонения: {$rejected}");
        }

        return [
            'ready' => $blockers->isEmpty(),
            'enabled' => (bool) $supplier->automatic_order_transfer_enabled,
            'minimum_successful_requests' => $minimumSuccessful,
            'minimum_control_days' => $minimumDays,
            'sample_size' => $sampleSize,
            'manual_sent_count' => (clone $manualRequests)->count(),
            'successful_count' => $successful,
            'rejected_count' => $rejected,
            'control_days' => $controlDays,
            'first_manual_sent_at' => $firstSentAt,
            'has_active_portal_user' => $hasPortalUser,
            'blockers' => $blockers->values()->all(),
        ];
    }

    public function label(Supplier $supplier): string
    {
        $snapshot = $this->snapshot($supplier);

        return match (true) {
            $snapshot['enabled'] && $snapshot['ready'] => 'Включена',
            $snapshot['enabled'] => 'Приостановлена системой',
            $snapshot['ready'] => 'Готова к включению',
            default => 'Контроль '.$snapshot['successful_count'].'/'.$snapshot['minimum_successful_requests'],
        };
    }
}
