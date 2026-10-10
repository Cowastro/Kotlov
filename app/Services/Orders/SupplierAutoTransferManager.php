<?php

namespace App\Services\Orders;

use App\Models\Supplier;
use App\Models\SupplierAutoTransferDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierAutoTransferManager
{
    public function setEnabled(Supplier $supplier, bool $enabled, User $user, ?string $note = null): Supplier
    {
        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'automatic_order_transfer' => 'Автоматическую передачу может включать только администратор.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $enabled, $user, $note): Supplier {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $snapshot = app(SupplierAutoTransferReadiness::class)->snapshot($supplier);

            if ($enabled && ! $snapshot['ready']) {
                throw ValidationException::withMessages([
                    'automatic_order_transfer' => 'Контрольный период не завершён: '.implode('; ', $snapshot['blockers']).'.',
                ]);
            }

            if ((bool) $supplier->automatic_order_transfer_enabled === $enabled) {
                return $supplier;
            }

            $supplier->forceFill([
                'automatic_order_transfer_enabled' => $enabled,
                'automatic_order_transfer_approved_at' => $enabled ? now() : null,
                'automatic_order_transfer_approved_by' => $enabled ? $user->id : null,
                'automatic_order_transfer_note' => filled($note) ? trim($note) : null,
            ])->save();

            SupplierAutoTransferDecision::query()->create([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'enabled' => $enabled,
                'readiness_snapshot' => $snapshot,
                'note' => filled($note) ? trim($note) : null,
                'decided_at' => now(),
            ]);

            return $supplier->refresh();
        });
    }
}
