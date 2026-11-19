<?php

namespace App\Filament\Resources\Evropochta;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
                TextColumn::make('evropochta_tracking_number')->label('Трек-номер')
                    ->searchable()->placeholder('Ожидает оформления'),
            ])
            ->recordActions([
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
            ->schema(function (): array {
                try {
                    $branches = collect(app(Evropochta::class)->branches())->map(fn (array $branch): string => $branch['label'])->all();
                } catch (RuntimeException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->persistent()->send();

                    $branches = [];
                }

                return [
                    Select::make('store_id_start')->label('Отделение отправления')
                        ->options($branches)->searchable()->required()->placeholder('Город или адрес'),
                    Select::make('store_id_finish')->label('Отделение получения')
                        ->options($branches)->searchable()->required()->placeholder('Город или адрес'),
                    TextInput::make('weight')->label('Вес, кг')->required()->numeric()
                        ->minValue(0.01)->maxValue(50)->step(0.01)->rule('decimal:0,2'),
                    TextInput::make('length')->label('Длина, см')->required()->numeric()
                        ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
                    TextInput::make('width')->label('Ширина, см')->required()->numeric()
                        ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
                    TextInput::make('height')->label('Высота, см')->required()->numeric()
                        ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
                ];
            })
            ->action(function (array $data, Action $action): void {
                try {
                    $quote = app(Evropochta::class)->quote($data);
                    $tariff = $quote['oversize'] ? 'Громоздкая посылка' : 'Стандартная посылка';

                    Notification::make()
                        ->title('Тариф Европочты — '.number_format($quote['total'], 2, ',', ' ').' BYN')
                        ->body($tariff.' · отделение → отделение')
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
