<?php

namespace App\Filament\Resources\B2bOrders;

use App\Models\B2bOrder;
use App\Services\B2bOrders\B2bOrderInvoice;
use App\Services\B2bOrders\B2bOrderWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * One button per stage of the B2B order, shared by the queue and the order
 * card: only the step the order needs now is visible.
 */
final class B2bOrderActions
{
    /** @return list<Action> */
    public static function steps(): array
    {
        return [
            Action::make('accept')->label('Принять в работу')->icon(Heroicon::OutlinedHandRaised)->color('success')
                ->visible(fn (B2bOrder $record): bool => in_array($record->status, ['new', 'assigned'], true))
                ->action(fn (B2bOrder $record, $livewire) => self::done($livewire,
                    app(B2bOrderWorkflow::class)->accept($record, auth()->user()), 'Заказ принят, остатки проверены')),

            Action::make('sendToClient')->label('Отправить клиенту')->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                ->visible(fn (B2bOrder $record): bool => in_array($record->status, ['checking', 'awaiting_supplier', 'proposal_ready'], true))
                ->disabled(fn (B2bOrder $record): bool => ! $record->load('items')->stockIsSettled())
                ->tooltip(fn (B2bOrder $record): ?string => $record->load('items')->stockIsSettled()
                    ? null : 'Сначала решите строки без подтверждённого наличия')
                ->modalHeading('Отправить заказ клиенту на подтверждение')
                ->modalDescription('Клиент получит письмо с составом, ценами и сроками. После его подтверждения счёт сформируется автоматически и появится в кабинете.')
                ->schema([Textarea::make('message')->label('Сообщение клиенту (необязательно)')->rows(3)->maxLength(2000)])
                ->modalSubmitActionLabel('Отправить')
                ->action(fn (B2bOrder $record, array $data, $livewire) => self::done($livewire,
                    app(B2bOrderWorkflow::class)->sendToClient($record, auth()->user(), $data['message'] ?? null), 'Отправлено клиенту')),

            Action::make('issueInvoice')->label('Выставить счёт')->icon(Heroicon::OutlinedDocumentCurrencyDollar)->color('success')
                ->visible(fn (B2bOrder $record): bool => $record->status === 'client_confirmed')
                ->requiresConfirmation()
                ->modalDescription('Счёт появится в B2B-кабинете и уйдёт клиенту на почту.')
                ->action(function (B2bOrder $record, $livewire): void {
                    $document = app(B2bOrderInvoice::class)->issue($record, auth()->user());
                    self::done($livewire, $record->refresh(), 'Счёт '.$document->number.' выставлен');
                }),

            Action::make('markPaid')->label('Оплата получена')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (B2bOrder $record): bool => in_array($record->status, ['invoice_issued', 'awaiting_payment'], true))
                ->requiresConfirmation()
                ->schema([Textarea::make('comment')->label('Комментарий (номер платёжки и т. п.)')->rows(2)])
                ->action(fn (B2bOrder $record, array $data, $livewire) => self::done($livewire,
                    app(B2bOrderWorkflow::class)->markPaid($record, auth()->user(), $data['comment'] ?? null), 'Заказ оплачен')),

            Action::make('markCreditPaid')->label('Оплата по отсрочке получена')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (B2bOrder $record): bool => B2bOrderWorkflow::awaitsCreditPayment($record))
                ->requiresConfirmation()
                ->modalDescription('Заказ станет оплаченным, кредитный лимит компании освободится. Этап заказа не меняется.')
                ->schema([Textarea::make('comment')->label('Комментарий (номер платёжки и т. п.)')->rows(2)])
                ->action(fn (B2bOrder $record, array $data, $livewire) => self::done($livewire,
                    app(B2bOrderWorkflow::class)->markCreditPaid($record, auth()->user(), $data['comment'] ?? null), 'Оплата отмечена')),

            Action::make('fulfil')->icon(Heroicon::OutlinedTruck)->color('success')
                ->label(fn (B2bOrder $record): string => match ($record->status) {
                    'assembling' => 'Собран — готов к отгрузке',
                    'ready_to_ship' => 'Отгружено',
                    'shipped' => 'Закрыть заказ',
                    default => 'Передать на склад',
                })
                ->visible(fn (B2bOrder $record): bool => in_array($record->status, ['paid', 'credit_approved', 'assembling', 'ready_to_ship', 'shipped'], true))
                ->requiresConfirmation()
                ->action(fn (B2bOrder $record, $livewire) => self::done($livewire,
                    app(B2bOrderWorkflow::class)->advanceFulfilment($record, auth()->user()), 'Этап обновлён')),
        ];
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')->label('Отменить заказ')->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->visible(fn (B2bOrder $record): bool => ! in_array($record->status, ['shipped', 'completed', 'cancelled'], true))
            ->schema([Textarea::make('reason')->label('Причина — её увидит клиент')->required()->rows(3)])
            ->action(fn (B2bOrder $record, array $data, $livewire) => self::done($livewire,
                app(B2bOrderWorkflow::class)->cancel($record, auth()->user(), $data['reason']), 'Заказ отменён, резерв снят'));
    }

    /** Manual override for unusual cases; regular work goes through the step buttons. */
    public static function changeStage(): Action
    {
        return Action::make('nextStatus')->label('Сменить этап вручную')->icon(Heroicon::OutlinedArrowRightCircle)->color('gray')
            ->schema([
                Select::make('status')->label('Новый этап')
                    ->options(fn (B2bOrder $record): array => app(B2bOrderWorkflow::class)->allowedTransitions($record))
                    ->required(),
                Textarea::make('comment')->label('Комментарий в хронологию')->rows(3),
            ])
            ->visible(fn (B2bOrder $record): bool => ! in_array($record->status, ['new', 'completed', 'cancelled'], true))
            ->action(fn (B2bOrder $record, array $data, $livewire) => self::done($livewire,
                app(B2bOrderWorkflow::class)->transition($record, $data['status'], auth()->user(), $data['comment'] ?? null), 'Этап обновлён'));
    }

    private static function done(mixed $livewire, B2bOrder $order, string $title): void
    {
        Notification::make()->success()->title($title)->body(B2bOrder::STATUSES[$order->status] ?? $order->status)->send();
        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->record = $order->refresh();
            $livewire->refreshFormData(['status', 'assigned_manager_id']);
        }
    }
}
