<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Services\CommercialDocuments\CommercialDocumentClientResolver;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCommercialDocument extends EditRecord
{
    protected static string $resource = CommercialDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function afterSave(): void
    {
        app(CommercialDocumentClientResolver::class)->sync($this->record);
        $this->record->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return CommercialDocumentResource::getUrl('view', ['record' => $this->record]);
    }
}
