<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditIntegrationSource extends EditRecord
{
    protected static string $resource = IntegrationSourceResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
