<?php

namespace App\Filament\Resources\CrmMailboxes\Pages;

use App\Filament\Resources\CrmMailboxes\CrmMailboxResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmMailboxes extends ListRecords
{
    protected static string $resource = CrmMailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getSubheading(): ?string
    {
        return 'Подключение входящих по IMAP и ответов по SMTP. Пароли сохраняются зашифрованно.';
    }
}
