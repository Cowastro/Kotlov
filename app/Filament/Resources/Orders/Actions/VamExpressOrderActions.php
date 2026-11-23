<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use App\Models\Order;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressDrafts;
use App\Services\Shipping\VamExpressOrderDelivery;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class VamExpressOrderActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('selectVamDelivery')->label('Выбрать доставку / привязать заявку')->icon('heroicon-o-link')
                ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
                ->modalHeading('Доставка ВАМ Экспресс')->modalSubmitActionLabel('Сохранить доставку')
                ->modalDescription('Сохранится способ доставки ВАМ Экспресс. Можно привязать уже созданную заявку. Статусы обновятся при следующей синхронизации; сумма заказа не меняется.')
                ->fillForm(fn (Order $record): array => ['shipment_id' => VamExpressShipment::where('order_id', $record->id)->value('id')])
                ->schema([
                    Select::make('shipment_id')->label('Существующая заявка ВАМ')->searchable()->native(false)
                        ->placeholder('Заявки ещё нет — сохраните доставку и откройте «Черновик ВАМ»')
                        ->helperText('Поиск по ID ВАМ, городу или профилю отправителя. Заявки других заказов не показываются.')
                        ->getSearchResultsUsing(fn (string $search, Order $record): array => VamExpressShipment::where(fn ($q) => $q->whereNull('order_id')->orWhere('order_id', $record->id))
                            ->where(fn ($q) => $q->where('display_id', 'like', '%'.$search.'%')->orWhere('remote_id', 'like', '%'.$search.'%')->orWhere('city', 'like', '%'.$search.'%')->orWhere('sender_profile', 'like', '%'.$search.'%'))
                            ->latest('remote_created_at')->limit(20)->get()->mapWithKeys(fn ($s) => [$s->id => self::label($s)])->all())
                        ->getOptionLabelUsing(fn ($value, Order $record): ?string => ($s = VamExpressShipment::where(fn ($q) => $q->whereNull('order_id')->orWhere('order_id', $record->id))->find($value)) ? self::label($s) : null),
                ])->action(function (Order $record, array $data, $livewire): void {
                    app(VamExpressOrderDelivery::class)->select($record, auth()->user(), filled($data['shipment_id'] ?? null) ? (int) $data['shipment_id'] : null);
                    $record->refresh();
                    Notification::make()->success()->title('Выбрана доставка ВАМ Экспресс')->send();
                    $livewire->dispatch('$refresh');
                }),
            Action::make('vamExpressDraft')->label('Черновик ВАМ')->icon('heroicon-o-document-plus')
                ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
                ->action(function (Order $record, $livewire): void {
                    app(VamExpressOrderDelivery::class)->select($record, auth()->user());
                    $draft = app(VamExpressDrafts::class)->fromOrder($record->fresh(), auth()->user());
                    $livewire->redirect(VamExpressDraftResource::getUrl('edit', ['record' => $draft]));
                }),
            Action::make('vamExpressShipments')->label('Отправления заказа')->icon('heroicon-o-truck')
                ->url(fn (Order $record): string => VamExpressShipmentResource::getUrl('index', ['tableSearch' => $record->number])),
            Action::make('vamExpressLabels')->label('Этикетки ВАМ')->icon('heroicon-o-printer')
                ->visible(fn (Order $record): bool => VamExpressShipment::where('order_id', $record->id)->exists())
                ->url(fn (Order $record): string => route('admin.orders.vam-express-labels', ['order' => $record->id]))->openUrlInNewTab(),
        ])->label('ВАМ Экспресс')->icon('heroicon-o-truck')->color('gray')->button();
    }

    private static function label(VamExpressShipment $shipment): string
    {
        return implode(' · ', array_filter([$shipment->display_id, $shipment->city, $shipment->sender_profile, $shipment->carrier_status]));
    }
}
