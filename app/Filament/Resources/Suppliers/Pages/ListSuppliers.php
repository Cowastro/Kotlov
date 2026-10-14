<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string
    {
        return 'Поставщики';
    }

    public function getSubheading(): ?string
    {
        return 'Введите бренд в поиск — увидите поставщика, контакты и прямую ссылку на прайс.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Добавить поставщика'),
        ];
    }
}
