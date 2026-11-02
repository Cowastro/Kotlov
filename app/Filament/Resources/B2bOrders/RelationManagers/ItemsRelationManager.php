<?php

namespace App\Filament\Resources\B2bOrders\RelationManagers;

use App\Models\B2bOrderItem;
use App\Services\B2bOrders\B2bOrderStockCheck;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Товары и склады';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('stock_key')->label('Откуда отгружаем')
                ->options(fn (?B2bOrderItem $record): array => $record
                    ? app(B2bOrderStockCheck::class)->poolsFor($record)->pluck('label', 'key')->all()
                    : [])
                ->placeholder('Склад не выбран')
                ->columnSpanFull(),
            Select::make('availability_status')->label('Наличие')->options(B2bOrderItem::AVAILABILITY_STATUSES)->required()->live(),
            TextInput::make('confirmed_quantity')->label('Подтверждено, шт.')
                ->numeric()->integer()->minValue(1)
                ->required(fn (Get $get): bool => $get('availability_status') === 'partial')
                ->visible(fn (Get $get): bool => $get('availability_status') === 'partial'),
            TextInput::make('price')->label('Цена клиенту')->numeric()->minValue(0.01)->required()->suffix('BYN'),
            Textarea::make('manager_note')->label('Комментарий менеджера')->rows(2)
                ->required(fn (Get $get): bool => $get('availability_status') === 'unavailable')
                ->helperText('Для «Нет в наличии» — причина: позиция уберётся из счёта.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('reservations'))
            ->columns([
                TextColumn::make('product_name')->label('Товар')->wrap()->searchable()
                    ->description(fn (B2bOrderItem $record): ?string => $record->product_sku),
                TextColumn::make('quantity')->label('Заказ / подтв.')
                    ->formatStateUsing(fn (B2bOrderItem $record): string => $record->quantity.' / '
                        .($record->isConfirmed() ? $record->orderedQuantity() : '—')),
                TextColumn::make('warehouse_name')->label('Склад')->placeholder('Не привязан')->wrap()
                    ->description(fn (B2bOrderItem $record): ?string => $record->supplier_name),
                TextColumn::make('stock_free_at_check')->label('Свободно')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, ',', ' '), '0'), ','))
                    ->placeholder('нет учёта'),
                TextColumn::make('reserved')->label('Резерв')
                    ->state(fn (B2bOrderItem $record): float => $record->reservedQuantity())
                    ->formatStateUsing(fn ($state): string => (float) $state > 0 ? rtrim(rtrim(number_format((float) $state, 3, ',', ' '), '0'), ',').' шт.' : '—')
                    ->color(fn ($state): string => (float) $state > 0 ? 'success' : 'gray'),
                TextColumn::make('price')->label('Цена')->money('BYN')
                    ->description(fn (B2bOrderItem $record): ?string => $record->purchase_price !== null
                        ? 'закупка '.number_format((float) $record->purchase_price, 2, ',', ' ').' · маржа '.$this->margin($record)
                        : 'закупка не известна'),
                TextColumn::make('availability_status')->label('Проверка')->badge()->wrap()
                    ->formatStateUsing(fn (string $state): string => B2bOrderItem::AVAILABILITY_STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success', 'partial' => 'warning', 'unavailable' => 'danger', default => 'gray',
                    })
                    ->description(fn (B2bOrderItem $record): ?string => $record->manager_note ?: $record->availability_note),
            ])
            ->headerActions([
                Action::make('checkStock')->label('Проверить наличие')->icon('heroicon-o-arrow-path')->color('primary')
                    ->action(function (): void {
                        $summary = app(B2bOrderStockCheck::class)->check($this->getOwnerRecord(), auth()->user());
                        Notification::make()->success()->title('Остатки проверены')
                            ->body(sprintf('В наличии и в резерве: %d · частично: %d · нет: %d · решить вручную: %d',
                                $summary['available'], $summary['partial'], $summary['unavailable'], $summary['manual']))
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Изменить')
                    ->after(fn (B2bOrderItem $record) => app(B2bOrderStockCheck::class)->applyManagerDecision($record, auth()->user())),
            ])
            ->recordClasses(fn (B2bOrderItem $record): ?string => $record->isConfirmed() ? null : 'bg-warning-50');
    }

    private function margin(B2bOrderItem $record): string
    {
        $price = (float) $record->price;
        if ($price <= 0) {
            return '—';
        }

        return number_format(($price - (float) $record->purchase_price) / $price * 100, 1, ',', ' ').'%';
    }
}
