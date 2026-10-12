<?php

namespace App\Filament\Resources\TelephonyConnections\Pages;

use App\Filament\Resources\TelephonyConnections\TelephonyConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelephonyConnections extends ListRecords
{
    protected static string $resource = TelephonyConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(TelephonyConnectionResource::canCreate())];
    }

    public function getSubheading(): ?string
    {
        return 'Webhook принимает события отдельно; Open API сверяет историю, сотрудников и пропущенные звонки.';
    }
}
