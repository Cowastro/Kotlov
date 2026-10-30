<?php

namespace App\Filament\Resources\B2bSpecifications\Pages;

use App\Filament\Resources\B2bSpecifications\B2bSpecificationResource;
use App\Services\B2bSpecificationOcrService;
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
            Action::make('recognizeDocuments')
                ->label('Распознать PDF / фото')
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Распознать спецификацию через DeepSeek?')
                ->modalDescription('Будет создана новая редакция. Точные совпадения свяжутся автоматически, похожие позиции останутся на проверку менеджеру.')
                ->visible(fn (): bool => $this->record->attachments->contains(function ($attachment): bool {
                    return in_array(mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)), ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true);
                }))
                ->action(function (): void {
                    try {
                        $result = app(B2bSpecificationOcrService::class)->recognize(
                            $this->record->fresh(['attachments', 'currentVersion']),
                            null,
                            Filament::auth()->user(),
                        );
                        $this->record->refresh();

                        Notification::make()
                            ->title('Документы распознаны')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('Распознавание не выполнено')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
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
