<?php

namespace App\Filament\Resources\TelephonyConnections\Pages;

use App\Filament\Resources\TelephonyConnections\TelephonyConnectionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTelephonyConnection extends CreateRecord
{
    protected static string $resource = TelephonyConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['webhook_secret'] = Str::random(48);

        return $data;
    }
}
