<?php

namespace App\Filament\Resources\MarketAnalysis;

use App\Filament\Resources\MarketAnalysis\Pages\ListMarketAnalysis;
use App\Filament\Resources\MarketPriceObservations\MarketPriceObservationResource;
use App\Models\MarketPriceSource;
use App\Models\Product;
use App\Services\Market\MarketPriceIndicator;
use App\Services\Market\MarketPriceRecommendation;
use App\Services\Market\MarketPriceSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketAnalysisResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'market-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Рынок и цены';

    protected static ?string $modelLabel = 'рыночная позиция';

    protected static ?string $pluralModelLabel = 'Рынок и цены';

    protected static ?int $navigationSort = 1;

    /** @var \WeakMap<Product, array<string, mixed>>|null */
    private static ?\WeakMap $summaryCache = null;

    /** @var \WeakMap<Product, array<string, mixed>>|null */
    private static ?\WeakMap $indicatorCache = null;

    /** @var \WeakMap<Product, array<string, mixed>>|null */
    private static ?\WeakMap $recommendationCache = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('marketPriceObservations')
            ->with([
                'category:id,name,slug',
                'marketPriceObservations.source',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Товар kotlov.by')
                    ->description(fn (Product $record): ?string => $record->sku)
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(55)
                    ->wrap(),
                TextColumn::make('category.name')->label('Категория')->badge()->sortable(),
                TextColumn::make('price')
                    ->label('Наша цена')
                    ->money('BYN')
                    ->sortable(),
                TextColumn::make('market_position')
                    ->label('Позиция на рынке')
                    ->state(fn (Product $record): string => self::summary($record)['label'])
                    ->description(function (Product $record): string {
                        $summary = self::summary($record);
                        if ($summary['status'] !== 'ready') {
                            return $summary['reason'];
                        }

                        return 'Медиана '.number_format($summary['median'], 2, ',', ' ')
                            .' BYN · '.$summary['sources_count'].' источника';
                    })
                    ->badge()
                    ->color(fn (Product $record): string => match (self::summary($record)['position']) {
                        'above' => 'danger',
                        'below' => 'info',
                        'market' => 'success',
                        default => 'gray',
                    })
                    ->wrap(),
                TextColumn::make('market_range')
                    ->label('Коридор')
                    ->state(function (Product $record): string {
                        $summary = self::summary($record);

                        return $summary['minimum'] === null
                            ? '—'
                            : number_format($summary['minimum'], 2, ',', ' ').'–'
                                .number_format($summary['maximum'], 2, ',', ' ').' BYN';
                    })
                    ->description(fn (Product $record): string => self::summary($record)['offers_count'].' свежих предложений'),
                TextColumn::make('market_delta')
                    ->label('Отклонение')
                    ->state(function (Product $record): string {
                        $summary = self::summary($record);
                        if ($summary['delta_percent'] === null) {
                            return '—';
                        }

                        $sign = $summary['delta_byn'] > 0 ? '+' : '';

                        return $sign.number_format($summary['delta_byn'], 2, ',', ' ').' BYN / '
                            .$sign.number_format($summary['delta_percent'], 1, ',', ' ').'%';
                    })
                    ->badge()
                    ->color(fn (Product $record): string => match (self::summary($record)['position']) {
                        'above' => 'danger',
                        'below' => 'info',
                        'market' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('manager_warning')
                    ->label('Сигнал менеджеру')
                    ->state(fn (Product $record): string => self::indicator($record)['primary_warning']['label'] ?? 'Без рыночных рисков')
                    ->description(fn (Product $record): string => self::indicator($record)['primary_warning']['description'] ?? 'Цена находится в подтверждённом рыночном коридоре.')
                    ->badge()
                    ->color(fn (Product $record): string => self::indicator($record)['primary_warning']['color'] ?? 'success')
                    ->wrap(),
                TextColumn::make('price_recommendation')
                    ->label('Рекомендация')
                    ->state(fn (Product $record): string => self::recommendation($record)['label'])
                    ->description(function (Product $record): string {
                        $recommendation = self::recommendation($record);

                        return $recommendation['recommended_price'] === null
                            ? $recommendation['reason']
                            : number_format($recommendation['recommended_price'], 2, ',', ' ').' BYN · маржа '
                                .number_format($recommendation['recommended_margin_percent'], 1, ',', ' ').'%';
                    })
                    ->badge()
                    ->color(fn (Product $record): string => match (self::recommendation($record)['status']) {
                        'ready' => self::recommendation($record)['can_apply'] ? 'info' : 'success',
                        'margin_conflict' => 'danger',
                        default => 'warning',
                    })
                    ->wrap(),
                TextColumn::make('market_checked_at')
                    ->label('Проверено')
                    ->state(fn (Product $record): string => self::summary($record)['latest_observed_at']?->diffForHumans() ?? 'Нет свежих данных')
                    ->description(fn (Product $record): ?string => self::summary($record)['latest_observed_at']?->format('d.m.Y H:i')),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Категория')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('market_source')
                    ->label('Источник рынка')
                    ->options(fn (): array => MarketPriceSource::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('marketPriceObservations', fn (Builder $observations) => $observations
                            ->where('market_price_source_id', $data['value']))
                        : $query),
            ])
            ->emptyStateIcon(Heroicon::OutlinedChartBarSquare)
            ->emptyStateHeading('Рыночных наблюдений пока нет')
            ->emptyStateDescription('Добавьте разрешённые источники и подтверждённые предложения. Закупочные цены поставщиков сюда не попадают.')
            ->recordActions([
                ActionGroup::make([
                    Action::make('recommendation_details')
                        ->label('Расчёт рекомендации')
                        ->icon(Heroicon::OutlinedCalculator)
                        ->modalHeading(fn (Product $record): string => 'Рекомендация цены · '.$record->name)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Закрыть')
                        ->modalContent(fn (Product $record) => view('filament.market.price-recommendation', [
                            'recommendation' => self::recommendation($record),
                        ])),
                    Action::make('apply_recommended_price')
                        ->label('Применить рекомендованную цену')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('warning')
                        ->visible(fn (Product $record): bool => (auth()->user()?->isAdmin() ?? false)
                            && self::recommendation($record)['status'] === 'ready'
                            && self::recommendation($record)['can_apply'])
                        ->modalHeading(fn (Product $record): string => 'Подтвердить цену для '.$record->name)
                        ->modalDescription(fn (Product $record): string => 'Цена изменится с '
                            .number_format((float) $record->price, 2, ',', ' ').' на '
                            .number_format((float) self::recommendation($record)['recommended_price'], 2, ',', ' ').' BYN. Действие будет записано в аудит.')
                        ->form([
                            Textarea::make('reason')
                                ->label('Причина решения')
                                ->placeholder('Почему подтверждена эта цена')
                                ->required()
                                ->minLength(10)
                                ->rows(3),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Product $record, array $data): void {
                            app(MarketPriceRecommendation::class)->apply(
                                $record,
                                auth()->user(),
                                $data['reason'],
                                request()->ip(),
                                request()->userAgent(),
                            );
                            self::forgetCachedAnalysis($record);
                            Notification::make()->success()->title('Цена обновлена')->body('Решение и рыночные доказательства сохранены в аудите.')->send();
                        }),
                    Action::make('observations')
                        ->label('Доказательства')
                        ->icon(Heroicon::OutlinedListBullet)
                        ->color('info')
                        ->url(fn (Product $record): string => MarketPriceObservationResource::getUrl('index', [
                            'filters' => ['product_id' => ['value' => $record->id]],
                        ])),
                    Action::make('site')
                        ->label('Карточка сайта')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Product $record): ?string => $record->category?->slug && $record->slug
                            ? url('/'.$record->category->slug.'/'.$record->slug)
                            : null)
                        ->openUrlInNewTab(),
                ])->label('Действия')->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isManager() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isManager() ?? false;
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
        return ['index' => ListMarketAnalysis::route('/')];
    }

    /** @return array<string, mixed> */
    private static function summary(Product $product): array
    {
        self::$summaryCache ??= new \WeakMap;

        return self::$summaryCache[$product]
            ??= app(MarketPriceSummary::class)->forProduct($product);
    }

    /** @return array<string, mixed> */
    private static function indicator(Product $product): array
    {
        self::$indicatorCache ??= new \WeakMap;

        return self::$indicatorCache[$product]
            ??= app(MarketPriceIndicator::class)->forProduct($product);
    }

    /** @return array<string, mixed> */
    private static function recommendation(Product $product): array
    {
        self::$recommendationCache ??= new \WeakMap;

        return self::$recommendationCache[$product]
            ??= app(MarketPriceRecommendation::class)->forProduct($product);
    }

    private static function forgetCachedAnalysis(Product $product): void
    {
        foreach ([self::$summaryCache, self::$indicatorCache, self::$recommendationCache] as $cache) {
            if ($cache?->offsetExists($product)) {
                unset($cache[$product]);
            }
        }
    }
}
