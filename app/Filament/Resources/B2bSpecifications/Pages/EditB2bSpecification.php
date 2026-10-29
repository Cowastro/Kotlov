<?php

namespace App\Filament\Resources\B2bSpecifications\Pages;

use App\Filament\Resources\B2bSpecifications\B2bSpecificationResource;
use Filament\Resources\Pages\EditRecord;

class EditB2bSpecification extends EditRecord
{
    protected static string $resource = B2bSpecificationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['manager_id'] ??= auth()->id();

        return $data;
    }
}
