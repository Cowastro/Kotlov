<?php

namespace App\Filament\Resources\B2bSpecifications\Pages;

use App\Filament\Resources\B2bSpecifications\B2bSpecificationResource;
use App\Services\B2bSpecificationParser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditB2bSpecification extends EditRecord
{
    protected static string $resource = B2bSpecificationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['manager_id'] ??= auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reparse')
                ->label('Повторно разобрать таблицу')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Строки будут заново извлечены из Excel/CSV. Ручные решения по текущим строкам заменятся новым результатом.')
                ->visible(fn (): bool => $this->record->attachments->contains(function ($attachment): bool {
                    return in_array(mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)), ['xlsx', 'xls', 'csv', 'ods'], true);
                }))
                ->action(function (): void {
                    $result = app(B2bSpecificationParser::class)->parse(
                        $this->record->fresh(['attachments', 'currentVersion']),
                        null,
                        'reparse',
                        Filament::auth()->user(),
                    );
                    $this->record->refresh();

                    Notification::make()
                        ->title('Таблица обработана повторно')
                        ->body($result['message'])
                        ->color($result['status'] === 'parsed' ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
