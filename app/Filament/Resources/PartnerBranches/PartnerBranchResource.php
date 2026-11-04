<?php

namespace App\Filament\Resources\PartnerBranches;

use App\Filament\Resources\PartnerBranches\Pages\CreatePartnerBranch;
use App\Filament\Resources\PartnerBranches\Pages\EditPartnerBranch;
use App\Filament\Resources\PartnerBranches\Pages\ListPartnerBranches;
use App\Models\PartnerBranch;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerBranchResource extends Resource
{
    protected static ?string $model = PartnerBranch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Филиалы партнёров';

    protected static ?string $modelLabel = 'филиал или точка продаж';

    protected static ?string $pluralModelLabel = 'Филиалы и точки продаж';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageB2bOrders() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('b2b_client_id')->label('Партнёр — B2B-клиент')
                ->relationship('client', 'company_name', fn (Builder $query) => $query->where('status', 'approved'))
                ->searchable()->preload()->required()
                ->helperText('Точку продаж получает только одобренный B2B-клиент с партнёрским соглашением.'),
            ...static::branchFields(),
        ])->columns(2);
    }

    /** Fields shared with the «Филиалы и точки продаж» tab of a B2B client card. */
    public static function branchFields(): array
    {
        return [
            Select::make('city_id')->label('Город')->relationship('city', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label('Название филиала на сайте')->placeholder('Магазин «Печкiн» (ООО «СБГ бай»)')->required()->maxLength(255),
            TextInput::make('address')->label('Адрес')->placeholder('г. Минск, ул. Селицкого, 39Б')->maxLength(255),
            TagsInput::make('phones')->label('Телефоны')->placeholder('+375 (29) 000-00-00')->required()
                ->helperText('Каждый номер — отдельно, Enter после ввода.'),
            TextInput::make('working_hours')->label('Режим работы')->placeholder('Пн–Пт 9:00–18:00')->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            TextInput::make('map_url')->label('Ссылка на карту')->url()->maxLength(1000),
            TextInput::make('sort_order')->label('Порядок в списке')->numeric()->minValue(0)->default(100)->required(),
            Toggle::make('is_active')->label('Показывать на сайте')->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['city', 'client'])
                ->withCount(['contactViews', 'contactViews as contact_views_30d_count' => fn (Builder $views) => $views->where('created_at', '>=', now()->subDays(30))]))
            ->columns([
                TextColumn::make('name')->label('Точка продаж')->searchable()->weight('bold')
                    ->description(fn (PartnerBranch $record): string => (string) $record->address),
                TextColumn::make('city.name')->label('Город')->sortable(),
                TextColumn::make('client.company_name')->label('Партнёр')->placeholder('Не привязан — скрыта')
                    ->description(fn (PartnerBranch $record): ?string => $record->client && $record->client->status !== 'approved' ? 'B2B-доступ не активен — точка скрыта' : null),
                TextColumn::make('phones')->label('Телефоны')->formatStateUsing(fn ($state): string => implode(', ', (array) $state)),
                TextColumn::make('contact_views_30d_count')->label('Показы телефона, 30 дн.')->numeric()->sortable(),
                TextColumn::make('contact_views_count')->label('Всего')->numeric()->sortable(),
                IconColumn::make('is_active')->label('На сайте')->boolean(),
            ])
            ->filters([
                SelectFilter::make('city_id')->label('Город')->relationship('city', 'name')->searchable()->preload(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerBranches::route('/'),
            'create' => CreatePartnerBranch::route('/create'),
            'edit' => EditPartnerBranch::route('/{record}/edit'),
        ];
    }
}
