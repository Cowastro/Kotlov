<?php

namespace App\Filament\Resources\MarketResearchReports;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\MarketResearchReports\Pages\ListMarketResearchReports;
use App\Models\CrmMailbox;
use App\Models\MarketResearchReport;
use App\Models\Supplier;
use App\Services\CrmMail\CrmMailSender;
use App\Services\Market\MarketResearchReportPresenter;
use App\Services\Market\MarketResearchSupplierInquiry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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
                Action::make('request_b2b_price')
                    ->label('Запросить B2B-цену')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('primary')
                    ->modalWidth('4xl')
                    ->modalHeading(fn (MarketResearchReport $record): string => 'Запрос партнёрской цены · '.($record->product?->name ?? 'товар'))
                    ->modalDescription('Можно выбрать поставщика из базы или указать произвольную компанию и email вручную.')
                    ->modalSubmitActionLabel('Создать и отправить запрос')
                    ->fillForm(fn (MarketResearchReport $record): array => app(MarketResearchSupplierInquiry::class)->formData($record))
                    ->form([
                        Select::make('supplier_id')
                            ->label('Поставщик из базы')
                            ->options(fn (): array => app(MarketResearchSupplierInquiry::class)->supplierOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->placeholder('Необязательно')
                            ->helperText('Если нужной компании нет, оставьте поле пустым и укажите её данные ниже.')
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $supplier = Supplier::query()
                                    ->with('crmCounterparty.contactPoints')
                                    ->find($state);
                                $set('recipient', $supplier?->contactEmailAddresses()[0] ?? '');
                                $set('participant_name', $supplier?->name ?? '');
                            }),
                        Select::make('mailbox_id')
                            ->label('Отправить из ящика')
                            ->options(fn (): array => CrmMailbox::query()->where('is_enabled', true)->pluck('name', 'id')->all())
                            ->required(),
                        TextInput::make('recipient')
                            ->label('Email поставщика')
                            ->email()
                            ->required(),
                        TextInput::make('participant_name')
                            ->label('Компания или контакт')
                            ->placeholder('Необязательно')
                            ->maxLength(255),
                        TagsInput::make('cc')
                            ->label('Копия (CC)')
                            ->placeholder('copy@example.com')
                            ->helperText('Можно указать несколько адресов. После каждого адреса нажмите Enter.'),
                        TextInput::make('subject')
                            ->label('Тема')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->label('Текст запроса')
                            ->rows(16)
                            ->required()
                            ->maxLength(30000),
                        Toggle::make('send_now')
                            ->label('Отправить сразу')
                            ->helperText('Если выключить, письмо сохранится в почте как черновик для дополнительной проверки.')
                            ->default(true),
                    ])
                    ->action(function (MarketResearchReport $record, array $data) {
                        try {
                            $supplier = filled($data['supplier_id'] ?? null)
                                ? Supplier::query()->find((int) $data['supplier_id'])
                                : null;
                            $mailbox = CrmMailbox::query()->findOrFail((int) $data['mailbox_id']);
                            $user = auth()->user();
                            $thread = app(MarketResearchSupplierInquiry::class)->createDraft(
                                $record,
                                $supplier,
                                $mailbox,
                                $user,
                                (string) $data['recipient'],
                                (array) ($data['cc'] ?? []),
                                $data['participant_name'] ?? null,
                                (string) $data['subject'],
                                (string) $data['body'],
                            );

                            if ((bool) ($data['send_now'] ?? false)) {
                                app(CrmMailSender::class)->sendDraft($thread, $user);
                                Notification::make()->success()->title('Запрос B2B-цены отправлен')->body($supplier?->name ?: (string) $data['recipient'])->send();
                            } else {
                                Notification::make()->success()->title('Черновик запроса создан')->body($supplier?->name ?: (string) $data['recipient'])->send();
                            }

                            return redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось подготовить запрос')->body($exception->getMessage())->send();

                            return null;
                        }
                    }),
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
