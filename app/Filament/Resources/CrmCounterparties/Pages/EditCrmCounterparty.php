<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmCounterparty extends EditRecord
{
    protected static string $resource = CrmCounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CrmCounterpartyResource::createB2bClientAction(),
            CrmCounterpartyResource::openB2bClientAction(),
            ViewAction::make(),
            Action::make('deleteSpam')
                ->label('Удалить как спам')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'lead' && ! $this->record->trashed())
                ->action(function (): void {
                    $this->record->delete();
                    $this->redirect(CrmCounterpartyResource::getUrl('index'));
                }),
            Action::make('restore')
                ->label('Восстановить')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->trashed())
                ->action(fn () => $this->record->restore()),
        ];
    }
}
