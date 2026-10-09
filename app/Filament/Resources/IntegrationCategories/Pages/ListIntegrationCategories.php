<?php

namespace App\Filament\Resources\IntegrationCategories\Pages;

use App\Filament\Resources\IntegrationCategories\IntegrationCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationCategories extends ListRecords
{
    protected static string $resource = IntegrationCategoryResource::class;

    public function getTitle(): string
    {
        return 'Группы поставщиков';
    }

    public function getSubheading(): ?string
    {
        return 'Исходные папки 1С и их соответствие единому каталогу kotlov.by';
    }
}
