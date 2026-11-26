<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Filament\Resources\Orders\Actions\FindOrderItemAnalogAction;
use App\Filament\Resources\Products\Actions\ProductOneCItemAction;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Livewire\EvropochtaOrderStatus;
use App\Models\IntegrationIssue;
use App\Models\OneCItem;
use App\Models\Order;
use App\Models\OrderAccountingRequest;
use App\Models\OrderEconomicSnapshot;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\VamExpressShipment;
use App\Services\Crm\BelarusTaxpayerRegistry;
use App\Services\Crm\CrmCounterpartyFromTaxpayerLookup;
use App\Services\Integrations\OneCBridge\ItemTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use App\Services\Integrations\OneCBridge\OneCOrderJourney;
use App\Services\Integrations\OneCBridge\OneCOrderMatcher;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCStock;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Orders\OrderDeliveryPreparation;
use App\Services\Orders\OrderItemAvailabilityChecker;
use App\Services\Orders\OrderItemAvailabilityDisplay;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderSettlementManager;
use App\Services\Orders\SupplierOrderRequestBuilder;
use App\Services\Orders\SupplierReservationMailService;
use App\Services\Shipping\EvropochtaRecipient;
use App\Support\AdminPhoneLinks;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $paymentNames = collect(config('shop.payment_methods', []))->mapWithKeys(fn ($m, $k) => [$k => $m['name']])->toArray();
        $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn ($m, $k) => [$k => $m['name']])->toArray();

        $byn = fn ($state) => number_format((float) $state, 2, '.', ' ').' BYN';

        $statusColor = fn (?string $state) => match ($state) {
            'new' => 'info',
            'confirmed' => 'success',
            'processing' => 'warning',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };

        return $schema
            ->columns(['default' => 1, '2xl' => 3])
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(['default' => 2, 'md' => 4, 'xl' => 8])
                    ->compact()
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер заказа')
                            ->helperText(fn (Order $record): ?string => $record->is_internal ? 'Внутренний заказ' : null)
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color($statusColor)
                            ->formatStateUsing(fn (string $state) => Order::STATUSES[$state] ?? $state),

                        TextEntry::make('onec_state')
                            ->label('Заказ в 1С')
                            ->badge()
                            ->state(fn (Order $record): string => self::onecState($record)[0])
                            ->color(fn (Order $record): string => self::onecState($record)[1])
                            ->helperText(fn (Order $record): ?string => self::onecState($record)[2]),

                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('corrected_at')
                            ->label('Скорректирован')
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn (Order $record): bool => $record->corrected_at !== null)
                            ->helperText(fn (Order $record): ?string => $record->correctedBy?->name),

                        TextEntry::make('items_count')
                            ->label('Позиций')
                            ->getStateUsing(fn ($record) => $record->items->count().' шт.'),

                        TextEntry::make('total')
                            ->label('Итого')
                            ->weight('bold')
                            ->color('primary')
                            ->formatStateUsing($byn),

                        TextEntry::make('payment_status')
                            ->label('Статус оплаты')
                            ->badge()
                            ->color(fn (Order $record): string => $record->paymentStatusColor())
                            ->formatStateUsing(fn (?string $state, Order $record): string => $record->paymentStatusLabel()),

                        TextEntry::make('manager.name')
                            ->label('Ответственный')
                            ->placeholder('Не назначен')
                            ->icon('heroicon-o-user-circle')
                            ->iconColor(fn ($state) => $state ? 'success' : 'gray')
                            ->color(fn ($state) => $state ? 'success' : 'gray')
                            ->getStateUsing(fn ($record) => $record->manager
                                ? $record->manager->name.($record->assigned_to ? ' ('.$record->assigned_to.')' : '')
                                : ($record->assigned_to ?? null)),
                    ]),

                Section::make('1С: что сделано и что дальше')
                    ->icon('heroicon-o-arrows-right-left')
                    ->columnSpanFull()
                    ->compact()
                    ->visible(fn (Order $record): bool => (bool) auth()->user()?->isManager() && app(OneCItemCreator::class)->source() !== null
                        && $record->status !== 'cancelled')
                    ->schema([
                        TextEntry::make('onec_journey')->hiddenLabel()
                            ->state(fn (Order $record): HtmlString => app(OneCOrderJourney::class)->render($record)),
                    ]),

                Group::make([
                    Section::make('Ход заказа')
                        ->icon('heroicon-o-forward')
                        ->columnSpanFull()
                        ->compact()
                        ->schema([
                            ViewEntry::make('deal_progress')->hiddenLabel()
                                ->view('filament.resources.orders.deal-progress')
                                ->viewData(['isTable' => false]),
                        ]),

                    Section::make('Товары заказа')
                        ->icon('heroicon-o-shopping-bag')
                        ->columnSpanFull()
                        ->compact()
                        ->schema([
                            RepeatableEntry::make('items')
                                ->hiddenLabel()
                                ->columns(['default' => 2, 'lg' => 12])
                                ->schema([
                                    TextEntry::make('product_name')
                                        ->label('Товар')
                                        ->columnSpan(['default' => 2, 'lg' => 3])
                                        ->url(fn (OrderItem $record): ?string => $record->publicProductUrl())
                                        ->openUrlInNewTab()
                                        ->suffixActions([
                                            FindOrderItemAnalogAction::forItem(),
                                            Action::make('openPublicProduct')
                                                ->label('Открыть товар на сайте')
                                                ->tooltip('Открыть товар на сайте')
                                                ->icon('heroicon-o-arrow-top-right-on-square')
                                                ->iconButton()
                                                ->color('info')
                                                ->visible(fn (OrderItem $record): bool => $record->publicProductUrl() !== null)
                                                ->url(fn (OrderItem $record): ?string => $record->publicProductUrl())
                                                ->openUrlInNewTab(),
                                        ]),

                                    TextEntry::make('product_sku')
                                        ->label('Артикул')
                                        ->columnSpan(2)
                                        ->placeholder('—')
                                        ->fontFamily('mono')
                                        ->copyable()
                                        // The card's 1C item: its code, or «нет в 1С» with linking / creating right here.
                                        ->helperText(fn (OrderItem $record): ?HtmlString => self::onecItemLine($record))
                                        ->suffixAction(
                                            ProductOneCItemAction::make('orderItemOneC', fn (OrderItem $record): ?Product => $record->product)
                                                ->iconButton()
                                                ->icon(fn (OrderItem $record): string => $record->product && app(OneCItemCreator::class)->linked($record->product)
                                                    ? 'heroicon-o-check-badge' : 'heroicon-o-plus-circle')
                                                ->color(fn (OrderItem $record): string => $record->product && app(OneCItemCreator::class)->linked($record->product) ? 'success' : 'warning')
                                                ->tooltip(fn (OrderItem $record): string => $record->product && app(OneCItemCreator::class)->linked($record->product)
                                                    ? 'Товар 1С' : 'Связать с товаром 1С или создать в 1С')
                                                ->visible(fn (OrderItem $record): bool => (bool) auth()->user()?->isManager() && $record->product !== null
                                                    && app(OneCItemCreator::class)->source() !== null)
                                        ),

                                    TextEntry::make('quantity')
                                        ->label('Кол-во')
                                        ->suffix(' шт.'),

                                    TextEntry::make('price')
                                        ->label('Продажа')
                                        ->columnSpan(2)
                                        ->weight('bold')
                                        ->color('primary')
                                        ->formatStateUsing(fn ($state, $record): string => $byn($state)
                                            .((int) $record->quantity > 1 ? ' × '.(int) $record->quantity.' = '.$byn($record->total) : '')),

                                    TextEntry::make('supply_supplier')
                                        ->label('Поставщик')
                                        ->columnSpan(2)
                                        ->state(fn (OrderItem $record): ?string => $record->fulfillment_supplier_name
                                            ?: $record->fulfillmentSupplier?->name
                                            ?: data_get($record->current_availability, 'supplier_name')
                                            ?: $record->supplyContext()['supplier_name'])
                                        ->placeholder('Не определён')
                                        ->helperText(function (OrderItem $record): ?string {
                                            $recommended = data_get($record->current_availability, 'supplier_name')
                                                ?: $record->supplyContext()['supplier_name'];
                                            $assigned = $record->fulfillment_supplier_name ?: $record->fulfillmentSupplier?->name;

                                            return $assigned && $recommended && $assigned !== $recommended
                                                ? 'Рекомендация базы: '.$recommended
                                                : null;
                                        })
                                        ->weight('bold')
                                        ->suffixAction(
                                            Action::make('assignItemSupplier')
                                                ->label('Назначить')
                                                ->icon('heroicon-o-arrows-right-left')
                                                ->color('warning')
                                                ->tooltip('Назначить или сменить фактического поставщика')
                                                ->modalHeading(fn (OrderItem $record): string => 'Поставщик: '.$record->product_name)
                                                ->modalDescription('Рекомендация из базы сохранится для сравнения. Здесь фиксируется фактический поставщик, наличие или резерв и решение менеджера.')
                                                ->modalSubmitActionLabel('Подтвердить')
                                                ->fillForm(function (OrderItem $record): array {
                                                    $recommendedRoute = $record->supplyContext()['status'];
                                                    $route = $record->fulfillment_route
                                                        ?: (array_key_exists($recommendedRoute, OrderItem::FULFILLMENT_ROUTES) ? $recommendedRoute : 'supplier_purchase');
                                                    $supplierId = $record->fulfillment_supplier_id
                                                        ?: data_get($record->current_availability, 'supplier_id')
                                                        ?: $record->supply_supplier_id;
                                                    $supplier = $supplierId
                                                        ? Supplier::query()->with('crmCounterparty.contactPoints')->find($supplierId)
                                                        : null;

                                                    return [
                                                        'fulfillment_route' => $route,
                                                        'supplier_id' => $supplierId,
                                                        'purchase_price' => self::assignmentPrice($record, $route),
                                                        'availability_status' => $record->fulfillment_availability_status,
                                                        'request_reserve_now' => true,
                                                        'reservation_quantity' => $record->quantity,
                                                        'reservation_recipient' => $supplier?->contactEmailAddresses()[0] ?? null,
                                                        'note' => null,
                                                    ];
                                                })
                                                ->schema([
                                                    Select::make('fulfillment_route')
                                                        ->label('Как исполняем')
                                                        ->options(OrderItem::FULFILLMENT_ROUTES)
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(fn (OrderItem $record, $state, Set $set) => $set('purchase_price', self::assignmentPrice($record, $state))),
                                                    Select::make('supplier_id')
                                                        ->label('Фактический поставщик')
                                                        ->options(fn (): array => Supplier::query()
                                                            ->where('is_active', true)
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id')
                                                            ->all())
                                                        ->searchable()
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, Set $set): void {
                                                            $supplier = $state
                                                                ? Supplier::query()->with('crmCounterparty.contactPoints')->find($state)
                                                                : null;
                                                            $set('reservation_recipient', $supplier?->contactEmailAddresses()[0] ?? null);
                                                        })
                                                        ->createOptionForm(self::newSupplierForm())
                                                        ->createOptionAction(fn (Action $action): Action => $action
                                                            ->label('Добавить поставщика')
                                                            ->modalHeading('Новый поставщик по УНП')
                                                            ->modalDescription('Найдите реквизиты в CRM / 1С и реестре МНС, проверьте их и сохраните. Новый поставщик сразу выберется для позиции.')
                                                            ->modalSubmitActionLabel('Создать и выбрать'))
                                                        ->createOptionUsing(fn (array $data): int => self::createSupplier($data))
                                                        ->helperText('Можно выбрать любого активного поставщика, даже если товар первоначально был подобран у другого.'),
                                                    Select::make('availability_status')
                                                        ->label('Решение по наличию')
                                                        ->options(OrderItem::FULFILLMENT_AVAILABILITY_STATUSES)
                                                        ->required(),
                                                    TextInput::make('purchase_price')
                                                        ->label(fn (Get $get): string => $get('fulfillment_route') === 'own_stock'
                                                            ? 'Входная цена с НДС за единицу, BYN'
                                                            : 'Подтверждённая входная цена за единицу, BYN')
                                                        ->numeric()
                                                        ->minValue(0),
                                                    Checkbox::make('request_reserve_now')
                                                        ->label('Сразу отправить поставщику запрос наличия и резерва')
                                                        ->helperText('Письмо уйдёт с order@kotlov.by, а заявка и переписка сохранятся в заказе.')
                                                        ->visible(fn (Get $get): bool => in_array($get('fulfillment_route'), ['supplier_purchase', 'direct_supplier'], true))
                                                        ->live(),
                                                    TextInput::make('reservation_quantity')
                                                        ->label('Количество для закупки / резерва')
                                                        ->numeric()
                                                        ->integer()
                                                        ->minValue(1)
                                                        ->required(fn (Get $get): bool => (bool) $get('request_reserve_now')
                                                            && in_array($get('fulfillment_route'), ['supplier_purchase', 'direct_supplier'], true))
                                                        ->visible(fn (Get $get): bool => (bool) $get('request_reserve_now')
                                                            && in_array($get('fulfillment_route'), ['supplier_purchase', 'direct_supplier'], true))
                                                        ->helperText(fn (OrderItem $record): string => 'В заказе клиента '.$record->quantity.' шт. Можно запросить больше для пополнения склада.'),
                                                    TextInput::make('reservation_recipient')
                                                        ->label('Email поставщика')
                                                        ->email()
                                                        ->required(fn (Get $get): bool => (bool) $get('request_reserve_now')
                                                            && in_array($get('fulfillment_route'), ['supplier_purchase', 'direct_supplier'], true))
                                                        ->visible(fn (Get $get): bool => (bool) $get('request_reserve_now')
                                                            && in_array($get('fulfillment_route'), ['supplier_purchase', 'direct_supplier'], true)),
                                                    Textarea::make('note')
                                                        ->label('Комментарий менеджера')
                                                        ->rows(3)
                                                        ->maxLength(2000),
                                                ])
                                                ->action(function (OrderItem $record, array $data): void {
                                                    $supplier = filled($data['supplier_id'] ?? null)
                                                        ? Supplier::query()->with('crmCounterparty.contactPoints')->findOrFail($data['supplier_id'])
                                                        : null;
                                                    $sendReserve = (bool) ($data['request_reserve_now'] ?? false)
                                                        && in_array($data['fulfillment_route'], ['supplier_purchase', 'direct_supplier'], true);

                                                    if ($sendReserve) {
                                                        $existing = $record->order->supplierOrderRequests()
                                                            ->where('supplier_id', $supplier?->id)
                                                            ->where('route', $data['fulfillment_route'])
                                                            ->whereNotIn('status', ['draft', 'cancelled'])
                                                            ->first();
                                                        if ($existing) {
                                                            throw ValidationException::withMessages([
                                                                'reservation_recipient' => 'Запрос этому поставщику уже отправляли: '.$existing->number.'.',
                                                            ]);
                                                        }
                                                    }

                                                    app(OrderItemFulfillmentManager::class)->confirm(
                                                        $record,
                                                        $data['fulfillment_route'],
                                                        $supplier,
                                                        auth()->user(),
                                                        $data['note'] ?? null,
                                                        filled($data['purchase_price'] ?? null) ? (float) $data['purchase_price'] : null,
                                                        $data['availability_status'],
                                                        useOwnStockSnapshot: false,
                                                    );

                                                    if ($sendReserve) {
                                                        try {
                                                            $order = Order::query()->findOrFail($record->order_id);
                                                            $reserveQuantity = max(1, (int) ($data['reservation_quantity'] ?? $record->quantity));
                                                            $request = app(SupplierOrderRequestBuilder::class)
                                                                ->buildDrafts($order, auth()->user(), $supplier->id, $data['fulfillment_route'])
                                                                ->firstOrFail();
                                                            $requestItem = $request->items()->where('order_item_id', $record->id)->firstOrFail();
                                                            $requestItem->forceFill([
                                                                'quantity' => $reserveQuantity,
                                                                'purchase_total' => $requestItem->purchase_price !== null
                                                                    ? (float) $requestItem->purchase_price * $reserveQuantity
                                                                    : null,
                                                            ])->save();
                                                            $request->load('items');
                                                            $request->forceFill([
                                                                'purchase_total' => $request->items->every(fn ($item): bool => $item->purchase_price !== null)
                                                                    ? $request->items->sum(fn ($item): float => (float) $item->purchase_total)
                                                                    : null,
                                                            ])->save();
                                                            app(SupplierReservationMailService::class)->sendOrderRequest(
                                                                $request,
                                                                auth()->user(),
                                                                (string) $data['reservation_recipient'],
                                                                filled($data['note'] ?? null)
                                                                    ? $data['note']
                                                                    : 'Просим подтвердить актуальное наличие указанного количества и поставить товар в резерв.',
                                                            );
                                                            $order->statusHistory()->create([
                                                                'user_id' => auth()->id(),
                                                                'status_from' => $order->status,
                                                                'status_to' => $order->status,
                                                                'comment' => 'Запрос наличия и резерва отправлен поставщику '.$supplier->name
                                                                    .' · '.$record->product_name.' · '.$reserveQuantity.' шт.'
                                                                    .($reserveQuantity !== (int) $record->quantity ? ' (в заказе клиента '.$record->quantity.' шт.)' : '')
                                                                    .' · '.$request->number,
                                                            ]);
                                                        } catch (\Throwable $exception) {
                                                            report($exception);
                                                            Notification::make()
                                                                ->warning()
                                                                ->title('Решение сохранено, но письмо не отправлено')
                                                                ->body($exception->getMessage())
                                                                ->send();

                                                            return;
                                                        }
                                                    }

                                                    Notification::make()
                                                        ->success()
                                                        ->title($sendReserve ? 'Поставщик подтверждён, запрос резерва отправлен' : 'Поставщик и наличие подтверждены')
                                                        ->body(($supplier?->name ?: 'Наш склад').' · '.OrderItem::FULFILLMENT_AVAILABILITY_STATUSES[$data['availability_status']])
                                                        ->send();
                                                }),
                                        ),

                                    TextEntry::make('supply_warehouse_name')
                                        ->label('Склад отгрузки')
                                        ->columnSpan(2)
                                        ->placeholder('Не выбран')
                                        ->helperText(fn (OrderItem $record): ?string => $record->supply_delivery_days
                                            ? 'Ориентировочная доставка: до '.$record->supply_delivery_days.' дней'
                                            : null)
                                        ->visible(fn (OrderItem $record): bool => filled($record->supply_warehouse_name)),

                                    TextEntry::make('supply_stock_summary')
                                        ->label('Наличие')
                                        ->columnSpan(2)
                                        ->state(fn (OrderItem $record): string => app(OrderItemAvailabilityDisplay::class)->label($record))
                                        ->helperText(fn (OrderItem $record): string => app(OrderItemAvailabilityDisplay::class)->details($record))
                                        ->color(fn (OrderItem $record): string => app(OrderItemAvailabilityDisplay::class)->color($record))
                                        ->tooltip(fn (OrderItem $record): ?string => app(OrderItemAvailabilityDisplay::class)->history($record))
                                        ->weight('bold')
                                        ->suffixAction(
                                            Action::make('refreshCurrentAvailability')
                                                ->label('Уточнить')
                                                ->icon('heroicon-o-arrow-path')
                                                ->color('info')
                                                ->tooltip('Уточнить актуальное наличие по последнему обновлению базы')
                                                ->action(function (OrderItem $record): void {
                                                    $check = app(OrderItemAvailabilityChecker::class)->check($record, auth()->user());

                                                    Notification::make()
                                                        ->title('Актуальное наличие проверено')
                                                        ->body(collect([
                                                            $check['supplier_name'],
                                                            $check['stock_label'],
                                                        ])->filter()->implode(' · '))
                                                        ->color($check['is_available'] === true ? 'success' : 'warning')
                                                        ->send();
                                                }),
                                        ),

                                    TextEntry::make('fulfillment_result_summary')
                                        ->label('Итог исполнения')
                                        ->columnSpanFull()
                                        ->state(function ($record) use ($byn): string {
                                            if (! $record->fulfillment_route) {
                                                return 'Требует решения: маршрут и входная цена не подтверждены';
                                            }

                                            return collect([
                                                $record->fulfillmentRouteLabel(),
                                                $record->fulfillment_supplier_name
                                                    ?: ($record->fulfillment_route === 'own_stock' ? 'Собственный склад' : null),
                                                $record->fulfillment_purchase_price !== null
                                                    ? 'вход '.$byn($record->fulfillment_purchase_price)
                                                    : 'входная цена не указана',
                                                $record->fulfillmentAvailabilityLabel(),
                                                $record->fulfillment_confirmed_at
                                                    ? 'подтвердил '.($record->fulfillmentConfirmedBy?->name ?? 'система')
                                                        .' · '.$record->fulfillment_confirmed_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
                                                    : null,
                                            ])->filter()->implode(' · ');
                                        })
                                        ->badge()
                                        ->color(fn ($record): string => $record->fulfillment_route ? 'success' : 'warning')
                                        ->icon(fn ($record): string => $record->fulfillment_route
                                            ? 'heroicon-o-check-circle'
                                            : 'heroicon-o-exclamation-triangle'),

                                    Section::make('Закупка и исполнение')
                                        ->icon('heroicon-o-truck')
                                        ->columnSpanFull()
                                        ->compact()
                                        ->collapsible()
                                        ->collapsed()
                                        ->columns(12)
                                        ->schema([
                                            TextEntry::make('supply_contact')
                                                ->label('Контакты поставщика')
                                                ->columnSpan(3)
                                                ->state(fn ($record): ?string => $record->currentSupplyContact())
                                                ->formatStateUsing(fn (?string $state): HtmlString|string => self::contactLinks($state))
                                                ->placeholder('Не заполнены')
                                                ->copyable(),

                                            TextEntry::make('supply_route')
                                                ->label('Маршрут')
                                                ->columnSpan(2)
                                                ->state(fn ($record): string => $record->supplyContext()['route_label'])
                                                ->badge()
                                                ->color(fn ($record): string => match ($record->supplyContext()['status']) {
                                                    'own_stock' => 'success',
                                                    'supplier_purchase' => 'warning',
                                                    default => 'danger',
                                                }),

                                            TextEntry::make('supply_wholesale_price')
                                                ->label('Оптовая / закупочная')
                                                ->columnSpan(2)
                                                ->state(fn ($record): ?float => $record->supplyContext()['wholesale_price'])
                                                ->formatStateUsing(fn ($state): string => $state === null ? '—' : $byn($state))
                                                ->helperText(fn ($record): string => $record->supplyContext()['wholesale_price_label']),

                                            TextEntry::make('supply_margin')
                                                ->label('Расчётная маржа')
                                                ->columnSpan(2)
                                                ->state(function ($record) use ($byn): string {
                                                    $context = $record->supplyContext();
                                                    if ($context['margin_total'] === null) {
                                                        return 'Не рассчитана';
                                                    }

                                                    return $byn($context['margin_total'])
                                                        .($context['margin_percent'] !== null ? ' / '.number_format($context['margin_percent'], 1, '.', ' ').'%' : '');
                                                })
                                                ->color(fn ($record): string => match (true) {
                                                    $record->supplyContext()['margin_total'] === null => 'gray',
                                                    $record->supplyContext()['margin_total'] < 0 => 'danger',
                                                    $record->supplyContext()['margin_total'] == 0 => 'warning',
                                                    default => 'success',
                                                }),

                                            TextEntry::make('fulfillment_note')
                                                ->label('Комментарий к маршруту')
                                                ->columnSpanFull()
                                                ->visible(fn ($record): bool => filled($record->fulfillment_note)),
                                        ]),
                                ]),
                        ]),
                ])->columnSpan(['default' => 1, '2xl' => 2]),

                Group::make([
                    Section::make('Доставка')
                        ->icon('heroicon-o-truck')
                        ->compact()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('delivery_type')
                                ->label('Способ')
                                ->formatStateUsing(fn (string $state) => Order::DELIVERY_TYPES[$state] ?? $deliveryNames[$state] ?? $state),
                            TextEntry::make('ep_recipient')->label('Получатель Европочты')
                                ->state(fn (Order $record): string => EvropochtaRecipient::name($record))
                                ->helperText(fn (Order $record): ?string => EvropochtaRecipient::phone($record))
                                ->visible(fn (Order $record): bool => $record->delivery_type === 'evropochta'
                                    && filled($record->evropochta_quote['recipient'] ?? null)),
                            TextEntry::make('vam_order_shipment')->label('Заявка ВАМ')
                                ->getStateUsing(fn (Order $record) => VamExpressShipment::where('order_id', $record->id)->get()->map(fn ($s) => $s->display_id.' · '.$s->carrier_status)->implode('; '))
                                ->visible(fn (Order $record): bool => VamExpressShipment::where('order_id', $record->id)->exists())
                                ->url(fn (Order $record): string => VamExpressShipmentResource::getUrl('index', ['tableSearch' => $record->number])),

                            TextEntry::make('delivery_price')
                                ->label('Стоимость')
                                ->formatStateUsing(function ($state, $record) use ($byn) {
                                    if ($record->delivery_type === 'evropochta' && (float) $state === 0.0 && blank($record->evropochta_quote)) {
                                        return 'Тариф ещё не рассчитан';
                                    }
                                    if ($record->delivery_type === 'kit' && (float) $state === 0.0) {
                                        return 'Уточняется';
                                    }

                                    return (float) $state === 0.0 ? 'Бесплатно' : $byn($state);
                                }),

                            TextEntry::make('delivery_region')
                                ->label('Область')
                                ->visible(fn (Order $record): bool => filled($record->delivery_region)),

                            TextEntry::make('delivery_city')
                                ->label('Город')
                                ->placeholder('—'),

                            TextEntry::make('delivery_address')
                                ->label('Адрес')
                                ->placeholder('—')
                                ->url(fn ($state, $record) => $state
                                    ? 'https://maps.google.com/?q='.urlencode(implode(', ', array_filter([
                                        $record->delivery_city,
                                        $record->delivery_address,
                                    ])))
                                    : null)
                                ->openUrlInNewTab(),

                            TextEntry::make('planned_delivery_date')
                                ->label('Плановая дата')
                                ->date('d.m.Y')
                                ->badge()
                                ->color('warning')
                                ->visible(fn (Order $record): bool => $record->planned_delivery_date !== null),

                            TextEntry::make('delivery_prepared_summary')->label('Согласованная передача')
                                ->state(fn (Order $record): string => app(OrderDeliveryPreparation::class)->summary($record))
                                ->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull()
                                ->visible(fn (Order $record): bool => filled($record->delivery_preparation['prepared_at'] ?? null)),

                            TextEntry::make('evropochta_quote.carrier_price')
                                ->label('Тариф перевозчика')
                                ->formatStateUsing($byn)
                                ->visible(fn (Order $record): bool => $record->delivery_type === 'evropochta'
                                    && isset($record->evropochta_quote['carrier_price'])),

                            TextEntry::make('evropochta_tracking_number')
                                ->label('Трек-номер')
                                ->placeholder('Ожидает оформления')
                                ->copyable()
                                ->visible(fn (Order $record): bool => $record->delivery_type === 'evropochta'),

                            Livewire::make(EvropochtaOrderStatus::class, fn (Order $record): array => ['orderId' => $record->getKey()])
                                ->columnSpanFull()
                                ->visible(fn (Order $record): bool => $record->delivery_type === 'evropochta'
                                    && filled($record->evropochta_tracking_number)),
                        ]),

                    Section::make('Клиент')
                        ->icon('heroicon-o-user')
                        ->compact()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('customer_name')
                                ->label('Имя'),

                            TextEntry::make('customer_phone')
                                ->label('Телефон')
                                ->copyable()
                                ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                                ->html(),

                            TextEntry::make('customer_email')
                                ->label('Email')
                                ->copyable()
                                ->placeholder('—')
                                ->visible(fn (Order $record): bool => filled($record->customer_email))
                                ->url(fn ($state) => $state ? 'mailto:'.$state : null),

                            TextEntry::make('user.name')
                                ->label('Аккаунт')
                                ->visible(fn (Order $record): bool => $record->user !== null),

                            TextEntry::make('company_name')
                                ->label('Организация')
                                ->placeholder('—')
                                ->visible(fn ($record) => (bool) $record->company_name),

                            TextEntry::make('company_unp')
                                ->label('УНП')
                                ->placeholder('—')
                                ->visible(fn ($record) => (bool) $record->company_unp),
                        ]),

                    Section::make('Оплата')
                        ->icon('heroicon-o-credit-card')
                        ->compact()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('payment_type')
                                ->label('Способ')
                                ->formatStateUsing(fn (string $state) => Order::PAYMENT_TYPES[$state] ?? $paymentNames[$state] ?? $state),

                            TextEntry::make('payment_status')
                                ->label('Статус')
                                ->badge()
                                ->color(fn (Order $record): string => $record->paymentStatusColor())
                                ->formatStateUsing(fn (?string $state, Order $record): string => $record->paymentStatusLabel()),

                            TextEntry::make('coupon_code')
                                ->label('Промокод')
                                ->visible(fn (Order $record): bool => filled($record->coupon_code)),

                            TextEntry::make('discount')
                                ->label('Скидка')
                                ->formatStateUsing($byn)
                                ->visible(fn (Order $record): bool => (float) $record->discount > 0),

                            TextEntry::make('subtotal')
                                ->label('Товары')
                                ->formatStateUsing($byn),

                            TextEntry::make('total')
                                ->label('Итого')
                                ->weight('bold')
                                ->color('primary')
                                ->formatStateUsing($byn),
                        ]),
                ])->columnSpan(1),

                Section::make('Источник продажи')
                    ->icon('heroicon-o-link')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->visible(fn (Order $record): bool => filled($record->commercial_document_id)
                        || filled($record->crm_counterparty_id)
                        || $record->dealByInbox !== null)
                    ->schema([
                        TextEntry::make('dealByInbox.remote_order_id')
                            ->label('Источник')
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Deal.by №'.$state : '—')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),
                        TextEntry::make('dealByInbox.remote_status_name')
                            ->label('Статус Deal.by')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('dealByInbox.last_pushed_at')
                            ->label('Передан в Deal.by')
                            ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                            ->placeholder('Ещё не передавался'),
                        TextEntry::make('dealByInbox.last_push_error')
                            ->label('Ошибка передачи в Deal.by')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => filled($record->dealByInbox?->last_push_error)),
                        TextEntry::make('commercialDocument.number')
                            ->label('Коммерческое предложение')
                            ->placeholder('—')
                            ->url(fn (Order $record): ?string => $record->commercialDocument
                                ? CommercialDocumentResource::getUrl('view', ['record' => $record->commercialDocument])
                                : null),
                        TextEntry::make('crmCounterparty.name')
                            ->label('Контрагент CRM')
                            ->placeholder('—')
                            ->url(fn (Order $record): ?string => $record->crmCounterparty
                                ? CrmCounterpartyResource::getUrl('view', ['record' => $record->crmCounterparty])
                                : null),
                        TextEntry::make('crmContact.full_name')
                            ->label('Контактное лицо')
                            ->placeholder('—'),
                    ]),

                Section::make('Обмен с 1С')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('onec_exported_at')
                            ->label('Передан в 1С')
                            ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                            ->placeholder('Ожидает передачи')
                            ->badge()
                            ->color(fn ($state): string => $state ? 'info' : 'warning'),

                        TextEntry::make('onec_external_id')
                            ->label('Идентификатор 1С')
                            ->copyable()
                            ->placeholder('Ещё не получен'),

                        TextEntry::make('onec_status')
                            ->label('Последний статус 1С')
                            ->badge()
                            ->color(fn ($state): string => $state ? 'success' : 'gray')
                            ->placeholder('Нет ответа'),

                        TextEntry::make('onec_status_received_at')
                            ->label('Ответ получен')
                            ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                            ->placeholder('—'),

                        TextEntry::make('onec_sync_conflict')
                            ->label('Защита от отката')
                            ->state(fn (Order $record): ?string => IntegrationIssue::query()
                                ->where('order_id', $record->id)
                                ->where('type', 'order_status_conflict')
                                ->where('status', 'open')
                                ->latest('last_detected_at')
                                ->value('message'))
                            ->badge()
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => IntegrationIssue::query()
                                ->where('order_id', $record->id)
                                ->where('type', 'order_status_conflict')
                                ->where('status', 'open')
                                ->exists()),

                        TextEntry::make('onec_unknown_status')
                            ->label('Неизвестный статус 1С')
                            ->state(fn (Order $record): ?string => IntegrationIssue::query()
                                ->where('order_id', $record->id)
                                ->where('type', 'order_status_unknown')
                                ->where('status', 'open')
                                ->latest('last_detected_at')
                                ->value('message'))
                            ->badge()
                            ->color('warning')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => IntegrationIssue::query()
                                ->where('order_id', $record->id)
                                ->where('type', 'order_status_unknown')
                                ->where('status', 'open')
                                ->exists()),
                    ]),

                Section::make('Маршрутизация по источникам')
                    ->icon('heroicon-o-arrows-right-left')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Order $record): bool => $record->integrationDeliveries->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('integrationDeliveries')
                            ->hiddenLabel()
                            ->columns(6)
                            ->schema([
                                TextEntry::make('source.name')
                                    ->label('Источник')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->label('Состояние')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state, OrderIntegrationDelivery $record): string => $record->statusLabel())
                                    ->color(fn (string $state): string => match ($state) {
                                        OrderIntegrationDelivery::STATUS_ACKNOWLEDGED => 'success',
                                        OrderIntegrationDelivery::STATUS_SENT => 'info',
                                        OrderIntegrationDelivery::STATUS_FAILED => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('last_attempted_at')
                                    ->label('Последняя попытка')
                                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                                    ->placeholder('—'),
                                TextEntry::make('exported_at')
                                    ->label('Передан')
                                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                                    ->placeholder('Ожидает'),
                                TextEntry::make('remote_status')
                                    ->label('Статус источника')
                                    ->placeholder('Ответа нет'),
                                TextEntry::make('status_received_at')
                                    ->label('Ответ получен')
                                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make('Экономический снимок при оформлении')
                    ->icon('heroicon-o-calculator')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->columns(8)
                    ->schema([
                        TextEntry::make('economic_snapshot_status')
                            ->label('Полнота')
                            ->state(fn (Order $record): string => $record->placedEconomicSnapshot?->statusLabel()
                                ?? 'Снимок отсутствует — старый заказ')
                            ->badge()
                            ->color(fn (Order $record): string => match ($record->placedEconomicSnapshot?->status) {
                                OrderEconomicSnapshot::STATUS_COMPLETE => 'success',
                                null => 'gray',
                                default => 'warning',
                            })
                            ->columnSpan(2),

                        TextEntry::make('economic_snapshot_captured_at')
                            ->label('Зафиксировано')
                            ->state(fn (Order $record) => $record->placedEconomicSnapshot?->captured_at)
                            ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                            ->placeholder('Не фиксировалось'),

                        TextEntry::make('economic_snapshot_sale')
                            ->label('Продажа товаров')
                            ->state(fn (Order $record): ?string => $record->placedEconomicSnapshot
                                ? $byn($record->placedEconomicSnapshot->goods_sale_total)
                                : null)
                            ->placeholder('Нет снимка'),

                        TextEntry::make('economic_snapshot_purchase')
                            ->label('Входная стоимость')
                            ->state(function (Order $record) use ($byn): ?string {
                                $snapshot = $record->placedEconomicSnapshot;
                                if (! $snapshot) {
                                    return null;
                                }

                                return $snapshot->purchase_total !== null
                                    ? $byn($snapshot->purchase_total)
                                    : ($snapshot->priced_items_count > 0
                                        ? $byn($snapshot->known_purchase_total).' известно; без цены: '.$snapshot->missing_purchase_price_count
                                        : 'Не определена');
                            })
                            ->placeholder('Нет снимка')
                            ->color(fn (Order $record): string => $record->placedEconomicSnapshot?->purchase_total === null ? 'warning' : 'success')
                            ->columnSpan(2),

                        TextEntry::make('economic_snapshot_margin')
                            ->label('Валовая маржа товаров')
                            ->state(function (Order $record) use ($byn): ?string {
                                $snapshot = $record->placedEconomicSnapshot;
                                if (! $snapshot) {
                                    return null;
                                }
                                if ($snapshot->goods_margin_total === null) {
                                    return 'Не рассчитана полностью';
                                }

                                return $byn($snapshot->goods_margin_total)
                                    .($snapshot->goods_margin_percent !== null
                                        ? ' / '.number_format((float) $snapshot->goods_margin_percent, 1, '.', ' ').'%'
                                        : '');
                            })
                            ->placeholder('Нет снимка')
                            ->color(fn (Order $record): string => match (true) {
                                $record->placedEconomicSnapshot?->goods_margin_total === null => 'warning',
                                (float) $record->placedEconomicSnapshot->goods_margin_total < 0 => 'danger',
                                default => 'success',
                            })
                            ->columnSpan(2),

                        TextEntry::make('economic_snapshot_delivery')
                            ->label('Доставка с клиента')
                            ->state(fn (Order $record): ?string => $record->placedEconomicSnapshot
                                ? $byn($record->placedEconomicSnapshot->delivery_revenue)
                                : null)
                            ->placeholder('Нет снимка'),

                        TextEntry::make('economic_snapshot_costs')
                            ->label('Расходы доставки / оплаты / комиссия')
                            ->state(fn (Order $record): string => $record->placedEconomicSnapshot
                                ? 'Не подтверждены'
                                : 'Нет снимка')
                            ->color('warning')
                            ->columnSpan(3),

                        TextEntry::make('economic_snapshot_profit')
                            ->label('Итоговая прибыль')
                            ->state(fn (Order $record): ?string => $record->placedEconomicSnapshot?->net_profit !== null
                                ? $byn($record->placedEconomicSnapshot->net_profit)
                                : 'Не рассчитана до подтверждения расходов')
                            ->color('warning')
                            ->columnSpan(2),
                    ]),

                Section::make('Подтверждённые взаиморасчёты')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Order $record): bool => $record->latestSettlement !== null)
                    ->columns(8)
                    ->schema([
                        TextEntry::make('settlement_version')
                            ->label('Версия')
                            ->state(fn (Order $record): string => '№ '.(int) $record->latestSettlement->version)
                            ->badge(),
                        TextEntry::make('settlement_state')
                            ->label('Актуальность')
                            ->state(fn (Order $record): string => app(OrderSettlementManager::class)
                                ->isCurrent($record->latestSettlement, $record) ? 'Актуальна' : 'Маршрут изменён')
                            ->badge()
                            ->color(fn (Order $record): string => app(OrderSettlementManager::class)
                                ->isCurrent($record->latestSettlement, $record) ? 'success' : 'warning'),
                        TextEntry::make('latestSettlement.confirmed_at')
                            ->label('Подтверждено')
                            ->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                        TextEntry::make('latestSettlement.confirmer.name')
                            ->label('Подтвердил')
                            ->placeholder('Система'),
                        TextEntry::make('latestSettlement.supplier_payable_total')
                            ->label('К выплате поставщикам')
                            ->formatStateUsing($byn),
                        TextEntry::make('latestSettlement.marketplace_commission_total')
                            ->label('Комиссия маркетплейса')
                            ->formatStateUsing($byn),
                        TextEntry::make('latestSettlement.reseller_margin_total')
                            ->label('Маржа перепродажи')
                            ->formatStateUsing($byn),
                        TextEntry::make('latestSettlement.net_profit')
                            ->label('Итоговая прибыль')
                            ->weight('bold')
                            ->formatStateUsing($byn)
                            ->color(fn ($state): string => (float) $state < 0 ? 'danger' : 'success'),
                        TextEntry::make('settlement_expenses')
                            ->label('Скидка / доставка / оплата / возвраты')
                            ->state(fn (Order $record): string => $byn($record->latestSettlement->discount_total)
                                .' / '.$byn($record->latestSettlement->delivery_cost)
                                .' / '.$byn($record->latestSettlement->payment_fee)
                                .' / '.$byn($record->latestSettlement->refund_total))
                            ->columnSpan(2),
                        TextEntry::make('latestSettlement.note')
                            ->label('Основание')
                            ->placeholder('Без комментария')
                            ->columnSpan(6),
                        RepeatableEntry::make('latestSettlement.lines')
                            ->label('Расчёт по позициям')
                            ->columnSpanFull()
                            ->columns(8)
                            ->schema([
                                TextEntry::make('product_name')->label('Позиция')->columnSpan(2),
                                TextEntry::make('route')
                                    ->label('Маршрут')
                                    ->formatStateUsing(fn (string $state): string => OrderItem::FULFILLMENT_ROUTES[$state] ?? $state),
                                TextEntry::make('supplier_name')->label('Поставщик')->placeholder('Наш склад'),
                                TextEntry::make('sale_total')->label('Продажа')->formatStateUsing($byn),
                                TextEntry::make('supplier_payable')->label('Поставщику')->formatStateUsing($byn),
                                TextEntry::make('commission_amount')
                                    ->label('Комиссия')
                                    ->formatStateUsing(fn ($state, $record): string => $byn($state)
                                        .($record->commission_rate !== null ? ' · '.rtrim(rtrim(number_format((float) $record->commission_rate, 4, '.', ' '), '0'), '.').'%' : '')),
                                TextEntry::make('platform_margin')->label('Доход платформы')->formatStateUsing($byn),
                            ]),
                    ]),

                Section::make('История решений по исполнению')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Order $record): bool => $record->fulfillmentHistory->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('fulfillmentHistory')
                            ->hiddenLabel()
                            ->columns(6)
                            ->contained(false)
                            ->schema([
                                TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                                TextEntry::make('item.product_name')->label('Позиция')->columnSpan(2),
                                TextEntry::make('route')
                                    ->label('Новое решение')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => OrderItem::FULFILLMENT_ROUTES[$state] ?? $state),
                                TextEntry::make('previous_route')
                                    ->label('Было')
                                    ->formatStateUsing(fn (?string $state): string => $state
                                        ? (OrderItem::FULFILLMENT_ROUTES[$state] ?? $state)
                                        : 'Не подтверждено')
                                    ->color('gray'),
                                TextEntry::make('supplier_name')->label('Исполнитель')->placeholder('Собственный склад'),
                                TextEntry::make('user.name')->label('Кто подтвердил')->placeholder('Система'),
                                TextEntry::make('route_change')
                                    ->label('Изменение поставщика / цены')
                                    ->state(fn ($record): string => collect([
                                        ($record->previous_supplier_name ?: 'Наш склад').' → '.($record->supplier_name ?: 'Наш склад'),
                                        ($record->previous_purchase_price !== null ? $byn($record->previous_purchase_price) : 'цена не задана')
                                            .' → '.($record->purchase_price !== null ? $byn($record->purchase_price) : 'цена не задана'),
                                    ])->implode(' · '))
                                    ->columnSpan(3),
                                TextEntry::make('note')->label('Комментарий')->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Заявки поставщикам')
                    ->icon('heroicon-o-document-duplicate')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Order $record): bool => $record->supplierOrderRequests->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('supplierOrderRequests')
                            ->hiddenLabel()
                            ->columns(8)
                            ->schema([
                                TextEntry::make('number')->label('Номер')->copyable()->columnSpan(2),
                                TextEntry::make('supplier_name')->label('Поставщик'),
                                TextEntry::make('route')
                                    ->label('Маршрут')
                                    ->formatStateUsing(fn (string $state): string => OrderItem::FULFILLMENT_ROUTES[$state] ?? $state),
                                TextEntry::make('item_count')->label('Позиций')->suffix(' шт.'),
                                TextEntry::make('purchase_total')
                                    ->label('Закупка')
                                    ->formatStateUsing(fn ($state): string => $state === null ? 'Не рассчитана' : $byn($state)),
                                TextEntry::make('status')
                                    ->label('Статус')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => SupplierOrderRequest::STATUSES[$state] ?? $state)
                                    ->color(fn (string $state): string => match ($state) {
                                        'acknowledged', 'fulfilled' => 'success',
                                        'sent' => 'info',
                                        'rejected', 'cancelled' => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('transfer_mode')
                                    ->label('Передача')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'automatic' => 'Автоматически',
                                        'email' => 'Email',
                                        default => 'Менеджером',
                                    })
                                    ->color(fn (?string $state): string => match ($state) {
                                        'automatic' => 'info',
                                        'email' => 'success',
                                        default => 'gray',
                                    })
                                    ->tooltip(fn ($record): ?string => $record->automatic_transfer_run_uuid
                                        ? 'Запуск '.$record->automatic_transfer_run_uuid
                                        : null),
                                TextEntry::make('supplier_contact')->label('Контакт поставщика')->copyable()->columnSpan(3),
                                TextEntry::make('mailDraft.subject')
                                    ->label('Почтовый черновик')
                                    ->placeholder('Не создан')
                                    ->url(fn (SupplierOrderRequest $record): ?string => $record->mailDraft
                                        ? CrmMailThreadResource::getUrl('view', ['record' => $record->mailDraft->id])
                                        : null)
                                    ->color('primary')
                                    ->icon(fn (SupplierOrderRequest $record): ?string => $record->mailDraft ? 'heroicon-o-envelope' : null)
                                    ->columnSpan(3),
                                TextEntry::make('creator.name')->label('Сформировал')->placeholder('Система'),
                                TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                                TextEntry::make('sent_at')
                                    ->label('Передана')
                                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                                    ->placeholder('Не передана'),
                                TextEntry::make('sentBy.name')->label('Передал')->placeholder('—'),
                                TextEntry::make('status_updated_at')
                                    ->label('Ответ обновлён')
                                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                                    ->placeholder('Ответа нет'),
                                TextEntry::make('statusUpdatedBy.name')->label('Кто ответил')->placeholder('—'),
                                TextEntry::make('note')->label('Комментарий KOTLOV')->placeholder('—')->columnSpan(2),
                                TextEntry::make('supplier_response_note')->label('Ответ поставщика')->placeholder('—')->columnSpan(3),
                            ]),
                    ]),

                Section::make('История счетов и переписки с бухгалтерией')
                    ->description('Все поручения на оплату привязаны к заказу. Откройте письмо, чтобы увидеть приложенный счёт и продолжить переписку.')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->compact()
                    ->visible(fn (Order $record): bool => $record->accountingRequests->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('accountingRequests')
                            ->hiddenLabel()
                            ->columns(8)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Статус')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => OrderAccountingRequest::STATUSES[$state] ?? $state)
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'sent' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('amount')->label('Сумма')->money('BYN')->placeholder('Не указана'),
                                TextEntry::make('due_date')->label('Оплатить до')->date('d.m.Y')->placeholder('Срок не указан'),
                                TextEntry::make('recipient_email')->label('Бухгалтерия')->copyable()->columnSpan(2),
                                TextEntry::make('mailThread.subject')
                                    ->label('Письмо со счётом')
                                    ->placeholder('Переписка не создана')
                                    ->url(fn (OrderAccountingRequest $record): ?string => $record->mailThread
                                        ? CrmMailThreadResource::getUrl('view', ['record' => $record->mailThread])
                                        : null)
                                    ->icon('heroicon-o-paper-clip')
                                    ->color('primary')
                                    ->columnSpan(2),
                                TextEntry::make('requestedBy.name')->label('Поручил')->placeholder('Система'),
                                TextEntry::make('created_at')->label('Создано')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                                TextEntry::make('sent_at')->label('Передано')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Не отправлено'),
                                TextEntry::make('mailThread.messages_count')->label('Писем')->badge()->placeholder('0'),
                                TextEntry::make('mailThread.last_message_at')->label('Последняя переписка')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                                TextEntry::make('completedBy.name')->label('Отметил оплату')->placeholder('—'),
                                TextEntry::make('completed_at')->label('Оплачено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                                TextEntry::make('comment')->label('Комментарий')->placeholder('—')->columnSpan(3),
                                TextEntry::make('last_error')->label('Ошибка отправки')->color('danger')->visible(fn ($record): bool => filled($record->last_error))->columnSpanFull(),
                            ]),
                    ]),

                // ── Комментарии: полная ширина ────────────────────────────────
                Section::make('Комментарии')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpanFull()
                    ->columns(2)
                    ->compact()
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Комментарий клиента')
                            ->placeholder('Комментарий отсутствует'),

                        RepeatableEntry::make('managerComments')
                            ->label('Хронология комментариев менеджеров')
                            ->placeholder('Комментарии отсутствуют')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Когда')
                                    ->dateTime('d.m.Y H:i', config('app.display_timezone', 'Europe/Minsk')),
                                TextEntry::make('author_name')
                                    ->label('Кто')
                                    ->formatStateUsing(fn (?string $state, $record): string => $state
                                        ?: $record->user?->name
                                        ?: 'Автор не зафиксирован'),
                                TextEntry::make('body')
                                    ->label('Что сделал / комментарий')
                                    ->columnSpan(2),
                            ]),
                    ]),

                // ── История: полная ширина ────────────────────────────────────
                Section::make('История этапов и действий')
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('statusSms')
                            ->label('СМС покупателю')
                            ->visible(fn (Order $record): bool => $record->statusSms->isNotEmpty())
                            ->columns(3)
                            ->contained(false)
                            ->schema([
                                TextEntry::make('message')->label('Сообщение'),
                                TextEntry::make('state')
                                    ->label('Статус СМС')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'accepted' => 'Принято SMS.BY',
                                        'unconfirmed' => 'Отправка не подтверждена',
                                        'no_phone' => 'Нет корректного телефона',
                                        'outdated' => 'Пропущено: этап уже изменился',
                                        'tracking_pending' => 'В очереди СМС с треком',
                                        'unavailable' => 'Сервис СМС не настроен',
                                        'skipped' => 'Уведомление отключено',
                                        default => 'Ожидает отправки',
                                    }),
                                TextEntry::make('sent_at')
                                    ->label('Время отправки')
                                    ->dateTime('d.m.Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('sms_id')->label('ID сообщения SMS.BY')->placeholder('—'),
                            ]),

                        RepeatableEntry::make('statusHistory')
                            ->hiddenLabel()
                            ->columns(5)
                            ->contained(false)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Дата')
                                    ->dateTime('d.m.Y H:i'),

                                TextEntry::make('user.name')
                                    ->label('Кто изменил')
                                    ->placeholder('Система'),

                                TextEntry::make('status_from')
                                    ->label(fn (OrderStatusHistory $record): string => $record->status_from === $record->status_to ? 'Этап заказа' : 'Было')
                                    ->badge()
                                    ->color($statusColor)
                                    ->formatStateUsing(fn (?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('status_to')
                                    ->label('Стало')
                                    ->badge()
                                    ->color($statusColor)
                                    ->visible(fn (OrderStatusHistory $record): bool => $record->status_from !== $record->status_to)
                                    ->formatStateUsing(fn (?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('comment')
                                    ->label('Комментарий')
                                    ->columnSpan(fn (OrderStatusHistory $record): int => $record->status_from === $record->status_to ? 2 : 1)
                                    ->placeholder('—'),
                            ]),
                    ]),

            ]);
    }

    /** @return array<int, mixed> */
    private static function newSupplierForm(): array
    {
        return [
            TextInput::make('unp')
                ->label('УНП')
                ->placeholder('9 цифр')
                ->required()
                ->length(9)
                ->rule('regex:/^\d{9}$/')
                ->suffixAction(
                    Action::make('lookupSupplierByUnp')
                        ->label('Подобрать реквизиты')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('info')
                        ->tooltip('Найти в CRM / 1С и реестре МНС по УНП')
                        ->action(function (Get $get, Set $set): void {
                            $lookup = app(BelarusTaxpayerRegistry::class)->lookup((string) $get('unp'));

                            if (! $lookup) {
                                Notification::make()
                                    ->warning()
                                    ->title('Реквизиты не найдены')
                                    ->body('Проверьте УНП. Для белорусской организации требуется 9 цифр.')
                                    ->send();

                                return;
                            }

                            $set('lookup_source', $lookup['source'] ?? null);
                            $set('name', $lookup['short_name'] ?? $lookup['legal_name'] ?? null);
                            $set('legal_name', $lookup['legal_name'] ?? null);
                            $set('address', $lookup['address'] ?? null);
                            $set('email', $lookup['email'] ?? null);
                            $set('phone', $lookup['phone'] ?? null);
                            $set('registered_at', $lookup['registered_at'] ?? null);

                            Notification::make()
                                ->success()
                                ->title('Реквизиты подобраны')
                                ->body((string) ($lookup['source'] ?? 'База контрагентов'))
                                ->send();
                        }),
                ),
            TextInput::make('lookup_source')
                ->label('Источник реквизитов')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('Нажмите значок поиска возле УНП'),
            TextInput::make('name')
                ->label('Название поставщика')
                ->required()
                ->maxLength(255),
            TextInput::make('legal_name')
                ->label('Полное юридическое название')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('address')
                ->label('Юридический адрес')
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Телефон')
                ->tel()
                ->maxLength(64),
            TextInput::make('contact_name')
                ->label('Контактное лицо')
                ->maxLength(255),
            TextInput::make('registered_at')
                ->hidden(),
        ];
    }

    private static function createSupplier(array $data): int
    {
        $saved = app(CrmCounterpartyFromTaxpayerLookup::class)->upsert([
            'unp' => $data['unp'],
            'short_name' => $data['name'],
            'legal_name' => $data['legal_name'],
            'address' => $data['address'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'registered_at' => $data['registered_at'] ?? null,
        ], auth()->id(), $data['contact_name'] ?? null);

        $counterparty = $saved['counterparty'];
        $counterparty->forceFill([
            'classification' => 'supplier',
            'business_unit' => 'wholesale',
            'status' => 'active',
        ])->save();

        $code = 'supplier-'.preg_replace('/\D+/', '', (string) $data['unp']);
        $supplier = Supplier::query()->where('crm_counterparty_id', $counterparty->id)->first()
            ?? Supplier::query()->where('code', $code)->first()
            ?? new Supplier;
        $contact = collect([$data['phone'] ?? null, $data['email'] ?? null])->filter()->implode(' · ');

        $supplier->fill([
            'crm_counterparty_id' => $counterparty->id,
            'code' => $supplier->code ?: $code,
            'name' => trim((string) $data['name']),
            'contact' => $contact !== '' ? $contact : $supplier->contact,
            'is_active' => true,
        ]);
        if (! $supplier->exists) {
            $supplier->currency = 'BYN';
            $supplier->currency_rate = 1;
        }
        $supplier->save();

        Notification::make()
            ->success()
            ->title($saved['created'] ? 'Поставщик создан' : 'Поставщик связан с карточкой CRM')
            ->body($supplier->name.' · УНП '.$data['unp'])
            ->send();

        return (int) $supplier->getKey();
    }

    private static function contactLinks(?string $contact): HtmlString|string
    {
        if (blank($contact)) {
            return '';
        }

        $escaped = e($contact);
        $escaped = preg_replace_callback(
            '/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u',
            fn (array $match): string => '<a class="text-primary-600 hover:underline" href="tel:'
                .e(preg_replace('/[^\d+]/', '', $match[0])).'">☎ '.e(trim($match[0])).'</a>',
            $escaped,
        ) ?? $escaped;
        $escaped = preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            fn (array $match): string => '<a class="text-primary-600 hover:underline" href="mailto:'
                .e($match[0]).'">✉ '.e($match[0]).'</a>',
            $escaped,
        ) ?? $escaped;

        return new HtmlString($escaped);
    }

    /** @return array{0: string, 1: string, 2: ?string} what 1C knows about the order: badge, colour, line under it */
    public static function onecState(Order $order): array
    {
        if (filled($order->onec_order_guid) && ! $order->onec_deleted) {
            $status = OneCOrderProfiles::STATUSES[$order->onec_doc_status] ?? $order->onec_doc_status;

            return [(string) $order->onec_order_number, $order->onec_posted ? 'success' : 'info',
                ($order->onec_posted ? 'проведён' : 'не проведён').($status ? ' · '.mb_strtolower((string) $status) : '')];
        }
        if (app(OneCAgentTasks::class)->activeFor($order, OrderTaskHandler::TYPE)) {
            return ['отправляется', 'gray', 'агент 1С создаст заказ'];
        }
        $found = app(OneCOrderMatcher::class)->found($order);
        if ($found) {
            return ['найден: '.$found['ref']->description, 'warning', 'проверьте и свяжите'];
        }

        return [$order->onec_deleted ? 'помечен на удаление' : 'нет', $order->onec_deleted ? 'danger' : 'gray', null];
    }

    public static function assignmentPrice(OrderItem $item, ?string $route): ?float
    {
        if ($route === 'own_stock') {
            $linked = $item->product ? app(OneCItemCreator::class)->linked($item->product) : null;

            return self::onecItemByKey($linked?->external_id)?->cost_gross;
        }

        $confirmed = $item->fulfillment_route !== 'own_stock' ? $item->fulfillment_purchase_price : null;

        return $confirmed ?? $item->supply_purchase_price;
    }

    private static function onecItemByKey(?string $key): ?OneCItem
    {
        return $key ? OneCItem::query()->where('ref_key', strtolower(explode('#', $key, 2)[0]))->first() : null;
    }

    private static function onecStockAndCost(?string $key): string
    {
        $item = self::onecItemByKey($key);

        $stock = OneCStock::fresh($item) ? ' <span style="color:#64748b" title="Склад: '.e(implode(', ', OneCStock::WAREHOUSES)).'">· '.e(OneCStock::label($item)).'</span>' : '';
        $label = '<br>Себестоимость 1С с НДС за шт.: ';
        if ($item?->cost_gross === null) {
            return $stock.$label.'нет данных';
        }

        $source = $item->cost_kind === \App\Services\Integrations\OneCBridge\OneCItemCosts::AVERAGE
            ? 'Средняя за 3 месяца' : 'Закупка';
        $details = implode(' · ', array_filter([$source, $item->cost_doc, $item->cost_date?->format('d.m.Y')]));

        return $stock.$label.number_format($item->cost_gross, 2, ',', ' ').' BYN<br>'.e($details);
    }

    public static function onecItemLine(OrderItem $item): ?HtmlString
    {
        $creator = app(OneCItemCreator::class);
        if (! $item->product || $creator->source() === null) {
            return $item->product_id ? null : new HtmlString('<span style="color:#b91c1c">без карточки — в 1С не связать</span>');
        }
        $linked = $creator->linked($item->product);
        if ($linked) {
            return new HtmlString('<a href="'.e(OneCItemResource::getUrl('index', ['tableSearch' => $linked->external_code ?: $linked->name]))
                .'" style="color:#16a34a;text-decoration:underline" title="Показать в номенклатуре 1С">1С: '.e($linked->external_code ?: 'связан').'</a>'
                .self::onecStockAndCost($linked->external_id));
        }
        $tasks = app(OneCAgentTasks::class);
        $waiting = $tasks->activeFor($item->product, ItemTaskHandler::TYPE) || $tasks->activeFor($item->product, ODataReadTaskHandler::TYPE);

        return new HtmlString($waiting ? '<span style="color:#2563eb">создаётся в 1С…</span>' : '<span style="color:#d97706">нет в 1С — нажмите ⊕</span>');
    }
}
