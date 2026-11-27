<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderDeliveryPreparation;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class PrepareOrderDeliveryAction
{
    public static function make(): Action
    {
        $preparation = app(OrderDeliveryPreparation::class);

        return Action::make('prepareOrderDelivery')
            ->label(fn (Order $record): string => ($preparation->blocker($record) === null ? 'Изменить ' : 'Подготовить ')
                .$preparation->handoverLabel($record))
            ->icon('heroicon-o-truck')
            ->color(fn (Order $record): string => app(OrderDeliveryPreparation::class)->blocker($record) === null ? 'gray' : 'primary')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => $record->status === 'processing' && blank($record->onec_order_guid))
            ->modalHeading(fn (Order $record): string => 'Согласовать '.$preparation->handoverLabel($record).' перед оформлением в 1С')
            ->modalDescription(fn (Order $record): string => match ($record->delivery_type) {
                'pickup' => 'Укажите, откуда забрать товар и где выдать его покупателю.',
                'evropochta' => 'Оформите трек Европочты и укажите склад отправления и отделение получения.',
                'vam_express' => 'Привяжите заявку ВАМ Экспресс и укажите адреса забора и доставки.',
                default => 'Укажите адреса забора и доставки заказа.',
            })
            ->modalSubmitActionLabel('Сохранить согласование')
            ->fillForm(fn (Order $record): array => [
                'method' => $record->delivery_type, 'from' => $record->delivery_preparation['from'] ?? null,
                'to' => $record->delivery_preparation['to'] ?? ($record->delivery_type === 'pickup' ? null
                    : collect([$record->delivery_city, $record->delivery_address])->filter()->implode(', ')),
                'date' => $record->planned_delivery_date, 'note' => $record->delivery_preparation['note'] ?? null,
            ])
            ->schema([
                Select::make('method')->label('Как передаём заказ')->options(Order::DELIVERY_TYPES)->required(),
                Textarea::make('from')->label('Откуда забрать товар')->rows(2)->maxLength(1000)->required(),
                Textarea::make('to')->label('Куда доставить / где выдать самовывоз')->rows(2)->maxLength(1000)->required(),
                DatePicker::make('date')->label('Согласованная дата')->required(),
                Textarea::make('note')->label('Указания по забору, закупке и передаче')->rows(2)->maxLength(2000),
            ])->action(function (Order $record, array $data, $livewire): void {
                try {
                    app(OrderDeliveryPreparation::class)->confirm($record, auth()->user(), $data);
                    $record->refresh();
                    Notification::make()->success()->title($record->delivery_type === 'pickup'
                        ? 'Самовывоз согласован — можно оформить заказ в 1С'
                        : 'Доставка согласована — можно оформить заказ в 1С')->send();
                    $livewire->dispatch('$refresh');
                } catch (DomainException $exception) {
                    Notification::make()->warning()->title('Подготовка не завершена')->body($exception->getMessage())->send();
                }
            });
    }
}
