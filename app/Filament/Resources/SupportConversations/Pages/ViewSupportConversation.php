<?php

namespace App\Filament\Resources\SupportConversations\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportConversation extends ViewRecord
{
    protected static string $resource = SupportConversationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->redirect(ContactRequestResource::forSiteChat($this->record));
    }
}
