<?php

namespace App\Filament\Resources\Evropochta;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Filament\Resources\Orders\Actions\EvropochtaOrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShipmentResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'evropochta-shipments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Европочта';

    protected static ?string $pluralModelLabel = 'Отправления Европочты';

    public static function getNavigationGroup(): ?string
    {
        return 'Доставки';
    }

    public static function getEloquentQuery(): Builder
    {
        return OrderResource::getEloquentQuery()->where('delivery_type', 'evropochta');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Заказ')->searchable()
                    ->url(fn (Order $record): ?string => OrderResource::canView($record)
                        ? OrderResource::getUrl('view', ['record' => $record])
                        : null),
                TextColumn::make('customer_name')->label('Получатель')->searchable(),
                TextColumn::make('customer_phone')->label('Телефон'),
                TextColumn::make('delivery_city')->label('Город'),
                TextColumn::make('delivery_address')->label('Отделение / адрес')->wrap(),
                TextColumn::make('delivery_price')->label('Доставка в заказе')->money('BYN'),
                TextColumn::make('evropochta_quote.carrier_price')->label('Тариф перевозчика')->money('BYN')->placeholder('—'),
                TextColumn::make('evropochta_quote.parcel.box')->label('Упаковка')
                    ->formatStateUsing(fn (string $state): string => implode(' × ', config("evropochta_packaging.{$state}.outer", [])).' см')
                    ->placeholder('—')->toggleable(),
                TextColumn::make('evropochta_tracking_number')->label('Трек-номер')
                    ->searchable()->placeholder('Ожидает оформления'),
            ])
            ->recordActions([
                EvropochtaOrderActions::track(),
                static::quoteAction()
                    ->authorize(fn (Order $record): bool => OrderResource::canView($record)),
                Action::make('saveTrackingNumber')
                    ->label('Записать трек-номер')
                    ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
                    ->fillForm(fn (Order $record): array => [
                        'tracking_number' => $record->evropochta_tracking_number,
                    ])
                    ->schema([
                        TextInput::make('tracking_number')->label('Трек-номер')
                            ->trim()->required()->maxLength(100),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $saved = DB::transaction(function () use ($record, $data): bool {
                            $order = static::getEloquentQuery()->lockForUpdate()->find($record->getKey());

                            if (! $order || ! OrderResource::canEdit($order)) {
                                return false;
                            }

                            $order->evropochta_tracking_number = $data['tracking_number'];

                            return $order->save();
                        });

                        if ($saved) {
                            Notification::make()->title('Трек-номер сохранён')->success()->send();

                            return;
                        }

                        Notification::make()->title('Заказ больше недоступен для изменения')->danger()->send();
                    }),
            ]);
    }

    public static function quoteAction(): Action
    {
        return Action::make('quoteEvropochta')
            ->label('Рассчитать тариф')
            ->visible(fn (): bool => app(Evropochta::class)->configured())
            ->modalDescription('До отделения, без наложенного платежа. Укажите вес и размеры с упаковкой.')
            ->modalSubmitActionLabel('Рассчитать')
            ->schema(fn (): array => EvropochtaOrderActions::parcelFields())
            ->action(function (array $data, Action $action): void {
                try {
                    $destination = (int) $data['store_id_finish'];
                    unset($data['store_id_finish']);
                    $quote = app(Evropochta::class)->estimate($data, $destination);
                    $tariff = $quote['oversize'] ? 'Громоздкая посылка' : 'Стандартная посылка';

                    Notification::make()
                        ->title('Доставка для клиента — '.number_format($quote['customer_price'], 0, ',', ' ').' BYN')
                        ->body($tariff.' · тариф Европочты '.number_format($quote['carrier_price'], 2, ',', ' ').' BYN')
                        ->persistent()->success()->send();
                } catch (RuntimeException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->persistent()->send();
                    $action->halt();
                }
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListShipments::route('/')];
    }
}
