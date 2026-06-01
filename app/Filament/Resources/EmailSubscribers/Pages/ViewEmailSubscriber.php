<?php

namespace App\Filament\Resources\EmailSubscribers\Pages;

use App\Filament\Resources\EmailSubscribers\EmailSubscriberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailSubscriber extends ViewRecord
{
    protected static string $resource = EmailSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
