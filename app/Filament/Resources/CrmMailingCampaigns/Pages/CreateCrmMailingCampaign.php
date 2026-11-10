<?php

namespace App\Filament\Resources\CrmMailingCampaigns\Pages;

use App\Filament\Resources\CrmMailingCampaigns\CrmMailingCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmMailingCampaign extends CreateRecord
{
    protected static string $resource = CrmMailingCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => 'draft', 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
