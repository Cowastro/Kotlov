<?php

namespace App\Filament\Resources\PartnerOnboardings\Pages;

use App\Filament\Resources\PartnerOnboardings\PartnerOnboardingResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerOnboardings extends ListRecords
{
    protected static string $resource = PartnerOnboardingResource::class;

    protected function getHeaderActions(): array
    {
        return [PartnerOnboardingResource::inviteAction()];
    }
}
