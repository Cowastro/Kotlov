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

        return $this->publishRequest($request, $user, 'manual', null, $note);
    }

    public function publishAutomatically(SupplierOrderRequest $request, string $runUuid): SupplierOrderRequest
    {
        return $this->publishRequest(
            $request,
            null,
            'automatic',
            $runUuid,
            'Автоматически передано после подтверждённого контрольного периода.',
        );
    }

    public function markEmailSent(SupplierOrderRequest $request, User $user, ?string $note = null): SupplierOrderRequest
    {
        if (! $user->isManager()) {
            throw ValidationException::withMessages([
                'request' => 'Отправлять заявки поставщикам может только менеджер.',
            ]);
        }

        return DB::transaction(function () use ($request, $user, $note): SupplierOrderRequest {
            $locked = SupplierOrderRequest::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'request' => "Заявка {$locked->number} уже отправлена или закрыта.",
                ]);
            }
            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'request' => 'Нельзя отправить пустую заявку.',
                ]);
            }

            $locked->forceFill([
                'status' => 'sent',
                'transfer_mode' => 'email',
                'automatic_transfer_run_uuid' => null,
                'sent_at' => now(),
                'sent_by' => $user->id,
                'status_updated_by' => $user->id,
                'status_updated_at' => now(),
                'note' => filled($note) ? trim($note) : $locked->note,
            ])->save();

            $this->recordHistory($locked, $user, 'manager_email', 'draft', 'sent', $note);

            return $locked->fresh(['supplier', 'items', 'statusHistories', 'mailDraft']);
        });
    }

    public function recordEmailResponse(
        SupplierOrderRequest $request,
        string $status,
        User $user,
        ?string $note = null,
    ): SupplierOrderRequest {
        if (! $user->isManager()) {
            throw ValidationException::withMessages([
                'request' => 'Фиксировать ответ поставщика может только менеджер.',
            ]);
        }
        if (! in_array($status, ['acknowledged', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Выберите подтверждение резерва или отказ поставщика.',
            ]);
        }
        if (blank($note)) {
            throw ValidationException::withMessages([
                'note' => 'Кратко укажите, что ответил поставщик.',
            ]);
        }

        return DB::transaction(function () use ($request, $status, $user, $note): SupplierOrderRequest {
            $locked = SupplierOrderRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'sent' || $locked->transfer_mode !== 'email') {
                throw ValidationException::withMessages([
                    'request' => 'Зафиксировать ответ можно только для отправленной email-заявки.',
                ]);
            }

            $now = now();
            $locked->forceFill([
                'status' => $status,
                'acknowledged_at' => $status === 'acknowledged' ? $now : null,
                'rejected_at' => $status === 'rejected' ? $now : null,
                'supplier_response_note' => trim((string) $note),
                'status_updated_by' => $user->id,
                'status_updated_at' => $now,
            ])->save();

            $this->recordHistory($locked, $user, 'manager_email', 'sent', $status, $note);

            return $locked->fresh(['supplier', 'items', 'statusHistories', 'mailDraft']);
        });
    }

    private function publishRequest(
        SupplierOrderRequest $request,
        ?User $user,
        string $mode,
        ?string $runUuid,
        ?string $note,
    ): SupplierOrderRequest {
        return DB::transaction(function () use ($request, $user, $mode, $runUuid, $note): SupplierOrderRequest {
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
            if ($mode === 'automatic') {
                $readiness = app(SupplierAutoTransferReadiness::class)->snapshot($locked->supplier);
                if (! $locked->supplier->automatic_order_transfer_enabled || ! $readiness['ready']) {
                    throw ValidationException::withMessages([
                        'request' => 'Автоматическая передача поставщику отключена или контрольный период больше не соответствует требованиям.',
                    ]);
                }
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
                'transfer_mode' => $mode,
                'automatic_transfer_run_uuid' => $mode === 'automatic' ? $runUuid : null,
                'sent_at' => now(),
                'sent_by' => $user?->id,
                'status_updated_by' => $user?->id,
                'status_updated_at' => now(),
                'note' => filled($note) ? trim($note) : $locked->note,
            ])->save();

            $this->recordHistory(
                $locked,
                $user,
                $mode === 'automatic' ? 'system' : 'manager',
                $from,
                'sent',
                $note,
                $runUuid,
            );

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
        ?User $user,
        string $scope,
        ?string $from,
        string $to,
        ?string $note,
        ?string $runUuid = null,
    ): void {
        $request->statusHistories()->create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Автоматическая передача',
            'actor_scope' => $scope,
            'run_uuid' => $runUuid,
            'status_from' => $from,
            'status_to' => $to,
            'note' => filled($note) ? trim($note) : null,
        ]);
    }
}
