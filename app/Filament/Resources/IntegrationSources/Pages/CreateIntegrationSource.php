<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationSource extends CreateRecord
{
    protected static string $resource = IntegrationSourceResource::class;
}
