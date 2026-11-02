<?php

namespace App\Filament\Resources\B2bOrders\Pages;

use App\Filament\Resources\B2bOrders\B2bOrderResource;
use App\Models\B2bOrderEvent;
use Filament\Resources\Pages\EditRecord;

class EditB2bOrder extends EditRecord
{
    protected static string $resource = B2bOrderResource::class;

    private ?int $previousManagerId = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousManagerId = $this->record->assigned_manager_id;

        return $data;
    }

    protected function afterSave(): void
    {
        $currentManagerId = $this->record->assigned_manager_id;
        if ((int) $this->previousManagerId === (int) $currentManagerId) {
            return;
        }

        B2bOrderEvent::query()->create([
            'b2b_order_id' => $this->record->id,
            'actor_id' => auth()->id(),
            'event' => 'manager_assigned',
            'comment' => $currentManagerId
                ? 'Назначен B2B-менеджер '.$this->record->manager?->name.'.'
                : 'Назначение B2B-менеджера снято.',
            'metadata' => [
                'previous_manager_id' => $this->previousManagerId,
                'assigned_manager_id' => $currentManagerId,
            ],
        ]);
    }
}
