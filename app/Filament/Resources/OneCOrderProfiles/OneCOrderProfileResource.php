<?php

namespace App\Filament\Resources\OneCOrderProfiles;

use App\Filament\Resources\OneCOrderProfiles\Pages\EditOneCOrderProfile;
use App\Filament\Resources\OneCOrderProfiles\Pages\ListOneCOrderProfiles;
use App\Filament\Resources\Orders\Actions\OneCOrderExportAction;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCReferences;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** «Схемы заказов 1С»: how an order is made in 1C (organization, customer, agreement, warehouse…), taken from a real 1C order. */
class OneCOrderProfileResource extends Resource
{
    protected static ?string $model = OneCOrderProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $modelLabel = 'схема заказа 1С';

    protected static ?string $pluralModelLabel = 'Схемы заказов 1С';

    protected static ?string $slug = 'onec-order-profiles';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function form(Schema $schema): Schema
    {
        $refs = app(OneCReferences::class);
        $field = function (string $alias) use ($refs): Select {
            [$label, $kind] = OneCOrderProfiles::FIELDS[OneCOrderExportAction::KEYS[$alias]];
            $select = Select::make('f.'.$alias)->label($label)->searchable();

            return in_array($kind, ['partner', 'counterparty'], true)
                ? $select->getSearchResultsUsing(fn (string $search): array => $refs->search($kind, $search))->getOptionLabelUsing(fn ($value): ?string => $refs->label($kind, $value))
                : $select->options(fn (?string $state): array => $refs->options($kind, current: $state));
        };

        return $schema->components([
            Section::make('Схема')->columns(2)->schema([
                TextInput::make('name')->label('Название')->required()->maxLength(120),
                Select::make('customer_mode')->label('Покупатель')->options(OneCOrderProfile::CUSTOMER_MODES)->required(),
                Toggle::make('is_default')->label('По умолчанию для заказов сайта'),
                CheckboxList::make('meta.delivery_types')->label('Сама для доставки')->options(Order::DELIVERY_TYPES)->columns(2)->columnSpanFull()
                    ->helperText('Заказ с этой доставкой идёт по этой схеме: например, «Транспортная компания по Беларуси» — клиент в 1С «ВАМэкспрессБай ООО».'),
                Toggle::make('is_active')->label('Используется'),
                TextInput::make('sort')->label('Порядок')->numeric(),
                Placeholder::make('template')->label('Сделана по заказу 1С')->content(fn (?OneCOrderProfile $record): string => $record?->template_number ?: '—'),
            ]),
            Section::make('Заказ клиента в 1С')
                ->description('Значения подставляются в форму «Создать заказ в 1С»; менеджер может поменять их для конкретного заказа.')
                ->columns(2)->schema([
                    $field('org'), $field('warehouse'), $field('partner'), $field('counterparty'), $field('agreement'), $field('contract'),
                    $field('subdivision'), $field('manager'), $field('zone'), $field('priority'),
                    Select::make('status')->label('Статус')->options(fn (?string $state): array => OneCOrderProfiles::STATUSES + ($state ? [$state => $state] : [])),
                    Select::make('delivery')->label('Способ доставки')->options(fn (?string $state): array => OneCOrderProfiles::DELIVERY + ($state ? [$state => $state] : [])),
                    Select::make('vat')->label('Ставка НДС в строках')->options(fn (?string $state): array => $refs->options('vat_rate', current: $state)),
                    Toggle::make('with_vat')->label('Цена включает НДС'),
                ]),
            Section::make('Остальные поля из образца')->collapsed()->schema([
                Placeholder::make('rest')->hiddenLabel()->content(fn (?OneCOrderProfile $record): HtmlString => new HtmlString('<div style="font-size:12px;columns:2">'
                    .collect($record?->header ?? [])->except(array_values(OneCOrderExportAction::KEYS))
                        ->map(fn ($v, $k): string => '<div>'.e($k).': <b>'.e(is_bool($v) ? ($v ? 'да' : 'нет') : (string) $v).'</b></div>')->implode('').'</div>')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $refs = app(OneCReferences::class);

        return $table
            ->columns([
                TextColumn::make('name')->label('Схема')->weight('bold'),
                TextColumn::make('document')->label('Документ')->badge()->formatStateUsing(fn (?string $state): string => OneCOrderProfile::DOCUMENTS[$state ?: OneCOrderProfile::CUSTOMER_ORDER] ?? (string) $state),
                TextColumn::make('customer_mode')->label('Покупатель')->formatStateUsing(fn (string $state): string => OneCOrderProfile::CUSTOMER_MODES[$state] ?? $state),
                TextColumn::make('org')->label('Организация')->state(fn (OneCOrderProfile $r): ?string => $refs->label('organization', $r->header['Организация_Key'] ?? null)),
                TextColumn::make('partner')->label('Клиент')->state(fn (OneCOrderProfile $r): ?string => $refs->label('partner', $r->header['Партнер_Key'] ?? null)),
                TextColumn::make('agreement')->label('Соглашение')->state(fn (OneCOrderProfile $r): ?string => $refs->label('agreement', $r->header['Соглашение_Key'] ?? null)),
                TextColumn::make('warehouse')->label('Склад')->state(fn (OneCOrderProfile $r): ?string => $refs->label('warehouse', $r->header['Склад_Key'] ?? null)),
                TextColumn::make('template_number')->label('Образец'),
                IconColumn::make('is_default')->label('По умолч.')->boolean(),
                IconColumn::make('is_active')->label('Активна')->boolean(),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneCOrderProfiles::route('/'),
            'edit' => EditOneCOrderProfile::route('/{record}/edit'),
        ];
    }
}
