<?php

namespace App\Filament\Resources\IntegrationIssues;

use App\Filament\Resources\IntegrationIssues\Pages\ListIntegrationIssues;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\IntegrationIssue;
use App\Models\Order;
use App\Models\Product;
use App\Services\Integrations\IntegrationIssueAdvisor;
use App\Services\Integrations\IntegrationIssueAiAdvisor;
use App\Services\Integrations\IntegrationOrderStatusMapper;
use App\Services\Integrations\IntegrationOrderStatusRuleManager;
use App\Services\Integrations\IntegrationProductIssueResolver;
use App\Services\Integrations\IntegrationProductMatchAdvisor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class IntegrationIssueResource extends Resource
{
    protected static ?string $model = IntegrationIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Требует внимания';

    protected static ?string $modelLabel = 'проблема интеграции';

    protected static ?string $pluralModelLabel = 'Требует внимания';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = IntegrationIssue::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return IntegrationIssue::query()
            ->where('status', 'open')
            ->where('severity', 'danger')
            ->exists() ? 'danger' : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['source', 'integrationProduct', 'order', 'assignee']))
            ->columns([
                TextColumn::make('severity')->label('Важность')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'danger' => 'Критично',
                        'info' => 'Информация',
                        default => 'Внимание',
                    })
                    ->color(fn (string $state): string => $state),
                TextColumn::make('title')->label('Проблема')->searchable()->weight('bold')
                    ->description(fn (IntegrationIssue $record): ?string => $record->message)
                    ->wrap(),
                TextColumn::make('source.name')->label('Источник')->placeholder('Сайт')->badge()->color('gray'),
                TextColumn::make('object')->label('Объект')
                    ->state(fn (IntegrationIssue $record): string => $record->order?->number
                        ?? $record->integrationProduct?->name
                        ?? 'Источник интеграции')
                    ->limit(55)
                    ->tooltip(fn (IntegrationIssue $record): string => $record->order?->number
                        ?? $record->integrationProduct?->name
                        ?? $record->source?->name
                        ?? '—'),
                TextColumn::make('recommended_action')->label('Следующий шаг')
                    ->state(fn (IntegrationIssue $record): string => self::displayAdvice($record)['title'])
                    ->description(fn (IntegrationIssue $record): ?string => self::displayAdvice($record)['steps'][0] ?? null)
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color(fn (IntegrationIssue $record): string => data_get($record->context, 'ai_advice.source') === 'ai'
                        ? 'primary'
                        : 'info')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('status')->label('Состояние')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'resolved' => 'Решено',
                        'ignored' => 'Игнорируется',
                        default => 'Открыто',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('assignee.name')->label('Ответственный')->placeholder('Не назначен')
                    ->toggleable(),
                TextColumn::make('last_detected_at')->label('Обнаружено')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')->label('Важность')->options([
                    'danger' => 'Критично',
                    'warning' => 'Внимание',
                    'info' => 'Информация',
                ]),
                SelectFilter::make('type')->label('Тип')->options([
                    'integration_stale' => 'Нет свежего обмена',
                    'integration_catalog_stale' => 'Нет каталога/остатков',
                    'integration_orders_stale' => '1С не забирает заказы',
                    'integration_statuses_stale' => 'Нет статусов из 1С',
                    'product_unmatched' => 'Товар не привязан',
                    'product_missing_category' => 'Нет категории',
                    'product_missing_price' => 'Нет цены',
                    'product_attention' => 'Товар требует решения',
                    'product_identity_collision' => 'Возможный дубль товара',
                    'catalog_all_stock_positive' => 'Все товары числятся в наличии',
                    'order_not_exported' => 'Заказ не передан',
                    'order_no_1c_response' => 'Нет ответа 1С',
                    'order_status_conflict' => 'Конфликт статусов заказа',
                    'order_status_unknown' => 'Неизвестный статус 1С',
                ]),
                SelectFilter::make('integration_source_id')->label('Источник')
                    ->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('assigned_to_user_id')->label('Ответственный')
                    ->relationship('assignee', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Состояние')->options([
                    'open' => 'Открыто',
                    'resolved' => 'Решено',
                    'ignored' => 'Игнорируется',
                ]),
            ])
            ->defaultSort('last_detected_at', 'desc')
            ->recordActions([
                Action::make('advice')
                    ->label('Что делать')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('info')
                    ->modalHeading(fn (IntegrationIssue $record): string => app(IntegrationIssueAdvisor::class)->advise($record)['title'])
                    ->form([
                        Placeholder::make('recommended_steps')
                            ->label('Рекомендуемые шаги')
                            ->content(function (IntegrationIssue $record): HtmlString {
                                $advice = app(IntegrationIssueAdvisor::class)->advise($record);

                                return new HtmlString(
                                    '<ol class="list-decimal space-y-2 ps-5">'.
                                    collect($advice['steps'])
                                        ->map(fn (string $step): string => '<li>'.e($step).'</li>')
                                        ->implode('').
                                    '</ol>'
                                );
                            }),
                        Placeholder::make('safety_note')
                            ->label('Важно')
                            ->content(fn (IntegrationIssue $record): string => app(IntegrationIssueAdvisor::class)->advise($record)['note']),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
                Action::make('aiAdvice')
                    ->label('ИИ-разбор')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Подготовить контекстную подсказку')
                    ->modalDescription(fn (): string => app(IntegrationIssueAiAdvisor::class)->isAvailable()
                        ? 'AI получит только технические поля проблемы — без имени, телефона, email и адреса клиента. Товары, заказы и привязки не изменятся.'
                        : 'AI-провайдер не настроен. Будет сохранён безопасный план локальных правил; товары, заказы и привязки не изменятся.')
                    ->modalSubmitActionLabel('Подготовить')
                    ->action(function (IntegrationIssue $record): void {
                        $advice = app(IntegrationIssueAiAdvisor::class)->advise($record);
                        $context = $record->context ?? [];
                        $context['ai_advice'] = [
                            ...$advice,
                            'generated_at' => now()->toIso8601String(),
                        ];
                        $record->update(['context' => $context]);

                        Notification::make()
                            ->success()
                            ->title($advice['source'] === 'ai' ? 'ИИ-подсказка готова' : 'Локальная подсказка обновлена')
                            ->body($advice['title'].' — '.($advice['steps'][0] ?? $advice['note']))
                            ->send();
                    }),
                Action::make('mapUnknownStatus')
                    ->label('Добавить правило')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('warning')
                    ->visible(fn (IntegrationIssue $record): bool => $record->type === 'order_status_unknown'
                        && filled($record->integration_source_id)
                        && (filled(data_get($record->context, 'unknown_status'))
                            || filled(data_get($record->context, 'unknown_payment_status'))))
                    ->modalHeading('Сопоставить неизвестный статус')
                    ->modalDescription(fn (IntegrationIssue $record): string => 'Правило сохранится только для источника «'
                        .($record->source?->partnerName() ?? $record->source?->name ?? 'не указан').'». Текущий заказ изменится только после следующего обмена.')
                    ->form([
                        Placeholder::make('raw_order_status')
                            ->label('Статус заказа из источника')
                            ->content(fn (IntegrationIssue $record): string => (string) data_get($record->context, 'unknown_status', '—'))
                            ->visible(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_status'))),
                        Select::make('order_target')
                            ->label('Статус заказа KOTLOV')
                            ->options(Order::STATUSES)
                            ->required(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_status')))
                            ->visible(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_status'))),
                        Placeholder::make('raw_payment_status')
                            ->label('Статус оплаты из источника')
                            ->content(fn (IntegrationIssue $record): string => (string) data_get($record->context, 'unknown_payment_status', '—'))
                            ->visible(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_payment_status'))),
                        Select::make('payment_target')
                            ->label('Статус оплаты KOTLOV')
                            ->options(IntegrationOrderStatusMapper::PAYMENT_STATUSES)
                            ->required(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_payment_status')))
                            ->visible(fn (IntegrationIssue $record): bool => filled(data_get($record->context, 'unknown_payment_status'))),
                    ])
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Сохранить правило')
                    ->action(function (IntegrationIssue $record, array $data): void {
                        $source = $record->source;
                        if (! $source) {
                            Notification::make()
                                ->danger()
                                ->title('Источник интеграции не найден')
                                ->body('Правило не сохранено. Обновите очередь проблем и проверьте источник.')
                                ->send();

                            return;
                        }

                        app(IntegrationOrderStatusRuleManager::class)->store(
                            $source,
                            data_get($record->context, 'unknown_status'),
                            $data['order_target'] ?? null,
                            data_get($record->context, 'unknown_payment_status'),
                            $data['payment_target'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Правило сопоставления сохранено')
                            ->body('Запустите повторный обмен статусами. Задача закроется автоматически после успешного применения.')
                            ->send();
                    }),
                Action::make('linkExistingProduct')
                    ->label('Привязать карточку')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('success')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open'
                        && filled($record->integration_product_id)
                        && data_get($record->context, 'unmatched') === true
                        && blank($record->integrationProduct?->product_id))
                    ->modalHeading('Привязать товар к существующей карточке')
                    ->modalDescription('Найдите карточку kotlov.by по названию или SKU. Связь сохранится для следующих обменов с этим поставщиком.')
                    ->form([
                        Placeholder::make('external_product')
                            ->label('Товар из внешней системы')
                            ->content(fn (IntegrationIssue $record): string => collect([
                                $record->integrationProduct?->name,
                                $record->integrationProduct?->external_sku
                                    ? 'арт. '.$record->integrationProduct->external_sku
                                    : null,
                                $record->integrationProduct?->price !== null
                                    ? number_format((float) $record->integrationProduct->price, 2, ',', ' ').' BYN'
                                    : 'цена не передана',
                                $record->integrationProduct?->formattedStockQuantity(),
                            ])->filter()->implode(' · ')),
                        Placeholder::make('automatic_candidates')
                            ->label('Найденные варианты')
                            ->content(fn (IntegrationIssue $record): HtmlString => self::candidateList($record)),
                        Select::make('product_id')
                            ->label('Карточка kotlov.by')
                            ->options(fn (IntegrationIssue $record): array => self::candidateOptions($record))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where(function ($query) use ($search): void {
                                    $query->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('sku', 'like', '%'.$search.'%');
                                })
                                ->orderBy('name')
                                ->limit(50)
                                ->get(['id', 'sku', 'name'])
                                ->mapWithKeys(fn (Product $product): array => [
                                    $product->id => self::productOptionLabel($product),
                                ])
                                ->all())
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $product = Product::query()->find($value, ['id', 'sku', 'name']);

                                return $product ? self::productOptionLabel($product) : null;
                            })
                            ->required()
                            ->helperText('Введите часть названия или SKU. Выбранная карточка сайта не будет изменена.'),
                        Placeholder::make('link_safety_note')
                            ->label('Что изменится')
                            ->content('Будет сохранена только постоянная привязка и закрыта эта задача. Название, категория, цена и остаток карточки сайта останутся без изменений.'),
                    ])
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Подтвердить привязку')
                    ->action(function (IntegrationIssue $record, array $data): void {
                        $product = app(IntegrationProductIssueResolver::class)
                            ->linkExistingProduct($record, (int) $data['product_id']);

                        if (! $product) {
                            Notification::make()
                                ->warning()
                                ->title('Задача или товар уже изменились')
                                ->body('Обновите очередь и проверьте привязку ещё раз.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Товар привязан, задача закрыта')
                            ->body('Связь с карточкой «'.$product->name.'» сохранена для следующих синхронизаций.')
                            ->send();
                    })
                    ->successRedirectUrl(fn (): string => self::getUrl('index', ['tab' => 'ready-to-link'])),
                Action::make('openObject')
                    ->label('Открыть')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (IntegrationIssue $record): bool => filled(self::objectUrl($record)))
                    ->url(fn (IntegrationIssue $record): ?string => self::objectUrl($record)),
                Action::make('claim')
                    ->label('Взять в работу')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('info')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open'
                        && $record->assigned_to_user_id !== (int) auth()->id())
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'assigned_to_user_id' => auth()->id(),
                    ])),
                Action::make('unclaim')
                    ->label('Снять с себя')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('gray')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open'
                        && $record->assigned_to_user_id === (int) auth()->id())
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'assigned_to_user_id' => null,
                    ])),
                Action::make('resolve')
                    ->label('Решено')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status === 'open')
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ])),
                Action::make('ignore')
                    ->label('Игнорировать')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (IntegrationIssue $record): bool => $record->status !== 'ignored')
                    ->requiresConfirmation()
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'ignored',
                        'resolved_at' => now(),
                    ])),
                Action::make('reopen')
                    ->label('Вернуть в работу')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (IntegrationIssue $record): bool => $record->status !== 'open')
                    ->action(fn (IntegrationIssue $record) => $record->update([
                        'status' => 'open',
                        'resolved_at' => null,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('claim')
                        ->label('Взять в работу')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->color('info')
                        ->action(fn (Collection $records) => $records->each->update([
                            'assigned_to_user_id' => auth()->id(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('resolve')
                        ->label('Отметить решёнными')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Игнорировать')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => 'ignored',
                            'resolved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListIntegrationIssues::route('/')];
    }

    private static function objectUrl(IntegrationIssue $issue): ?string
    {
        return match (true) {
            filled($issue->integration_product_id) => IntegrationProductResource::getUrl('edit', ['record' => $issue->integration_product_id]),
            filled($issue->order_id) => OrderResource::getUrl('view', ['record' => $issue->order_id]),
            filled($issue->integration_source_id) => IntegrationSourceResource::getUrl('edit', ['record' => $issue->integration_source_id]),
            default => null,
        };
    }

    private static function productOptionLabel(Product $product): string
    {
        return collect([
            $product->sku ? 'SKU '.$product->sku : null,
            $product->name,
        ])->filter()->implode(' — ');
    }

    /** @return array<int, string> */
    private static function candidateOptions(IntegrationIssue $issue): array
    {
        if (! $issue->integrationProduct) {
            return [];
        }

        return collect(app(IntegrationProductMatchAdvisor::class)->candidates($issue->integrationProduct))
            ->mapWithKeys(fn (array $candidate): array => [
                $candidate['product_id'] => collect([
                    round($candidate['confidence'] * 100).'%',
                    filled($candidate['product_sku']) ? 'SKU '.$candidate['product_sku'] : null,
                    $candidate['product_name'],
                    filled($candidate['category_name']) ? '('.$candidate['category_name'].')' : null,
                ])->filter()->implode(' · '),
            ])
            ->all();
    }

    private static function candidateList(IntegrationIssue $issue): HtmlString
    {
        if (! $issue->integrationProduct) {
            return new HtmlString('<p class="text-sm text-gray-500">Внешний товар больше не найден.</p>');
        }

        $candidates = app(IntegrationProductMatchAdvisor::class)->candidates($issue->integrationProduct);
        if ($candidates === []) {
            return new HtmlString('<p class="text-sm text-gray-500">Автоматические варианты не найдены. Используйте поиск по названию или SKU ниже.</p>');
        }

        $items = collect($candidates)->map(function (array $candidate): string {
            $label = collect([
                '<strong>'.e(round($candidate['confidence'] * 100).'%').'</strong>',
                filled($candidate['product_sku']) ? 'SKU '.e($candidate['product_sku']) : null,
                e($candidate['product_name']),
                filled($candidate['category_name']) ? 'Категория: '.e($candidate['category_name']) : null,
            ])->filter()->implode(' · ');

            if (filled($candidate['public_url'])) {
                $label .= ' · <a class="text-primary-600 underline" href="'.e($candidate['public_url']).'" target="_blank" rel="noopener noreferrer">открыть карточку</a>';
            }

            return '<li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">'.$label.'</li>';
        })->implode('');

        return new HtmlString('<ol class="space-y-2">'.$items.'</ol>');
    }

    /** @return array{title:string,steps:array<int,string>,note:string} */
    private static function displayAdvice(IntegrationIssue $issue): array
    {
        $cached = data_get($issue->context, 'ai_advice');

        if (is_array($cached) && filled($cached['title'] ?? null) && is_array($cached['steps'] ?? null)) {
            return [
                'title' => (string) $cached['title'],
                'steps' => array_values(array_filter($cached['steps'], 'is_string')),
                'note' => (string) ($cached['note'] ?? ''),
            ];
        }

        return app(IntegrationIssueAdvisor::class)->advise($issue);
    }
}
