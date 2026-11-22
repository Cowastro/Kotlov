<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\InternalOrderManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class InternalOrderActions
{
    public static function confirm(): Action
    {
        return Action::make('confirmInternalOrder')->label('Подтвердить все')
            ->icon('heroicon-o-check-badge')->color('success')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => $record->dealIsActive() && (! $record->is_internal || $record->needsFulfillmentConfirmation()))
            ->requiresConfirmation()->modalHeading('Подтвердить внутренний заказ целиком?')
            ->modalDescription('Вы подтверждаете наличие всех оставшихся позиций и отмечаете заказ как внутренний. Используются уже выбранные поставщики и входные цены. Это ваше подтверждение, а не автоматическая проверка складов. Уведомления клиенту и документы 1С не отправляются.')
            ->modalSubmitActionLabel('Подтвердить все позиции')
            ->action(function (Order $record): void {
                app(InternalOrderManager::class)->confirm($record, auth()->user());
                $record->refresh();
                Notification::make()->success()->title('Внутренний заказ подтверждён целиком')->send();
            });
    }

    public static function complete(): Action
    {
        return Action::make('completeInternalOrder')->label('Закрыть успешно')
            ->icon('heroicon-o-check-circle')->color('success')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => $record->is_internal && $record->dealIsActive())
            ->requiresConfirmation()->modalHeading('Успешно закрыть внутренний заказ?')
            ->modalDescription('Подтвердите, что внутренний заказ выполнен. Статус оплаты и документы в 1С не изменятся.')
            ->modalSubmitActionLabel('Закрыть успешно')
            ->action(function (Order $record): void {
                app(InternalOrderManager::class)->complete($record, auth()->user());
                $record->refresh();
                Notification::make()->success()->title('Внутренний заказ успешно закрыт')->send();
            });
    }
}
