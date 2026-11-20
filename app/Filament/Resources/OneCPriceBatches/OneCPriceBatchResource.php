<?php

namespace App\Filament\Resources\OneCPriceBatches;

use App\Filament\Resources\OneCPriceBatches\Pages\ListOneCPriceBatches;
use App\Filament\Resources\OneCPriceBatches\Pages\ViewOneCPriceBatch;
use App\Filament\Resources\OneCPriceBatches\RelationManagers\ItemsRelationManager;
use App\Models\OneCPriceBatch;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** «Цены для 1С»: batches of wholesale and retail prices the agent on the 1C server turns into «Установка цен». */
class OneCPriceBatchResource extends Resource
{
    protected static ?string $model = OneCPriceBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Цены для 1С';

    protected static ?string $modelLabel = 'пакет цен';

    protected static ?string $pluralModelLabel = 'Цены для 1С';

    protected static ?int $navigationSort = 13;

    /** Reached from «Мост 1С»: one menu entry for everything that goes to 1C. */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isManager();
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
        return static::canViewAny() && in_array($record->status, [OneCPriceBatch::DRAFT, OneCPriceBatch::CANCELLED], true);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Пакет')->columns(3)->schema([
                TextEntry::make('status')->label('Состояние')->badge()
                    ->formatStateUsing(fn (string $state): string => OneCPriceBatch::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => self::color($state)),
                TextEntry::make('items_count')->label('Товаров')->state(fn (OneCPriceBatch $record): int => $record->items()->count()),
                TextEntry::make('onec_document_number')->label('Документ в 1С')->placeholder('—')
                    ->helperText('«Установка цен номенклатуры», не проведён: бухгалтер проверяет и проводит.'),
                TextEntry::make('creator.name')->label('Сформировал')->placeholder('—'),
                TextEntry::make('approved_at')->label('Отправлен')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                TextEntry::make('finished_at')->label('Ответ агента')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                TextEntry::make('error')->label('Ошибка')->placeholder('—')->columnSpanFull()->visible(fn (OneCPriceBatch $record): bool => filled($record->error)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable(),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('status')->label('Состояние')->badge()
                    ->formatStateUsing(fn (string $state): string => OneCPriceBatch::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => self::color($state)),
                TextColumn::make('items_count')->label('Товаров')->counts('items')->alignRight(),
                TextColumn::make('onec_document_number')->label('Документ 1С')->placeholder('—'),
                TextColumn::make('comment')->label('Комментарий')->limit(60)->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneCPriceBatches::route('/'),
            'view' => ViewOneCPriceBatch::route('/{record}'),
        ];
    }

    public static function color(string $state): string
    {
        return match ($state) {
            OneCPriceBatch::CREATED => 'success',
            OneCPriceBatch::FAILED => 'danger',
            OneCPriceBatch::APPROVED, OneCPriceBatch::SENDING => 'warning',
            default => 'gray',
        };
    }
}
