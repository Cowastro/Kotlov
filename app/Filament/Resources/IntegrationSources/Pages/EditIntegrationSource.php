<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationSource extends EditRecord
{
    protected static string $resource = IntegrationSourceResource::class;
}
