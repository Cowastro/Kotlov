<?php

namespace App\Filament\Resources\B2bOrders;

use App\Filament\Resources\B2bOrders\Pages\EditB2bOrder;
use App\Filament\Resources\B2bOrders\Pages\ListB2bOrders;
use App\Filament\Resources\B2bOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\B2bOrders\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\B2bOrders\RelationManagers\EventsRelationManager;
use App\Models\B2bOrder;
use App\Models\User;
use App\Services\B2bOrders\B2bOrderWorkflow;
use App\Services\B2bOrders\B2bOrderInvoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class B2bOrderResource extends Resource
{
    protected static ?string $model = B2bOrder::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static ?string $navigationLabel = 'B2B-заказы';
    protected static ?string $modelLabel = 'B2B-заказ';
    protected static ?string $pluralModelLabel = 'B2B-заказы';
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string { return 'B2B'; }

    public static function canViewAny(): bool { return auth()->user()?->canManageB2bOrders() ?? false; }
    public static function canView(Model $record): bool { return auth()->user()?->canManageB2bOrders() ?? false; }
    public static function canEdit(Model $record): bool { return auth()->user()?->canManageB2bOrders() ?? false; }

    public static function getNavigationBadge(): ?string
    {
        $count = B2bOrder::query()->whereIn('status', ['new', 'assigned'])->count();
        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null { return 'warning'; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Заказ партнёра')->columns(2)->schema([
                Select::make('assigned_manager_id')
                    ->label('B2B-менеджер')
                    ->options(fn (): array => User::query()->where('is_active', true)
                        ->where(fn (Builder $query): Builder => $query->where('role', 'admin')->orWhere('can_manage_b2b_orders', true))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload(),
                Select::make('status')->label('Статус')->options(B2bOrder::STATUSES)->disabled()->dehydrated(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'creator', 'manager', 'items'])->withCount('items'))
            ->columns([
                TextColumn::make('number')->label('№ B2B')->searchable()->sortable()->weight('bold')
                    ->description(fn (B2bOrder $record): string => $record->created_at?->format('d.m.Y H:i') ?? ''),
                TextColumn::make('client.company_name')->label('Компания')->searchable()
                    ->description(fn (B2bOrder $record): string => collect([$record->company_unp ? 'УНП '.$record->company_unp : null, $record->contact_name, $record->contact_phone])->filter()->implode(' · ')),
                TextColumn::make('stock')->label('Наличие')->badge()
                    ->state(fn (B2bOrder $record): string => $record->items->filter->isConfirmed()->count().' из '.$record->items->count())
                    ->color(fn (B2bOrder $record): string => $record->stockIsSettled() ? 'success' : 'warning'),
                TextColumn::make('total')->label('Сумма')->money('BYN')->sortable(),
                TextColumn::make('status')->label('Этап')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bOrder::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new', 'assigned' => 'warning', 'checking', 'awaiting_supplier' => 'info',
                        'completed', 'paid', 'shipped' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
                TextColumn::make('payment_status')->label('Оплата')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bOrder::PAYMENT_STATUSES[$state] ?? $state),
                TextColumn::make('manager.name')->label('B2B-менеджер')->placeholder('Не назначен'),
                TextColumn::make('next_action')->label('Следующее действие')->wrap()
                    ->state(fn (B2bOrder $record): string => static::nextAction($record)),
            ])
            ->filters([
                SelectFilter::make('status')->label('Этап')->options(B2bOrder::STATUSES),
                SelectFilter::make('assigned_manager_id')->label('B2B-менеджер')->relationship('manager', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ...B2bOrderActions::steps(),
                EditAction::make()->label('Карточка'),
            ])
            ->recordUrl(fn (B2bOrder $record): string => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function nextAction(B2bOrder $order): string
    {
        $open = $order->items->reject(fn ($item): bool => $item->isConfirmed() || $item->isExcluded())->count();

        return match (true) {
            in_array($order->status, ['new', 'assigned'], true) => 'Принять в работу',
            in_array($order->status, ['checking', 'awaiting_supplier'], true) && $open > 0 => 'Решить '.$open.' поз. без остатка',
            in_array($order->status, ['checking', 'awaiting_supplier', 'proposal_ready'], true) => 'Отправить клиенту',
            $order->status === 'awaiting_client' => 'Ждём подтверждения клиента',
            $order->status === 'client_confirmed' => 'Счёт не сформировался — выставить вручную',
            in_array($order->status, ['invoice_issued', 'awaiting_payment'], true) => 'Ждём оплату по счёту',
            in_array($order->status, ['paid', 'credit_approved'], true) => 'Передать на склад',
            $order->status === 'assembling' => 'Собрать заказ',
            $order->status === 'ready_to_ship' => 'Отгрузить',
            default => '—',
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'manager']);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class, CommentsRelationManager::class, EventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListB2bOrders::route('/'),
            'edit' => EditB2bOrder::route('/{record}/edit'),
        ];
    }
}
