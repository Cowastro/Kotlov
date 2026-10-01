<?php

namespace App\Filament\Resources\InstallerProfiles\Pages;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallerProfile extends ViewRecord
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
            EditAction::make(),
        ];
    }
}
