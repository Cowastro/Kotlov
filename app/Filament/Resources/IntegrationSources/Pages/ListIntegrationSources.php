<?php

namespace App\Filament\Resources\IntegrationSources\Pages;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListIntegrationSources extends ListRecords
{
    protected static string $resource = IntegrationSourceResource::class;

    public function getTabs(): array
    {
        return [
            'onec' => Tab::make('Обмен с 1С')->modifyQueryUsing(fn ($query) => $query->where('code', 'onec')),
            'all' => Tab::make('Все источники'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить источник')];
    }
}
