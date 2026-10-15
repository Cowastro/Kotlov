<?php

namespace App\Filament\Resources\MarketResearchReports;

use App\Filament\Resources\MarketResearchReports\Pages\ListMarketResearchReports;
use App\Models\MarketResearchReport;
use App\Services\Market\MarketResearchReportPresenter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketResearchReportResource extends Resource
{
    protected static ?string $model = MarketResearchReport::class;

    protected static ?string $slug = 'market-research-archive';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Архив анализа рынка';

    protected static ?string $modelLabel = 'анализ рынка';

    protected static ?string $pluralModelLabel = 'Архив анализа рынка';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product.brand', 'product.category']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('applied_at')->label('Сохранено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('product.name')->label('Товар')->description(fn (MarketResearchReport $record): ?string => $record->product?->sku)->searchable()->wrap()->limit(55),
                TextColumn::make('batch_code')->label('Пакет')->searchable()->copyable(),
                TextColumn::make('decision')->label('Решение')->badge()
                    ->formatStateUsing(fn (string $state): string => MarketResearchReport::DECISIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'price_applied' => 'info',
                        'price_unchanged' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('price_before')->label('Было')->money('BYN')->placeholder('—'),
                TextColumn::make('recommended_price')->label('Рекомендация')->money('BYN')->placeholder('—'),
                TextColumn::make('price_after')->label('Стало')->money('BYN')->placeholder('—'),
                TextColumn::make('confidence')->label('Уверенность')->badge(),
                TextColumn::make('observations_count')->label('Предложения')
                    ->state(fn (MarketResearchReport $record): int => count($record->observations_snapshot ?? []))
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('decision')->label('Решение')->options(MarketResearchReport::DECISIONS),
                SelectFilter::make('product_id')->label('Товар')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Открыть анализ')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->modalHeading(fn (MarketResearchReport $record): string => 'Анализ рынка · '.($record->product?->name ?? $record->batch_code))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(function (MarketResearchReport $record) {
                        return view('filament.market.research-report-details', [
                            'report' => $record,
                            ...app(MarketResearchReportPresenter::class)->present($record),
                        ]);
                    }),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (MarketResearchReport $record): string => route('admin.market-research-reports.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('applied_at', 'desc');
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

    public static function getPages(): array
    {
        return ['index' => ListMarketResearchReports::route('/')];
    }
}
