<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderReviewInvitation;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;

final class OrderReviewLinkAction
{
    public static function make(): Action
    {
        return Action::make('customer_review_link')
            ->label('Попросить отзыв')->icon('heroicon-o-star')
            ->authorize(fn (Order $record): bool => OrderResource::canView($record))
            ->disabled(fn (Order $record): bool => ! $record->canRequestCustomerReview())
            ->tooltip(fn (Order $record): string => $record->canRequestCustomerReview()
                ? 'Скопировать ссылку на отзыв' : 'Доступно после завершения сделки')
            ->modalHeading('Ссылка для отзыва клиента')->modalWidth('md')
            ->modalDescription('Скопируйте ссылку и отправьте клиенту после покупки или разговора. Регистрация не нужна. Отзывы поступают на модерацию; ничего автоматически не отправляется.')
            ->schema([
                TextEntry::make('customer_review_url')->label('Ссылка на отзыв')
                    ->state(fn (Order $record): string => app(OrderReviewInvitation::class)->url($record))
                    ->copyable()->copyMessage('Ссылка скопирована'),
            ])
            ->modalSubmitAction(false)->modalCancelActionLabel('Закрыть');
    }
}
