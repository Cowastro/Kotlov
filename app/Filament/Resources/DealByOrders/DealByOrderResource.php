<?php

namespace App\Filament\Resources\DealByOrders;

use App\Filament\Resources\DealByOrders\Pages\ListDealByOrders;
use App\Filament\Resources\DealByOrders\Pages\ViewDealByOrder;
use App\Models\DealByOrderInbox;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DealByOrderResource extends Resource
{
    protected static ?string $model = DealByOrderInbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Заказы Deal.by';

    protected static ?string $modelLabel = 'заказ Deal.by';

    protected static ?string $pluralModelLabel = 'Заказы Deal.by';

    protected static ?string $recordTitleAttribute = 'remote_order_id';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DealByOrderInbox::query()
            ->where('processing_status', DealByOrderInbox::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('remote_order_id')
                    ->label('№ Deal.by')
                    ->description('Источник: Deal.by')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('remote_created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('customer')
                    ->label('Клиент')
                    ->state(fn (DealByOrderInbox $record): string => $record->customerName())
                    ->description(fn (DealByOrderInbox $record): string => $record->customerPhone() ?: 'Телефон не указан')
                    ->wrap(),
                TextColumn::make('products')
                    ->label('Товары')
                    ->state(fn (DealByOrderInbox $record): string => $record->productsSummary())
                    ->description(fn (DealByOrderInbox $record): string => 'Позиций: '.$record->productsCount())
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('total')
                    ->label('Сумма')
                    ->state(fn (DealByOrderInbox $record): ?float => $record->orderTotal())
                    ->formatStateUsing(fn (?float $state): string => $state === null
                        ? '—'
                        : number_format($state, 2, '.', ' ').' BYN')
                    ->alignRight(),
                TextColumn::make('remote_status_name')
                    ->label('Статус Deal.by')
                    ->state(fn (DealByOrderInbox $record): string => $record->remote_status_name ?: ($record->remote_status ?: 'Не указан'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('processing_status')
                    ->label('В KOTLOV.BY')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::processingStatusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        DealByOrderInbox::STATUS_PROCESSED => 'success',
                        DealByOrderInbox::STATUS_FAILED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('processing_status')
                    ->label('Состояние в KOTLOV.BY')
                    ->options([
                        DealByOrderInbox::STATUS_PENDING => self::processingStatusLabel(DealByOrderInbox::STATUS_PENDING),
                        DealByOrderInbox::STATUS_PROCESSED => self::processingStatusLabel(DealByOrderInbox::STATUS_PROCESSED),
                        DealByOrderInbox::STATUS_FAILED => self::processingStatusLabel(DealByOrderInbox::STATUS_FAILED),
                    ]),
            ])
            ->recordActions([ViewAction::make()->label('Открыть')])
            ->defaultSort('remote_created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('Заказов Deal.by пока нет')
            ->emptyStateDescription('После первого безопасного запуска загрузки новые и изменённые заказы появятся здесь.');
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (mixed $state): string => is_numeric($state)
            ? number_format((float) $state, 2, '.', ' ').' BYN'
            : '—';

        return $schema->components([
            Section::make('Заказ Deal.by')
                ->columns(4)
                ->schema([
                    TextEntry::make('remote_order_id')->label('№ Deal.by')->copyable()->weight('bold'),
                    TextEntry::make('source')->label('Источник')->state('Deal.by')->badge()->color('info'),
                    TextEntry::make('remote_status_name')->label('Статус Deal.by')->badge()
                        ->state(fn (DealByOrderInbox $record): string => $record->remote_status_name ?: ($record->remote_status ?: 'Не указан')),
                    TextEntry::make('processing_status')->label('В KOTLOV.BY')->badge()
                        ->formatStateUsing(fn (string $state): string => self::processingStatusLabel($state)),
                    TextEntry::make('remote_created_at')->label('Создан')->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('—'),
                    TextEntry::make('remote_modified_at')->label('Изменён')->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('—'),
                    TextEntry::make('first_seen_at')->label('Впервые получен')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                    TextEntry::make('last_seen_at')->label('Последняя проверка')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                ]),
            Section::make('Покупатель')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer_name')->label('Имя')
                        ->state(fn (DealByOrderInbox $record): string => $record->customerName()),
                    TextEntry::make('payload.phone')->label('Телефон')->copyable()->placeholder('—'),
                    TextEntry::make('payload.email')->label('Email')->copyable()->placeholder('—'),
                    TextEntry::make('payload.client_notes')->label('Комментарий покупателя')->placeholder('Нет комментария')->columnSpanFull(),
                ]),
            Section::make('Доставка и оплата')
                ->columns(3)
                ->schema([
                    TextEntry::make('payload.delivery_option.name')->label('Способ доставки')->placeholder('—'),
                    TextEntry::make('payload.delivery_address')->label('Адрес')->placeholder('—')->columnSpan(2),
                    TextEntry::make('payload.delivery_cost')->label('Стоимость доставки')->formatStateUsing($money),
                    TextEntry::make('payload.payment_option.name')->label('Способ оплаты')->placeholder('—'),
                    TextEntry::make('payload.full_price')->label('Итого')->formatStateUsing($money)->weight('bold'),
                ]),
            Section::make('Товары')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('payload.products')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('name')->label('Товар')->columnSpan(2)->weight('medium'),
                            TextEntry::make('sku')->label('Артикул')->copyable()->placeholder('—'),
                            TextEntry::make('price')->label('Цена')->formatStateUsing($money)->alignRight(),
                            TextEntry::make('quantity')->label('Количество')->alignRight(),
                            TextEntry::make('total_price')->label('Сумма')->formatStateUsing($money)->alignRight()->weight('bold'),
                        ]),
                ]),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDealByOrders::route('/'),
            'view' => ViewDealByOrder::route('/{record}'),
        ];
    }

    private static function processingStatusLabel(string $status): string
    {
        return match ($status) {
            DealByOrderInbox::STATUS_PROCESSED => 'Создан рабочий заказ',
            DealByOrderInbox::STATUS_FAILED => 'Ошибка обработки',
            default => 'Во входящем журнале',
        };
    }
}
