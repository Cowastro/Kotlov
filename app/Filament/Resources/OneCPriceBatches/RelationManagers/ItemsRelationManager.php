<?php

namespace App\Filament\Resources\OneCPriceBatches\RelationManagers;

use App\Models\OneCPriceBatchItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** The goods of a batch: retail and wholesale with VAT are edited in place while the batch is a draft. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Товары и цены';

    private const VERDICTS = [
        'ok' => 'Розница сайта адекватна',
        'computed' => 'Рассчитана от закупки',
        'raised' => 'Поднята (низкая наценка)',
        'markup' => 'По наценке от себестоимости',
        'fixed' => 'Задано вручную',
        'supplier' => 'Розница из прайса поставщика',
        'no_cost' => 'Нет себестоимости — вручную',
    ];

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $editable = fn (): bool => $this->getOwnerRecord()->isEditable();

        return $table
            ->columns([
                TextColumn::make('code')->label('Код 1С')->searchable()->toggleable(),
                TextColumn::make('name')->label('Товар')->searchable()->wrap(),
                TextColumn::make('purchase')->label('Себестоимость / закупка')->numeric(2)->alignRight()->placeholder('—'),
                TextColumn::make('retail_site')->label('Розница сайта')->numeric(2)->alignRight()->placeholder('—'),
                TextInputColumn::make('retail')->label('Розница с НДС')->type('number')->step('0.01')
                    ->rules(['required', 'numeric', 'min:0.01'])->disabled(fn (): bool => ! $editable()),
                TextInputColumn::make('wholesale')->label('Опт с НДС')->type('number')->step('0.01')
                    ->rules(['required', 'numeric', 'min:0.01'])->disabled(fn (): bool => ! $editable()),
                TextColumn::make('margin')->label('Наценка розн. / опт')->alignRight()
                    ->state(fn (OneCPriceBatchItem $record): ?string => (float) $record->purchase > 0
                        ? round(((float) $record->retail / (float) $record->purchase - 1) * 100).' % / '.round(((float) $record->wholesale / (float) $record->purchase - 1) * 100).' %' : null)
                    // below the 1C cost: red, and the batch is not sent without a confirmation
                    ->color(fn (OneCPriceBatchItem $record): ?string => (float) $record->purchase > 0
                        && ((float) $record->retail < (float) $record->purchase || (float) $record->wholesale < (float) $record->purchase) ? 'danger' : null)
                    ->placeholder('—'),
                TextColumn::make('verdict')->label('Проверка')->badge()
                    ->formatStateUsing(fn (?string $state): string => self::VERDICTS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => in_array($state, ['ok', 'markup', 'fixed', 'supplier'], true) ? 'success' : 'warning')
                    ->description(fn (OneCPriceBatchItem $record): ?string => $record->note),
            ])
            ->filters([
                SelectFilter::make('verdict')->label('Проверка')->options(self::VERDICTS),
            ])
            ->defaultSort('name')
            ->paginated([50, 100, 'all'])
            ->recordActions([
                DeleteAction::make()->label('Убрать')->visible(fn (): bool => $editable()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->label('Убрать выбранные')->visible(fn (): bool => $editable()),
            ]);
    }
}
