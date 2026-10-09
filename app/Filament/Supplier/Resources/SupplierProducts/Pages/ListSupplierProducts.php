<?php

namespace App\Filament\Supplier\Resources\SupplierProducts\Pages;

use App\Filament\Supplier\Resources\SupplierProducts\SupplierProductResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierProducts extends ListRecords
{
    protected static string $resource = SupplierProductResource::class;

    public function getTitle(): string
    {
        return 'Мои товары';
    }

    public function getSubheading(): ?string
    {
        return 'Цены, остатки и связь с карточками KOTLOV. Данные доступны только по назначенным поставщикам.';
    }
}
