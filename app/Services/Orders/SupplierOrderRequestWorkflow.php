<?php

namespace App\Services\Orders;

use App\Models\SupplierOrderRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierOrderRequestWorkflow
{
    public function publish(SupplierOrderRequest $request, User $user, ?string $note = null): SupplierOrderRequest
    {
        if (! $user->isManager()) {
            throw ValidationException::withMessages([
                'request' => 'Передать заявку поставщику может только менеджер.',
            ]);
        }

        return DB::transaction(function () use ($request, $user, $note): SupplierOrderRequest {
            $locked = SupplierOrderRequest::query()
                ->with(['supplier.users', 'items'])
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'request' => "Заявка {$locked->number} уже не является черновиком.",
                ]);
            }
            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'request' => 'Нельзя передать пустую заявку.',
                ]);
            }
            if (! $locked->supplier?->is_active) {
                throw ValidationException::withMessages([
                    'request' => 'Поставщик отключён. Сначала проверьте его настройки.',
                ]);
            }
            if (! $locked->supplier->users->contains(
                fn (User $supplierUser): bool => $supplierUser->isSupplier() && $supplierUser->is_active,
            )) {
                throw ValidationException::withMessages([
                    'request' => 'У поставщика нет активного пользователя кабинета. Заявка не опубликована.',
                ]);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_by' => $user->id,
                'status_updated_by' => $user->id,
                'status_updated_at' => now(),
                'note' => filled($note) ? trim($note) : $locked->note,
            ])->save();

            $this->recordHistory($locked, $user, 'manager', $from, 'sent', $note);

            return $locked->fresh(['supplier', 'items', 'statusHistories']);
        });
    }

    public function respond(
        SupplierOrderRequest $request,
        string $status,
        User $user,
        ?string $note = null,
    ): SupplierOrderRequest {
        return DB::transaction(function () use ($request, $status, $user, $note): SupplierOrderRequest {
            $locked = SupplierOrderRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $user->isSupplier() || ! $user->suppliers()->whereKey($locked->supplier_id)->exists()) {
                throw ValidationException::withMessages([
                    'request' => 'Эта заявка не принадлежит вашему поставщику.',
                ]);
            }

            $allowed = match ($locked->status) {
                'sent' => ['acknowledged', 'rejected'],
                'acknowledged' => ['fulfilled', 'rejected'],
                default => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'request' => 'Недопустимый переход статуса заявки.',
                ]);
            }
            if ($status === 'rejected' && blank($note)) {
                throw ValidationException::withMessages([
                    'note' => 'Укажите причину отклонения заявки.',
                ]);
            }

            $from = $locked->status;
            $now = now();
            $locked->forceFill([
                'status' => $status,
                'acknowledged_at' => $status === 'acknowledged' ? $now : $locked->acknowledged_at,
                'rejected_at' => $status === 'rejected' ? $now : null,
                'fulfilled_at' => $status === 'fulfilled' ? $now : null,
                'supplier_response_note' => filled($note) ? trim($note) : $locked->supplier_response_note,
                'status_updated_by' => $user->id,
                'status_updated_at' => $now,
            ])->save();

            $this->recordHistory($locked, $user, 'supplier', $from, $status, $note);

            return $locked->fresh(['supplier', 'items', 'statusHistories']);
        });
    }

    private function recordHistory(
        SupplierOrderRequest $request,
        User $user,
        string $scope,
        ?string $from,
        string $to,
        ?string $note,
    ): void {
        $request->statusHistories()->create([
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_scope' => $scope,
            'status_from' => $from,
            'status_to' => $to,
            'note' => filled($note) ? trim($note) : null,
        ]);
    }
}
