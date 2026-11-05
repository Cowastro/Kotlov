<?php

namespace App\Filament\Resources\PartnerBranches;

use App\Filament\Resources\PartnerBranches\Pages\CreatePartnerBranch;
use App\Filament\Resources\PartnerBranches\Pages\EditPartnerBranch;
use App\Filament\Resources\PartnerBranches\Pages\ListPartnerBranches;
use App\Models\PartnerBranch;
use App\Models\City;
use App\Filament\Resources\CityStorefronts\CityStorefrontResource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use App\Services\PartnerBranchModeration;
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

    public static function getNavigationBadge(): ?string
    {
        $count = PartnerBranch::whereNotNull('changes_submitted_at')->count();
        return $count ? (string) $count : null;
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
            Select::make('city_id')->label('Город')->relationship('city', 'name')->searchable()->preload()->required()->live(),
            Placeholder::make('city_site')->label('Городской сайт')
                ->content(fn (Get $get): string => ($city = City::find($get('city_id')))
                    ? $city->storefrontUrl().' — '.($city->storefront_enabled ? 'публикация включена' : 'не опубликован')
                    : 'Выберите город. После сохранения откройте «Управлять сайтом города».'),
            TextInput::make('name')->label('Название филиала на сайте')->placeholder('Магазин «Печкiн» (ООО «СБГ бай»)')->required()->maxLength(255),
            TextInput::make('address')->label('Адрес')->placeholder('г. Минск, ул. Селицкого, 39Б')->required()->maxLength(255),
            TagsInput::make('phones')->label('Телефоны')->placeholder('+375 (29) 000-00-00')->required()
                ->helperText('Каждый номер — отдельно, Enter после ввода.'),
            TextInput::make('working_hours')->label('Режим работы')->placeholder('Пн–Пт 9:00–18:00, Сб–Вс — выходной')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            TextInput::make('map_url')->label('Ссылка на карту')->url()->maxLength(1000),
            TextInput::make('map_embed_url')->label('Ссылка для встраивания карты')->url()->maxLength(1000)
                ->helperText('Необязательно. Используется для карты прямо на городской странице.'),
            TextInput::make('storefront_title')->label('Заголовок локальной витрины')->maxLength(255)
                ->placeholder('Магазин отопления KOTLOV в Борисове')->columnSpanFull(),
            Textarea::make('storefront_intro')->label('Краткое описание магазина')->rows(3)->maxLength(1200)->columnSpanFull(),
            Textarea::make('storefront_promo')->label('Рекламное предложение филиала')->rows(3)->maxLength(1500)
                ->helperText('Акция, услуги, преимущества или условия получения товара в этом городе.')->columnSpanFull(),
            FileUpload::make('hero_image')->label('Главная фотография магазина')->image()->disk('public')
                ->directory('partner-branches')->visibility('public')->columnSpanFull(),
            FileUpload::make('gallery')->label('Фотографии магазина')->image()->multiple()->reorderable()
                ->maxFiles(12)->disk('public')->directory('partner-branches')->visibility('public')->columnSpanFull(),
            TextInput::make('seo_title')->label('SEO title городской страницы')->maxLength(70)->columnSpanFull(),
            Textarea::make('seo_description')->label('SEO description городской страницы')->rows(2)->maxLength(180)->columnSpanFull(),
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
                static::moderationColumn(),
                TextColumn::make('city.storefront_enabled')->label('Сайт города')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Опубликован' : 'Не опубликован'),
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
            ->recordActions([EditAction::make(), ...static::reviewActions(), static::citySiteAction()]);
    }

    public static function moderationColumn(): TextColumn
    {
        return TextColumn::make('moderation_status')->label('Изменения партнёра')->badge()
            ->state(fn (PartnerBranch $record): string => $record->changes_submitted_at ? 'На проверке' : ($record->changes_rejection_reason ? 'На доработке' : 'Нет заявки'))
            ->color(fn (PartnerBranch $record): string => $record->changes_submitted_at ? 'warning' : 'gray');
    }

    public static function reviewActions(): array
    {
        return [
            Action::make('previewStore')->label('Предпросмотр магазина')->icon('heroicon-o-eye')
                ->url(fn (PartnerBranch $record): string => route('b2b.branches.preview', $record))->openUrlInNewTab(),
            Action::make('approveChanges')->label('Одобрить магазин')->color('success')->icon('heroicon-o-check-circle')
                ->visible(fn (PartnerBranch $record): bool => (bool) $record->changes_submitted_at)
                ->requiresConfirmation()->modalDescription('Сначала откройте предпросмотр. Данные из заявки заменят опубликованную версию магазина. Городской сайт включается отдельно.')
                ->action(function (PartnerBranch $record): void {
                    app(PartnerBranchModeration::class)->approve($record, auth()->user());
                    Notification::make()->success()->title('Данные магазина опубликованы')->send();
                }),
            Action::make('returnChanges')->label('Вернуть на доработку')->color('warning')
                ->visible(fn (PartnerBranch $record): bool => (bool) $record->changes_submitted_at)
                ->schema([Textarea::make('reason')->label('Что нужно уточнить')->required()->maxLength(1000)])
                ->action(function (PartnerBranch $record, array $data): void {
                    $record->forceFill(['changes_submitted_at' => null, 'changes_rejection_reason' => $data['reason']])->save();
                    Notification::make()->success()->title('Замечание сохранено в кабинете партнёра')->send();
                }),
        ];
    }

    public static function citySiteAction(): Action
    {
        return Action::make('citySite')->label('Управлять сайтом города')->icon('heroicon-o-globe-alt')
            ->visible(fn (PartnerBranch $record): bool => $record->city_id && $record->city?->slug !== 'minsk')
            ->url(fn (PartnerBranch $record): string => CityStorefrontResource::getUrl('edit', ['record' => $record->city_id]));
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
