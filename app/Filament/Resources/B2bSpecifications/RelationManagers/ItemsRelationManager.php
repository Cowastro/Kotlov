<?php

namespace App\Filament\Resources\B2bSpecifications\RelationManagers;

use App\Models\B2bSpecificationItem;
use App\Services\B2bSpecificationItemDecision;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

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
            Placeholder::make('candidate_options')
                ->label('Предложенные варианты')
                ->content(function (?B2bSpecificationItem $record): HtmlString|string {
                    if (! $record || blank($record->candidates)) {
                        return 'Подходящих вариантов не найдено.';
                    }

                    $rows = collect($record->candidates)->take(5)->map(function (array $candidate): string {
                        $name = e((string) ($candidate['name'] ?? 'Товар'));
                        $sku = filled($candidate['sku'] ?? null) ? ' · '.e((string) $candidate['sku']) : '';
                        $score = number_format((float) ($candidate['score'] ?? 0) * 100, 0, ',', ' ');

                        return "<li><strong>{$name}</strong>{$sku} <small>({$score}%)</small></li>";
                    })->implode('');

                    return new HtmlString("<ol class=\"list-decimal pl-5 space-y-1\">{$rows}</ol>");
                })
                ->columnSpanFull(),
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
                TextColumn::make('top_candidate')
                    ->label('Лучший вариант')
                    ->state(fn (B2bSpecificationItem $record): ?string => $record->topCandidate()['name'] ?? null)
                    ->description(function (B2bSpecificationItem $record): ?string {
                        $candidate = $record->topCandidate();
                        if (! $candidate) {
                            return null;
                        }

                        return collect([
                            $candidate['sku'] ?? null,
                            number_format((float) ($candidate['score'] ?? 0) * 100, 0, ',', ' ').'%',
                        ])->filter()->implode(' · ');
                    })
                    ->placeholder('—')
                    ->wrap()
                    ->limit(55),
                TextColumn::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bSpecificationItem::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'suggested' => 'info',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('attachment.original_name')->label('Файл')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(B2bSpecificationItem::STATUSES),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Проверить')
                    ->after(function (B2bSpecificationItem $record): void {
                        $record->forceFill([
                            'reviewed_by' => Filament::auth()->id(),
                            'reviewed_at' => now(),
                        ])->saveQuietly();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('confirmSuggestions')
                    ->label('Подтвердить лучшие варианты')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Подтверждаются только выбранные строки с предложенным товаром. Клиент увидит их в корзине после вашего решения.')
                    ->action(function (Collection $records): void {
                        $reviewer = Filament::auth()->user();
                        $applied = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            app(B2bSpecificationItemDecision::class)->confirmTopCandidate($record, $reviewer)
                                ? $applied++
                                : $skipped++;
                        }

                        Notification::make()
                            ->title('Проверка завершена')
                            ->body("Подтверждено: {$applied}. Пропущено: {$skipped}.")
                            ->color($skipped > 0 ? 'warning' : 'success')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('ignoreItems')
                    ->label('Не учитывать выбранные')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $reviewer = Filament::auth()->user();
                        foreach ($records as $record) {
                            app(B2bSpecificationItemDecision::class)->ignore($record, $reviewer);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Табличные позиции не извлечены')
            ->emptyStateDescription('PDF и изображения менеджер обрабатывает вручную. Для Excel и CSV позиции появятся автоматически.');
    }
}
