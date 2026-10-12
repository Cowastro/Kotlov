<?php

namespace App\Filament\Resources\TelephonyEmployees\Pages;

use App\Filament\Resources\TelephonyEmployees\TelephonyEmployeeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTelephonyEmployees extends ListRecords
{
    protected static string $resource = TelephonyEmployeeResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Сотрудники и внутренние номера приходят из A1. Здесь они связываются с пользователями админки.';
    }
}
