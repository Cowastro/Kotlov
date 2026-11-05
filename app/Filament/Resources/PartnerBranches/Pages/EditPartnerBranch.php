<?php

namespace App\Filament\Resources\PartnerBranches\Pages;

use App\Filament\Resources\PartnerBranches\PartnerBranchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerBranch extends EditRecord
{
    protected static string $resource = PartnerBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [PartnerBranchResource::citySiteAction(), DeleteAction::make()];
    }
}
