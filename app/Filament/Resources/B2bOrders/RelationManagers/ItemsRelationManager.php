<?php

namespace App\Filament\Resources\B2bOrders\RelationManagers;

use App\Models\B2bOrderItem;
use App\Models\B2bOrderEvent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Проверка позиций';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('availability_status')->label('Наличие')->options(B2bOrderItem::AVAILABILITY_STATUSES)->required()->live(),
            TextInput::make('confirmed_quantity')->label('Подтверждено, шт.')
                ->numeric()->integer()->minValue(1)
                ->required(fn (Get $get): bool => $get('availability_status') === 'partial')
                ->visible(fn (Get $get): bool => in_array($get('availability_status'), ['available', 'partial'], true)),
            TextInput::make('price')->label('Согласованная цена B2B')->numeric()->minValue(0.01)->required()->suffix('BYN'),
            Textarea::make('manager_note')->label('Комментарий менеджера')->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('product_sku')->label('Артикул')->placeholder('—'),
            TextColumn::make('product_name')->label('Товар')->wrap()->searchable(),
            TextColumn::make('quantity')->label('Кол-во')->numeric(),
            TextColumn::make('price')->label('Цена B2B')->money('BYN'),
            TextColumn::make('purchase_price')->label('Закупка')->money('BYN')->placeholder('—'),
            TextColumn::make('availability_status')->label('Проверка')->badge()
                ->formatStateUsing(fn (string $state): string => B2bOrderItem::AVAILABILITY_STATUSES[$state] ?? $state),
            TextColumn::make('supplier_name')->label('Поставщик')->placeholder('Собственный склад'),
            TextColumn::make('warehouse_name')->label('Склад')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ])->recordActions([
            \Filament\Actions\EditAction::make()->label('Проверить')->after(function (B2bOrderItem $record): void {
                DB::transaction(function () use ($record): void {
                    $record->forceFill(['checked_at' => now(), 'checked_by' => auth()->id()])->saveQuietly();
                    $order = $record->order()->lockForUpdate()->firstOrFail();
                    $items = $order->items()->get();
                    $subtotal = (float) $items->sum(fn (B2bOrderItem $item): float =>
                        (float) $item->price * (int) ($item->confirmed_quantity ?: $item->quantity));
                    $order->forceFill([
                        'subtotal' => round($subtotal, 2),
                        'total' => round($subtotal - (float) $order->discount + (float) $order->delivery_price, 2),
                    ])->saveQuietly();
                    B2bOrderEvent::query()->create([
                        'b2b_order_id' => $order->id,
                        'actor_id' => auth()->id(),
                        'event' => 'item_checked',
                        'comment' => 'Проверена позиция '.$record->product_name.'.',
                        'metadata' => [
                            'item_id' => $record->id,
                            'availability_status' => $record->availability_status,
                            'confirmed_quantity' => $record->confirmed_quantity,
                            'price' => (float) $record->price,
                        ],
                    ]);
                });
            }),
        ]);
    }
}
