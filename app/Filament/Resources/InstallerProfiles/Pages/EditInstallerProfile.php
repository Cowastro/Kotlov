<?php

namespace App\Filament\Resources\InstallerProfiles\Pages;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInstallerProfile extends EditRecord
{
    protected static string $resource = InstallerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cabinetPreview')
                ->label('Просмотреть кабинет')
                ->icon('heroicon-o-computer-desktop')
                ->url(fn () => route('admin.installer-cabinet-preview', $this->record))
                ->openUrlInNewTab(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
