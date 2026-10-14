<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Orders\OrderResource;
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
            Action::make('create_order')
                ->label('Создать заказ')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && $this->record->status === 'accepted'
                    && ! $this->record->order()->exists())
                ->requiresConfirmation()
                ->modalHeading('Создать заказ из согласованного КП?')
                ->modalDescription('Клиент, реквизиты, товары, цены и условия будут перенесены в заказ. Заказ попадёт в обычный процесс обработки и обмена с 1С.')
                ->action(function (): void {
                    try {
                        $order = app(CommercialDocumentWorkflow::class)->convertToOrder($this->record, auth()->user());
                        Notification::make()->success()->title('Заказ создан')->body($order->number)->send();
                        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось создать заказ')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('open_order')
                ->label('Открыть заказ')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn (): bool => $this->record->order()->exists())
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->record->order()->firstOrFail()])),
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
            Action::make('request_revision')
                ->label('Нужна корректировка')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && in_array($this->record->status, ['sent', 'accepted'], true))
                ->schema([
                    Textarea::make('note')
                        ->label('Что просит изменить клиент')
                        ->placeholder('Например: заменить модель, пересчитать количество и срок поставки')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(CommercialDocumentWorkflow::class)->requestRevision($this->record, $data['note']);
                    $this->record->refresh();
                    Notification::make()
                        ->warning()
                        ->title('Корректировка зафиксирована')
                        ->body('Создайте новую редакцию — исходное КП останется в истории.')
                        ->send();
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
