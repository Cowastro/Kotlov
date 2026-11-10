<?php

namespace App\Filament\Resources\CrmMailingCampaigns\Pages;

use App\Filament\Resources\CrmMailingCampaigns\CrmMailingCampaignResource;
use App\Filament\Resources\CrmMailingOptouts\CrmMailingOptoutResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmMailingCampaigns extends ListRecords
{
    protected static string $resource = CrmMailingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('optouts')->label('Отписавшиеся')->icon('heroicon-o-no-symbol')->color('gray')
                ->url(CrmMailingOptoutResource::getUrl('index')),
            CreateAction::make()->label('Новая рассылка'),
        ];
    }
}
