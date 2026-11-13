<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderReviewSms;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OrderReviewSmsAction
{
    public static function make(): Action
    {
        return Action::make('customer_review_sms')
            ->label('Отправить отзыв по СМС')->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->disabled(fn (Order $record): bool => ! $record->canRequestCustomerReview())
            ->tooltip(fn (Order $record): string => $record->canRequestCustomerReview()
                ? 'Отправить клиенту ссылку на отзыв' : 'Доступно после завершения сделки')
            ->modalHeading('Отправить ссылку на отзыв')->modalWidth('md')
            ->modalDescription('Проверьте номер клиента. Отправка произойдёт после нажатия кнопки.')
            ->modalSubmitActionLabel('Отправить 1 СМС')
            ->fillForm(fn (Order $record): array => ['phone' => $record->customer_phone])
            ->schema([
                TextInput::make('phone')->label('Телефон клиента')->tel()->telRegex('/^[+0-9()\s-]+$/D')->required()->maxLength(30),
                TextEntry::make('sms_preview')->label('Текст сообщения')
                    ->state(fn (Order $record): string => app(OrderReviewSms::class)->message($record))
                    ->helperText(fn (Order $record): string => mb_strlen(app(OrderReviewSms::class)->message($record)).' из 70 символов · 1 СМС'),
                TextEntry::make('last_sent')->label('Последняя отправка')
                    ->state(fn (Order $record): string => $record->review_sms_sent_at
                        ? \Illuminate\Support\Carbon::parse($record->review_sms_sent_at)->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)'
                        : 'Ещё не отправляли'),
            ])
            ->action(function (Order $record, array $data): void {
                try {
                    app(OrderReviewSms::class)->send($record, $data['phone']);
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['phone' => $e->getMessage()]);
                }
                Notification::make()->success()->title('СМС принято сервисом отправки')->body('Ссылка на отзыв отправлена клиенту. Запись добавлена в историю заказа.')->send();
            });
    }
}
