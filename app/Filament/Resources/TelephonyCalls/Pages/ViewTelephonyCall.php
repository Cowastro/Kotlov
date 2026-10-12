<?php

namespace App\Filament\Resources\TelephonyCalls\Pages;

use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewTelephonyCall extends ViewRecord
{
    protected static string $resource = TelephonyCallResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
