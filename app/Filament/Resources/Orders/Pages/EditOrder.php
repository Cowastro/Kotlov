<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('closeAsIrrelevant')
                ->label('Отказ / неактуален')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->record->canBeClosedAsIrrelevant())
                ->modalHeading('Закрыть заказ без удаления?')
                ->modalDescription('Заказ получит статус «Отменён» и попадёт в архив. Причина, автор и время сохранятся в истории.')
                ->schema([
                    Select::make('reason_type')
                        ->label('Основание')
                        ->options(Order::CLOSURE_REASONS)
                        ->native(false)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Конкретная причина')
                        ->placeholder('Например: клиент купил товар в другом месте.')
                        ->rows(3)
                        ->minLength(3)
                        ->maxLength(1000)
                        ->required(),
                ])
                ->modalSubmitActionLabel('Закрыть заказ')
                ->action(function (array $data): void {
                    $this->record->markIrrelevant($data['reason'], $data['reason_type']);

                    Notification::make()
                        ->success()
                        ->title('Заказ закрыт и перемещён в архив')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('closeAfterDeliveryRefusal')
                ->label('Отказ при доставке')
                ->icon('heroicon-o-truck')
                ->color('danger')
                ->visible(fn (): bool => $this->record->canBeClosedAfterDeliveryRefusal())
                ->modalHeading('Клиент отказался принять заказ?')
                ->modalDescription('Заказ получит статус «Отменён» и попадёт в архив. Причина, сотрудник и время сохранятся в истории.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Причина отказа при доставке')
                        ->placeholder('Например: клиент отказался принимать товар при получении.')
                        ->rows(3)
                        ->minLength(3)
                        ->maxLength(1000)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalSubmitActionLabel('Зафиксировать отказ')
                ->action(function (array $data): void {
                    $this->record->markDeliveryRefused($data['reason']);

                    Notification::make()
                        ->success()
                        ->title('Отказ при доставке сохранён')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }

    protected function afterSave(): void
    {
        $subtotal = round((float) $this->record->items()->sum('total'), 2);
        $discount = max(0, (float) $this->record->discount);
        $deliveryPrice = max(0, (float) $this->record->delivery_price);

        $this->record->forceFill([
            'subtotal' => $subtotal,
            'total' => round(max(0, $subtotal - $discount) + $deliveryPrice, 2),
        ])->save();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['corrected_at'] = now();
        $data['corrected_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
