<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewCommercialDocument extends ViewRecord
{
    protected static string $resource = CommercialDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Открыть PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('admin.commercial-documents.pdf', $this->record))
                ->openUrlInNewTab(),
            Action::make('mail')
                ->label('Подготовить письмо')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->action(function (): void {
                    $issues = app(CommercialDocumentReadiness::class)->issues($this->record);
                    if ($issues !== []) {
                        Notification::make()
                            ->warning()
                            ->title('Документ пока не готов к отправке')
                            ->body(implode("\n", array_map(fn (string $issue): string => '• '.$issue, $issues)))
                            ->send();

                        return;
                    }

                    try {
                        $thread = app(CommercialDocumentWorkflow::class)->createMailDraft($this->record, auth()->user());
                        Notification::make()->success()->title('Письмо с PDF готово')->body('Проверьте текст и отправьте письмо клиенту.')->send();
                        $this->redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось подготовить письмо')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('convert')
                ->label('Сделать счёт')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote')
                ->requiresConfirmation()
                ->modalHeading('Создать счёт из коммерческого предложения?')
                ->modalDescription('Будет создан отдельный счёт с теми же товарами, ценами и данными клиента. Исходное КП сохранится.')
                ->action(function (): void {
                    $invoice = app(CommercialDocumentWorkflow::class)->convertToInvoice($this->record, auth()->user());
                    Notification::make()->success()->title('Счёт создан')->body($invoice->number)->send();
                    $this->redirect(CommercialDocumentResource::getUrl('view', ['record' => $invoice]));
                }),
            EditAction::make(),
        ];
    }
}
