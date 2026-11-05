<?php

namespace App\Filament\Resources\CityStorefronts;

use App\Filament\Resources\CityStorefronts\Pages\EditCityStorefront;
use App\Filament\Resources\CityStorefronts\Pages\ListCityStorefronts;
use App\Filament\Resources\PartnerBranches\PartnerBranchResource;
use App\Models\City;
use App\Models\PartnerBranch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CityStorefrontResource extends Resource
{
    protected static ?string $model = City::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Городские сайты';
    protected static ?string $modelLabel = 'городской сайт';
    protected static ?string $pluralModelLabel = 'Городские сайты';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'city-storefronts';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string { return 'B2B'; }
    public static function canViewAny(): bool { return auth()->user()?->canManageB2bOrders() ?? false; }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canCreate(): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('slug', '!=', 'minsk')->withCount('partnerBranches');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Городской сайт KOTLOV')
                ->description('Единый каталог и оформление KOTLOV с местными контактами, фотографиями и SEO. Адрес формируется по городу; собственный домен партнёра здесь не используется.')
                ->schema([
                    Placeholder::make('domain')->label('Адрес сайта')->content(fn (City $record): string => $record->storefrontUrl()),
                    Placeholder::make('publication')->label('Сейчас')->content(fn (City $record): string => $record->storefront_enabled ? 'Публикация включена' : 'Не опубликован'),
                    Select::make('primary_partner_branch_id')->label('Главный филиал городского сайта')
                        ->options(fn (City $record): array => PartnerBranch::query()->visibleIn($record)->with('client')->get()
                            ->mapWithKeys(fn (PartnerBranch $branch): array => [$branch->id => $branch->name.' — '.$branch->address])->all())
                        ->searchable()->helperText('Его фото, описание и контакты используются на главной и в шапке. Остальные активные точки города остаются в списке магазинов.'),
                    Toggle::make('storefront_enabled')->label('Опубликовать городской сайт')
                        ->helperText('Выключение скрывает весь городской сайт и переход к нему в выборе города. Карточки магазинов в «Где купить» управляются отдельно.'),
                    Placeholder::make('preparation')->label('Подготовка к публикации')
                        ->content('Добавьте филиал в карточке B2B-клиента: город, адрес, телефон, фото, описание и SEO. Затем выберите его главным и включите публикацию. Адрес сайта закреплён за городом. Один городской сайт объединяет все его активные точки продаж.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->description('Все городские адреса KOTLOV. Для нового города сначала добавьте точку продаж в карточке партнёра.')
            ->columns([
                TextColumn::make('name')->label('Город')->searchable()->sortable(),
                TextColumn::make('slug')->label('Адрес сайта')->formatStateUsing(fn (City $record): string => $record->storefrontUrl()),
                TextColumn::make('storefront_enabled')->label('Публикация')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Включена' : 'Выключена')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                TextColumn::make('partner_branches_count')->label('Точек продаж')->numeric(),
            ])->filters([TernaryFilter::make('storefront_enabled')->label('Опубликован')])
            ->recordActions([
                EditAction::make()->label('Управлять сайтом'),
                Action::make('open')->label('Открыть')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (City $record): string => $record->storefrontUrl())->openUrlInNewTab()
                    ->visible(fn (City $record): bool => $record->storefront_enabled),
                Action::make('branches')->label('Магазины')->url(fn (City $record): string => PartnerBranchResource::getUrl('index', ['filters' => ['city_id' => ['value' => $record->id]]])),
            ])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => ListCityStorefronts::route('/'), 'edit' => EditCityStorefront::route('/{record}/edit')];
    }
}
