<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationSources extends ListRecords
{
    protected static string $resource = IntegrationSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить источник')];
    }
}
