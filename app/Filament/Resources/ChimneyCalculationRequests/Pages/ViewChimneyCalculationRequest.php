<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Pages;

use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use App\Services\ChimneyCalculationRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewChimneyCalculationRequest extends ViewRecord
{
    protected static string $resource = ChimneyCalculationRequestResource::class;

    public function getTitle(): string
    {
        return 'Расчёт '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Подтвердить и создать заказ')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->action(function (ChimneyCalculationRequestService $service): void {
                    $user = auth()->user();
                    $result = $service->confirm($this->record, $user?->name ?? 'Менеджер CRM', $user);
                    $this->record->refresh();
                    Notification::make()->title('Заказ '.$result['order']->number.' создан')->success()->send();
                }),
            Action::make('openOrder')
                ->label('Открыть заказ')
                ->icon('heroicon-o-shopping-cart')
                ->visible(fn (): bool => (bool) $this->record->order_id)
                ->url(fn (): string => url('/admin/orders/'.$this->record->order_id)),
        ];
    }
}
