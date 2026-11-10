<?php

namespace App\Filament\Resources\CrmMailingOptouts\Pages;

use App\Filament\Resources\CrmMailingOptouts\CrmMailingOptoutResource;
use App\Services\Crm\MailingSender;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCrmMailingOptouts extends ListRecords
{
    protected static string $resource = CrmMailingOptoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label('Отписать номер')->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('phone')->label('Телефон')->required()->tel(),
                    TextInput::make('note')->label('Заметка')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $added = app(MailingSender::class)->optOut((string) $data['phone'], 'manual', $data['note'] ?? null);
                    Notification::make()->success()->title($added ? 'Номер отписан' : 'Номер уже в списке или неверный')->send();
                }),
        ];
    }
}
