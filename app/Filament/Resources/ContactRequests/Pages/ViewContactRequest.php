<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function afterFill(): void
    {
        if ($this->record->status === 'new') {
            $this->record->update(['status' => 'read']);
        }
    }
}
