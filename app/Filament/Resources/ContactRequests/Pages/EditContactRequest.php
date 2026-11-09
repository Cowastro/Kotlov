<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditContactRequest extends EditRecord
{
    protected static string $resource = ContactRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($url = ContactRequestResource::siteChatUrl($this->record)) {
            $this->redirect($url);
        }
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
