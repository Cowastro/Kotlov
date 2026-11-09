<?php

namespace App\Filament\Resources\SupportConversations\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\ContactRequest;
use Filament\Resources\Pages\ListRecords;

class ListSupportConversations extends ListRecords
{
    protected static string $resource = SupportConversationResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->redirect(ContactRequestResource::getUrl('index', [
            'tab' => 'active',
            'tableFilters' => ['source' => ['value' => ContactRequest::SITE_CHAT_SOURCE]],
        ]));
    }
}
