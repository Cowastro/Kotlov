<?php

namespace App\Filament\Resources\IntegrationProducts\Pages;

use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Services\Integrations\IntegrationManualMatchRecorder;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationProduct extends EditRecord
{
    protected static string $resource = IntegrationProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['product_id'] ?? null)) {
            $data['match_status'] = 'matched';
            $data['match_method'] = 'manual';
            $data['match_confidence'] = 1;
            $data['matched_at'] = now();
        } else {
            $data['match_status'] = 'unmatched';
            $data['match_method'] = null;
            $data['match_confidence'] = null;
            $data['matched_at'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('К списку')
                ->url(IntegrationProductResource::getUrl()),
        ];
    }

    protected function afterSave(): void
    {
        if (filled($this->record->product_id)) {
            app(IntegrationManualMatchRecorder::class)->record($this->record);
        }
    }
}
