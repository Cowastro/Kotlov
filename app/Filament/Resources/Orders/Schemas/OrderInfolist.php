<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\IntegrationIssue;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $paymentNames = collect(config('shop.payment_methods', []))->mapWithKeys(fn ($m, $k) => [$k => $m['name']])->toArray();
        $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn ($m, $k) => [$k => $m['name']])->toArray();

        $byn = fn ($state) => number_format((float) $state, 2, '.', ' ').' BYN';

        $statusColor = fn (?string $state) => match ($state) {
            'new' => 'info',
            'confirmed' => 'warning',
            'processing' => 'warning',
            'shipped' => 'primary',
            'delivered' => 'success',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };

        $paymentStatusColor = fn (?string $state) => match ($state) {
            'paid' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'info',
            default => 'gray',
        };

        $paymentStatusLabel = fn (?string $state) => match ($state) {
            'paid' => 'Оплачен',
            'pending' => 'Ожидает оплаты',
            'failed' => 'Ошибка оплаты',
            'refunded' => 'Возврат',
            default => $state,
        };

        return $schema
            ->columns(3)
            ->components([

                // ── Шапка: полная ширина ──────────────────────────────────────
                Section::make()
                    ->columnSpanFull()
                    ->columns(8)
                    ->compact()
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер заказа')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color($statusColor)
                            ->formatStateUsing(fn (string $state) => Order::STATUSES[$state] ?? $state),

                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Обновлён')
                            ->dateTime('d.m.Y H:i'),

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
                            ->color($paymentStatusColor)
                            ->formatStateUsing($paymentStatusLabel),

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

                Section::make('Обмен с 1С')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->columnSpanFull()
                    ->compact()
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
                    ->description('Каждый поставщик и каждая 1С подтверждают только свою часть смешанного заказа.')
                    ->icon('heroicon-o-arrows-right-left')
                    ->columnSpanFull()
                    ->compact()
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

                // ── Клиент (1 из 3) ──────────────────────────────────────────
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
                            ->url(fn ($state) => $state ? 'tel:'.preg_replace('/\s+/', '', $state) : null),

                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('—')
                            ->url(fn ($state) => $state ? 'mailto:'.$state : null),

                        TextEntry::make('user.name')
                            ->label('Аккаунт')
                            ->placeholder('Гость'),

                        TextEntry::make('company_name')
                            ->label('Организация')
                            ->placeholder('—')
                            ->visible(fn ($record) => (bool) $record->company_name),

                        TextEntry::make('company_unp')
                            ->label('УНП')
                            ->placeholder('—')
                            ->visible(fn ($record) => (bool) $record->company_unp),
                    ]),

                // ── Доставка (2 из 3) ─────────────────────────────────────────
                Section::make('Доставка')
                    ->icon('heroicon-o-truck')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('delivery_type')
                            ->label('Способ')
                            ->formatStateUsing(fn (string $state) => Order::DELIVERY_TYPES[$state] ?? $deliveryNames[$state] ?? $state),

                        TextEntry::make('delivery_price')
                            ->label('Стоимость')
                            ->formatStateUsing(function ($state, $record) use ($byn) {
                                if ($record->delivery_type === 'kit' && (float) $state === 0.0) {
                                    return 'Уточняется';
                                }

                                return (float) $state === 0.0 ? 'Бесплатно' : $byn($state);
                            }),

                        TextEntry::make('delivery_region')
                            ->label('Область')
                            ->placeholder('—'),

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
                    ]),

                // ── Оплата (3 из 3) ──────────────────────────────────────────
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
                            ->color($paymentStatusColor)
                            ->formatStateUsing($paymentStatusLabel),

                        TextEntry::make('coupon_code')
                            ->label('Промокод')
                            ->placeholder('—'),

                        TextEntry::make('discount')
                            ->label('Скидка')
                            ->formatStateUsing(fn ($state) => (float) $state > 0 ? $byn($state) : '—'),

                        TextEntry::make('subtotal')
                            ->label('Товары')
                            ->formatStateUsing($byn),

                        TextEntry::make('total')
                            ->label('Итого')
                            ->weight('bold')
                            ->color('primary')
                            ->formatStateUsing($byn),
                    ]),

                // ── Товары заказа: полная ширина ──────────────────────────────
                Section::make('Товары заказа')
                    ->description('Новые заказы сохраняют поставщика, входную цену, НДС, остаток и контакт на момент оформления. Для старых заказов без снимка показана текущая рекомендация.')
                    ->icon('heroicon-o-shopping-bag')
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(12)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Товар')
                                    ->columnSpan(3)
                                    ->url(fn ($state, $record) => $record->product?->category
                                        ? url('/'.$record->product->category->slug.'/'.$record->product->slug)
                                        : null)
                                    ->openUrlInNewTab(),

                                TextEntry::make('product_sku')
                                    ->label('Артикул')
                                    ->columnSpan(2)
                                    ->placeholder('—')
                                    ->fontFamily('mono')
                                    ->copyable(),

                                TextEntry::make('quantity')
                                    ->label('Кол-во')
                                    ->suffix(' шт.'),

                                TextEntry::make('price')
                                    ->label('Продажа / шт.')
                                    ->formatStateUsing($byn),

                                TextEntry::make('total')
                                    ->label('Сумма')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->formatStateUsing($byn),

                                TextEntry::make('supply_supplier')
                                    ->label('Поставщик')
                                    ->columnSpan(2)
                                    ->state(fn ($record): ?string => $record->supplyContext()['supplier_name'])
                                    ->placeholder('Не определён')
                                    ->weight('bold'),

                                TextEntry::make('supply_contact')
                                    ->label('Контакты поставщика')
                                    ->columnSpan(2)
                                    ->state(fn ($record): ?string => $record->supplyContext()['supplier_contact'])
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

                                TextEntry::make('supply_stock')
                                    ->label('Наличие')
                                    ->columnSpan(2)
                                    ->state(fn ($record): string => $record->supplyContext()['stock_label']),

                                TextEntry::make('supply_source')
                                    ->label('Источник данных')
                                    ->columnSpan(2)
                                    ->state(fn ($record): ?string => $record->supplyContext()['source_label'])
                                    ->placeholder('Нет источника'),

                                TextEntry::make('supply_confidence')
                                    ->label('Надёжность маршрута')
                                    ->columnSpan(2)
                                    ->state(function ($record): string {
                                        $context = $record->supplyContext();

                                        return match (true) {
                                            $context['is_snapshot'] => 'Снимок заказа · '.optional($record->supply_captured_at)->timezone('Europe/Minsk')->format('d.m.Y H:i'),
                                            $context['is_explicit'] => 'Явная связь товара',
                                            $context['candidate_count'] > 1 => 'Рекомендация · вариантов: '.$context['candidate_count'],
                                            $context['candidate_count'] === 1 => 'Текущая рекомендация',
                                            default => 'Нужна ручная маршрутизация',
                                        };
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => match (true) {
                                        $record->supplyContext()['is_explicit'] => 'success',
                                        $record->supplyContext()['candidate_count'] > 1 => 'warning',
                                        $record->supplyContext()['candidate_count'] === 1 => 'info',
                                        default => 'danger',
                                    }),
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

                        TextEntry::make('admin_comment')
                            ->label('Комментарий менеджера')
                            ->placeholder('Комментарий отсутствует'),
                    ]),

                // ── История: полная ширина ────────────────────────────────────
                Section::make('История изменений статуса')
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->schema([
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
                                    ->label('Было')
                                    ->badge()
                                    ->color($statusColor)
                                    ->formatStateUsing(fn (?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('status_to')
                                    ->label('Стало')
                                    ->badge()
                                    ->color($statusColor)
                                    ->formatStateUsing(fn (?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('comment')
                                    ->label('Комментарий')
                                    ->placeholder('—'),
                            ]),
                    ]),

            ]);
    }
}
