<?php

namespace App\Filament\Resources\PublicAiSelectionRuns;

use App\Filament\Resources\PublicAiSelectionRuns\Pages\ListPublicAiSelectionRuns;
use App\Filament\Resources\PublicAiSelectionRuns\Pages\ViewPublicAiSelectionRun;
use App\Models\PublicAiSelectionRun;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PublicAiSelectionRunResource extends Resource
{
    protected static ?string $model = PublicAiSelectionRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'AI-поиск';

    protected static ?string $modelLabel = 'AI-подбор';

    protected static ?string $pluralModelLabel = 'Статистика AI-поиска';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PublicAiSelectionRun::query()
            ->whereIn('status', ['no_results', 'abandoned'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['catalogCategory', 'contactRequest', 'selectedProduct']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('intent')->label('Что искали')->searchable()->wrap()->limit(100)
                    ->tooltip(fn (PublicAiSelectionRun $record): string => $record->intent),
                TextColumn::make('catalogCategory.name')->label('Раздел')->placeholder(fn (PublicAiSelectionRun $record): string => self::categoryLabel($record->category))
                    ->wrap()->sortable(),
                TextColumn::make('answers')->label('Параметры')
                    ->formatStateUsing(fn ($state): string => self::formatAnswers($state))->wrap()->limit(90)->placeholder('—'),
                TextColumn::make('result_count')->label('Найдено')->numeric()->sortable(),
                TextColumn::make('selectedProduct.name')->label('Открыл товар')->placeholder('—')->wrap()->limit(60),
                TextColumn::make('status')->label('Итог')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => PublicAiSelectionRun::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'lead_captured' => 'success', 'product_opened' => 'info',
                        'no_results', 'abandoned' => 'danger', 'completed' => 'warning', default => 'gray',
                    }),
                TextColumn::make('contactRequest.phone')->label('Контакт')->copyable()->placeholder('—')
                    ->description(fn (PublicAiSelectionRun $record): ?string => $record->contactRequest?->name),
            ])
            ->filters([
                SelectFilter::make('status')->label('Итог')->options(PublicAiSelectionRun::STATUSES),
                SelectFilter::make('catalog_category_id')->label('Раздел')->relationship('catalogCategory', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('Поисков пока нет')
            ->emptyStateDescription('Здесь появятся запросы посетителей из умного подбора.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Запрос посетителя')->columns(2)->schema([
                TextEntry::make('intent')->label('Что искал')->columnSpanFull(),
                TextEntry::make('catalogCategory.name')->label('Категория каталога')
                    ->placeholder(fn (PublicAiSelectionRun $record): string => self::categoryLabel($record->category)),
                TextEntry::make('status')->label('Итог')->badge()
                    ->formatStateUsing(fn (string $state): string => PublicAiSelectionRun::STATUSES[$state] ?? $state),
                TextEntry::make('answers')->label('Выбранные параметры')
                    ->formatStateUsing(fn ($state): string => self::formatAnswers($state))->placeholder('Не указаны')->columnSpanFull(),
                TextEntry::make('result_count')->label('Найдено товаров'),
                TextEntry::make('selectedProduct.name')->label('Открытый товар')->placeholder('Не открывал'),
                TextEntry::make('page_url')->label('Страница начала поиска')->url(fn ($state) => $state ?: null)->openUrlInNewTab()->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Контакт и этапы')->columns(3)->schema([
                TextEntry::make('contactRequest.name')->label('Имя')->placeholder('Не оставил'),
                TextEntry::make('contactRequest.phone')->label('Телефон')->copyable()->placeholder('Не оставил'),
                TextEntry::make('current_step')->label('Последний этап'),
                TextEntry::make('created_at')->label('Начал')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('recommended_at')->label('Получил подбор')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('lead_prompted_at')->label('Открыл форму контакта')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('product_opened_at')->label('Открыл товар')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('contact_submitted_at')->label('Оставил контакт')->dateTime('d.m.Y H:i:s')->placeholder('—'),
                TextEntry::make('abandoned_at')->label('Ушёл')->dateTime('d.m.Y H:i:s')->placeholder('—'),
            ]),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicAiSelectionRuns::route('/'),
            'view' => ViewPublicAiSelectionRun::route('/{record}'),
        ];
    }

    private static function formatAnswers(mixed $answers): string
    {
        $values = is_array($answers) ? $answers : [];

        return collect($values)->map(fn ($value, $key): string => Str::headline((string) $key).': '.$value)->join(' · ');
    }

    private static function categoryLabel(?string $category): string
    {
        return match ($category) {
            'gas_boiler' => 'Газовые котлы', 'boiler' => 'Котлы', 'heat_pump' => 'Тепловые насосы',
            'pump' => 'Насосы', 'gas_meter' => 'Счётчики газа', default => 'Общий подбор',
        };
    }
}
