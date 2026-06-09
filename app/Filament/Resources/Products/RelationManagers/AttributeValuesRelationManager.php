<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'allAttributeValues';

    protected static ?string $title = 'Технические характеристики';
    protected static ?string $label = 'Характеристика';
    protected static ?string $pluralLabel = 'Характеристики';

    // ─────────────────────────────────────────────────────────────────
    //  Форма создания / редактирования значения характеристики
    // ─────────────────────────────────────────────────────────────────
    public function form(Schema $schema): Schema
    {
        return $schema->components([

            // Выбор атрибута — фильтруется по категории товара,
            // уже назначенные атрибуты скрываются (за исключением текущего при редактировании)
            Select::make('attribute_id')
                ->label('Характеристика')
                ->required()
                ->live()
                ->searchable()
                ->options(function ($record): array {
                    $product   = $this->getOwnerRecord();
                    $usedQuery = $product->allAttributeValues();

                    // При редактировании исключаем текущий атрибут из «занятых»,
                    // чтобы он оставался доступен в списке
                    if ($record) {
                        $usedQuery->where('attribute_id', '!=', $record->attribute_id);
                    }

                    $usedIds = $usedQuery->pluck('attribute_id')->toArray();

                    return Attribute::when(
                            $product->category_id,
                            fn($q) => $q->where('category_id', $product->category_id)
                        )
                        ->whereNotIn('id', $usedIds)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn(Attribute $attr) => [
                            $attr->id => $attr->name
                                . ($attr->suffix ? " ({$attr->suffix})" : '')
                                . match ($attr->type) {
                                    'select' => ' — список',
                                    'check'  => ' — да/нет',
                                    default  => '',
                                },
                        ])
                        ->toArray();
                })
                ->columnSpanFull(),

            // ── type = select : выбор одного варианта из списка ─────────────
            Select::make('option_id')
                ->label('Значение (выбор из списка)')
                ->searchable()
                ->options(function ($get): array {
                    $attrId = $get('attribute_id');
                    if (! $attrId) {
                        return [];
                    }

                    return AttributeOption::where('attribute_id', $attrId)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->hidden(fn($get): bool => $this->resolveType($get('attribute_id')) !== 'select')
                ->columnSpanFull(),

            // ── type = value : текстовое / числовое значение ─────────────────
            TextInput::make('value')
                ->label(function ($get): string {
                    $attr = Attribute::find($get('attribute_id'));
                    return 'Значение' . ($attr?->suffix ? " ({$attr->suffix})" : '');
                })
                ->hidden(fn($get): bool => $this->resolveType($get('attribute_id')) !== 'value')
                ->columnSpanFull(),

            // ── type = check : булево Да / Нет ───────────────────────────────
            Toggle::make('is_checked')
                ->label('Значение (Да / Нет)')
                ->onColor('success')
                ->offColor('danger')
                ->hidden(fn($get): bool => $this->resolveType($get('attribute_id')) !== 'check')
                ->columnSpanFull(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Таблица со значениями характеристик товара
    // ─────────────────────────────────────────────────────────────────
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn($query) => $query
                    ->with(['attribute', 'option'])
                    ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
                    ->orderBy('attributes.sort_order')
                    ->select('product_attribute_values.*')
            )
            ->columns([
                TextColumn::make('attribute.name')
                    ->label('Характеристика')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attribute.type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'select' => 'Список',
                        'value'  => 'Значение',
                        'check'  => 'Да/Нет',
                        default  => (string) $state,
                    })
                    ->color(fn(?string $state): string => match ($state) {
                        'select' => 'info',
                        'value'  => 'success',
                        'check'  => 'warning',
                        default  => 'gray',
                    }),

                TextColumn::make('display_value')
                    ->label('Значение')
                    ->placeholder('—'),

                TextColumn::make('attribute.suffix')
                    ->label('Ед.')
                    ->placeholder('—'),

                TextColumn::make('attribute.sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Добавить характеристику')
                    ->modalHeading('Добавить характеристику товара'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Изменить значение'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Характеристики не заданы')
            ->emptyStateDescription('Выберите «Добавить характеристику», чтобы заполнить технические данные товара.');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Вспомогательный метод: тип атрибута по его ID (с кешем)
    // ─────────────────────────────────────────────────────────────────
    private function resolveType(?int $attributeId): ?string
    {
        if (! $attributeId) {
            return null;
        }

        /** @var Attribute|null $attr */
        $attr = Attribute::find($attributeId);

        return $attr?->type;
    }
}
