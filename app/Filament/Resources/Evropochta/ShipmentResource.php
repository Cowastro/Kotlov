<?php

namespace App\Filament\Resources\Evropochta;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
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

    protected static ?string $pluralModelLabel = 'Отправления Европочты';

    public static function getNavigationGroup(): ?string
    {
        return OrderResource::getNavigationGroup();
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
                TextColumn::make('evropochta_tracking_number')->label('Трек-номер')
                    ->searchable()->placeholder('Ожидает оформления'),
            ])
            ->recordActions([
                Action::make('quoteEvropochta')
                    ->label('Рассчитать пересылку')
                    ->visible(fn (): bool => app(Evropochta::class)->configured())
                    ->authorize(fn (Order $record): bool => OrderResource::canView($record))
                    ->schema(static::parcelFields())
                    ->action(function (array $data): void {
                        try {
                            $price = app(Evropochta::class)->quote($data);
                            Notification::make()
                                ->title('Базовая пересылка: '.number_format($price, 2, ',', ' ').' BYN')
                                ->body('Стоимость доставки в заказе можно изменить в карточке заказа.')
                                ->persistent()->success()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('createEvropochta')
                    ->label('Создать заявку')
                    ->visible(fn (Order $record): bool => app(Evropochta::class)->configured()
                        && blank($record->evropochta_tracking_number))
                    ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
                    ->fillForm(fn (Order $record): array => [
                        'receiver_phone_number' => preg_replace('/\D/', '', (string) $record->customer_phone),
                    ])
                    ->modalDescription('Заявка до отделения без наложенного платежа. Проверьте вес и данные получателя.')
                    ->schema([
                        ...static::parcelFields(),
                        TextInput::make('receiver_surname')->label('Фамилия')->trim()->required()->maxLength(100),
                        TextInput::make('receiver_name')->label('Имя')->trim()->required()->maxLength(100),
                        TextInput::make('receiver_phone_number')->label('Телефон, только цифры')
                            ->required()->regex('/^375\d{9}$/'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $error = DB::transaction(function () use ($record, $data): ?string {
                            $order = static::getEloquentQuery()->lockForUpdate()->find($record->getKey());

                            if (! $order || ! OrderResource::canEdit($order) || filled($order->evropochta_tracking_number)) {
                                return 'Заказ уже имеет трек-номер или больше недоступен для отправки.';
                            }

                            try {
                                $number = app(Evropochta::class)->create($data + [
                                    'external_id' => 'kotlov-order-'.$order->getKey(),
                                ]);
                            } catch (RuntimeException $exception) {
                                return $exception->getMessage();
                            }

                            $order->evropochta_tracking_number = $number;
                            $order->save();

                            return null;
                        });

                        if ($error !== null) {
                            Notification::make()->title($error)->persistent()->danger()->send();

                            return;
                        }

                        Notification::make()->title('Заявка создана, трек-номер сохранён')->success()->send();
                    }),
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

    private static function parcelFields(): array
    {
        return [
            TextInput::make('store_id_start')->label('ID отделения отправления')->required()->integer()->minValue(1),
            TextInput::make('store_id_finish')->label('ID отделения выдачи')->required()->integer()->minValue(1),
            TextInput::make('weight')->label('Вес упакованной посылки, кг')
                ->required()->numeric()->minValue(0.01)->step(0.01)->rule('decimal:0,2'),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListShipments::route('/')];
    }
}
