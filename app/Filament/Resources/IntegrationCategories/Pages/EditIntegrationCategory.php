<?php

namespace App\Filament\Resources\IntegrationCategories\Pages;

use App\Filament\Resources\IntegrationCategories\IntegrationCategoryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationCategory extends EditRecord
{
    protected static string $resource = IntegrationCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('К списку')
                ->url(IntegrationCategoryResource::getUrl()),
        ];
    }
}
