<?php

namespace App\Filament\Resources\B2bSpecifications\Pages;

use App\Filament\Resources\B2bSpecifications\B2bSpecificationResource;
use App\Services\B2bSpecificationOcrQueue;
use App\Services\B2bSpecificationParser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
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
                ->modalHeading('Поставить документы в очередь DeepSeek?')
                ->modalDescription('Обработка пройдёт в фоне. После завершения будет создана новая редакция; похожие позиции останутся на проверку менеджеру.')
                ->schema([
                    CheckboxList::make('attachment_ids')
                        ->label('Файлы для распознавания')
                        ->options(fn (): array => $this->ocrAttachmentOptions())
                        ->default(fn (): array => array_keys($this->ocrAttachmentOptions()))
                        ->columns(1)
                        ->required(),
                ])
                ->visible(fn (): bool => $this->ocrAttachmentOptions() !== [])
                ->action(function (array $data): void {
                    try {
                        $result = app(B2bSpecificationOcrQueue::class)->enqueue(
                            $this->record->fresh(['attachments', 'currentVersion']),
                            $data['attachment_ids'] ?? [],
                            Filament::auth()->user(),
                        );
                        $this->record->refresh();

                        Notification::make()
                            ->title('Документы поставлены в очередь')
                            ->body('Файлов: '.$result['count'].'. Статус будет обновлён автоматически.')
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

    /** @return array<int, string> */
    private function ocrAttachmentOptions(): array
    {
        return $this->record->attachments
            ->filter(function ($attachment): bool {
                $extension = mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));

                return in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)
                    && ! in_array($attachment->ocr_status, ['queued', 'processing'], true);
            })
            ->mapWithKeys(fn ($attachment): array => [
                $attachment->id => $attachment->original_name.' · '.$attachment->ocrStatusLabel(),
            ])
            ->all();
    }
}
