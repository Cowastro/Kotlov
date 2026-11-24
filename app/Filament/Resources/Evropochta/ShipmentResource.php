<?php

namespace App\Filament\Resources\Evropochta;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Filament\Resources\Orders\Actions\EvropochtaOrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaShipmentSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\SelectFilter;
use App\Services\Shipping\EvropochtaPayment;
use App\Services\Shipping\EvropochtaShipmentManagement;
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

    public static function getNavigationItems(): array
    {
        return array_map(fn ($item) => $item->isActiveWhen(fn (): bool => request()->routeIs(
            static::getRouteBaseName().'.*',
            PackagingResource::getRouteBaseName().'.*',
        )), parent::getNavigationItems());
    }

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
                TextColumn::make('customer_name')->label('Получатель')->searchable()
                    ->description(fn (Order $record): ?string => $record->customer_phone),
                TextColumn::make('shipment_status')->label('Статус Европочты')->badge()
                    ->state(fn (Order $record): string => EvropochtaShipmentSummary::status($record))
                    ->color(fn (Order $record): string => EvropochtaShipmentSummary::color($record))
                    ->description(fn (Order $record): ?string => $record->carrier_reference === $record->evropochta_tracking_number
                        && $record->carrier_checked_at ? 'Проверено '.$record->carrier_checked_at->format('d.m H:i') : null),
                TextColumn::make('cod_amount')->label('Наложенный платёж')->money('BYN')
                    ->state(fn (Order $record): ?float => EvropochtaShipmentSummary::amount($record))
                    ->placeholder('Нет данных')->description(fn (Order $record): string => EvropochtaShipmentSummary::source($record)),
                TextColumn::make('shipment_payer')->label('Доставку оплачивает')
                    ->state(fn (Order $record): string => EvropochtaShipmentSummary::payer($record, 'shipment_payer')),
                TextColumn::make('cod_payer')->label('Комиссию наложенного платежа')
                    ->state(fn (Order $record): string => EvropochtaShipmentSummary::amount($record) === 0.0
                        ? 'Нет наложенного платежа' : EvropochtaShipmentSummary::payer($record, 'cash_on_delivery_payer'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_city')->label('Город')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_address')->label('Отделение / адрес')->wrap(),
                TextColumn::make('delivery_price')->label('Доставка в заказе')->money('BYN')->toggleable(),
                TextColumn::make('evropochta_quote.carrier_price')->label('Тариф перевозчика')->money('BYN')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('evropochta_quote.parcel.box')->label('Упаковка')
                    ->formatStateUsing(fn (string $state): string => implode(' × ', config("evropochta_packaging.{$state}.outer", [])).' см')
                    ->placeholder('—')->toggleable(),
                TextColumn::make('evropochta_tracking_number')->label('Трек-номер')
                    ->searchable()->placeholder('Ожидает оформления'),
            ])
            ->filters([
                SelectFilter::make('shipment_stage')->label('Этап отправления')->options([
                    'not_created' => 'Не оформлено', 'unverified' => 'Статус не получен', 'created' => 'Создано, не передано',
                    'accepted' => 'Принято Европочтой', 'transit' => 'В пути', 'ready' => 'В отделении получателя',
                    'delivered' => 'Вручено', 'attention' => 'Отмена / возврат / проблема',
                ])->query(function (Builder $query, array $data): Builder {
                    $stage = $data['value'] ?? null;
                    if (! $stage) return $query;
                    if ($stage === 'not_created') return $query->whereNull('evropochta_tracking_number');
                    if ($stage === 'unverified') return $query->whereNotNull('evropochta_tracking_number')->whereNull('carrier_checked_at');
                    if ($stage === 'created') return $query->whereNotNull('evropochta_tracking_number')
                        ->where(fn (Builder $q): Builder => $q->where('carrier_stage', 'created')->orWhere(fn (Builder $q): Builder =>
                            $q->where('evropochta_quote->shipment->status', 'Заявка создана')
                                ->whereColumn('evropochta_quote->shipment->number', 'evropochta_tracking_number')));
                    return $query->where('carrier_provider', 'evropochta')->where('carrier_stage', $stage);
                }),
                SelectFilter::make('payment_status')->label('Оплата заказа')->options(['pending' => 'Ожидает оплаты', 'paid' => 'Оплачен', 'refunded' => 'Возврат оплаты']),
            ])->deferFilters(false)->persistFiltersInSession()
            ->recordActions([
                ActionGroup::make([
                    EvropochtaOrderActions::prepare(),
                    EvropochtaOrderActions::create(),
                    static::refreshAction(),
                    EvropochtaOrderActions::track(),
                    EvropochtaOrderActions::printLabel('thermal'),
                    EvropochtaOrderActions::printLabel('address'),
                    static::paymentAction(),
                    static::cancelAction(),
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
                ])->label('Управление')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),
            ]);
    }

    public static function refreshAction(): Action
    {
        return Action::make('refreshEvropochta')->label('Проверить статус')->icon('heroicon-o-arrow-path')
            ->authorize(fn (Order $record): bool => OrderResource::canView($record))
            ->visible(fn (Order $record): bool => filled($record->evropochta_tracking_number))
            ->disabled(fn (): bool => ! app(Evropochta::class)->configured())
            ->action(function (Order $record, Action $action): void {
                try {
                    $status = app(EvropochtaShipmentManagement::class)->refresh($record, auth()->user());
                    Notification::make()->success()->title($status)->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function paymentAction(): Action
    {
        return Action::make('updateEvropochtaPayment')->label('Установить сумму к получению')->icon('heroicon-o-currency-dollar')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => filled($record->evropochta_tracking_number)
                && ! in_array($record->status, ['cancelled', 'delivered'], true) && ! $record->archived_at)
            ->disabled(fn (Order $record): bool => ! app(Evropochta::class)->configured() || EvropochtaPayment::blocker($record) !== null)
            ->tooltip(fn (Order $record): ?string => EvropochtaPayment::blocker($record))
            ->modalDescription('Европочта получит одну итоговую сумму. Доставка и комиссия — за счёт нашего магазина. Изменение возможно, пока посылка не принята Европочтой.')
            ->modalSubmitActionLabel('Сохранить в Европочте')
            ->fillForm(fn (Order $record): array => ['payment_review_signature' => EvropochtaPayment::signature($record->fresh())])
            ->schema(EvropochtaOrderActions::paymentFields())
            ->action(function (Order $record, array $data, Action $action): void {
                try {
                    app(EvropochtaShipmentManagement::class)->changePayment($record, auth()->user(), $data['payment_review_signature'] ?? '');
                    $record->refresh();
                    Notification::make()->success()->title('Европочта подтвердила сумму. Доставка и комиссия — за наш счёт.')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancelEvropochta')->label('Отменить отправление')->icon('heroicon-o-x-circle')->color('danger')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => filled($record->evropochta_tracking_number)
                && ! in_array($record->status, ['cancelled', 'delivered'], true) && ! $record->archived_at)
            ->disabled(fn (): bool => ! app(Evropochta::class)->configured())
            ->requiresConfirmation()->modalDescription('Отменится только заявка Европочты, сам заказ останется. Доступно только до приёма посылки перевозчиком.')
            ->action(function (Order $record, Action $action): void {
                try {
                    app(EvropochtaShipmentManagement::class)->cancel($record, auth()->user());
                    $record->refresh();
                    Notification::make()->success()->title('Отправление отменено в Европочте')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
            });
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
