<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && ! in_array($this->record->status, ['rejected', 'expired', 'cancelled'], true))
                ->requiresConfirmation()
                ->modalHeading('Создать счёт из коммерческого предложения?')
                ->modalDescription('Будет создан отдельный счёт с теми же товарами, ценами и данными клиента. Исходное КП сохранится.')
                ->action(function (): void {
                    $invoice = app(CommercialDocumentWorkflow::class)->convertToInvoice($this->record, auth()->user());
                    Notification::make()->success()->title('Счёт создан')->body($invoice->number)->send();
                    $this->redirect(CommercialDocumentResource::getUrl('view', ['record' => $invoice]));
                }),
            Action::make('accept')
                ->label('Согласовано')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status === 'sent')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->accept($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('КП согласовано клиентом')->send();
                }),
            Action::make('reject')
                ->label('Зафиксировать отказ')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->type === 'quote' && in_array($this->record->status, ['sent', 'accepted'], true))
                ->schema([
                    Select::make('reason')->label('Причина')->options(CommercialDocument::OUTCOME_REASONS)->required(),
                    Textarea::make('note')->label('Комментарий менеджера')->rows(3),
                ])
                ->action(function (array $data): void {
                    app(CommercialDocumentWorkflow::class)->reject($this->record, $data['reason'], $data['note'] ?? null);
                    $this->record->refresh();
                    Notification::make()->success()->title('Отказ зафиксирован')->send();
                }),
            Action::make('expire')
                ->label('Срок истёк')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status === 'sent')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->expire($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('КП закрыто как просроченное')->send();
                }),
            Action::make('new_revision')
                ->label('Новая редакция')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status !== 'draft')
                ->requiresConfirmation()
                ->modalDescription('Будет создан новый черновик с теми же клиентом, товарами и ценами. Отправленная редакция останется неизменной.')
                ->action(function (): void {
                    $revision = app(CommercialDocumentWorkflow::class)->createRevision($this->record, auth()->user());
                    Notification::make()->success()->title('Новая редакция создана')->body($revision->number)->send();
                    $this->redirect(CommercialDocumentResource::getUrl('edit', ['record' => $revision]));
                }),
            Action::make('mark_paid')
                ->label('Оплачено')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'invoice' && $this->record->status !== 'paid')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->markPaid($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('Счёт отмечен оплаченным')->send();
                }),
            EditAction::make()->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }
}
