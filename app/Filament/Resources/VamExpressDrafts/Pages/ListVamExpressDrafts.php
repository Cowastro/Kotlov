<?php

namespace App\Filament\Resources\VamExpressDrafts\Pages;

use App\Filament\Concerns\HasVamExpressNavigation;
use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVamExpressDrafts extends ListRecords
{
    use HasVamExpressNavigation;

    protected static string $resource = VamExpressDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Новый черновик')];
    }
}
