<?php

namespace App\Filament\Resources\B2bSpecifications\RelationManagers;

use App\Models\B2bSpecificationItem;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Позиции из спецификации';

    protected static ?string $label = 'Позиция';

    protected static ?string $pluralLabel = 'Позиции';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source_sku')->label('Артикул из файла')->disabled()->dehydrated(false),
            TextInput::make('source_name')->label('Название из файла')->disabled()->dehydrated(false),
            TextInput::make('quantity')->label('Количество')->numeric()->minValue(0.001)->required(),
            TextInput::make('unit')->label('Единица')->maxLength(40),
            Select::make('product_id')
                ->label('Товар каталога')
                ->relationship(
                    'product',
                    'name',
                    modifyQueryUsing: fn (Builder $query) => $query->active()->notArchived(),
                )
                ->getOptionLabelFromRecordUsing(fn ($record): string => ($record->sku ? $record->sku.' · ' : '').$record->name)
                ->searchable(['name', 'sku'])
                ->nullable(),
            Select::make('status')
                ->label('Решение')
                ->options(B2bSpecificationItem::STATUSES)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['attachment', 'product'])->orderBy('id'))
            ->columns([
                TextColumn::make('source_row')->label('Строка')->numeric()->sortable(),
                TextColumn::make('source_sku')->label('Артикул')->placeholder('—')->searchable(),
                TextColumn::make('source_name')->label('Название из файла')->wrap()->limit(55)->searchable(),
                TextColumn::make('quantity')->label('Кол-во')->numeric(decimalPlaces: 3)->suffix(fn ($record): string => ' '.($record->unit ?: 'шт.')),
                TextColumn::make('product.name')->label('Товар каталога')->placeholder('Не сопоставлен')->wrap()->limit(55)
                    ->description(fn ($record): ?string => $record->product?->sku),
                TextColumn::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bSpecificationItem::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('attachment.original_name')->label('Файл')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(B2bSpecificationItem::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label('Проверить'),
            ])
            ->emptyStateHeading('Табличные позиции не извлечены')
            ->emptyStateDescription('PDF и изображения менеджер обрабатывает вручную. Для Excel и CSV позиции появятся автоматически.');
    }
}
