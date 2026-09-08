<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use App\Services\Pricing\CurrencyPriceConverter;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?string $navigationLabel = 'Поставщики';
    protected static ?string $modelLabel = 'поставщик';
    protected static ?string $pluralModelLabel = 'Поставщики';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Код')
                ->helperText('Используется в командах синхронизации, например: elicon')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),

            Select::make('currency')
                ->label('Валюта поставщика')
                ->options(array_combine(
                    CurrencyPriceConverter::SUPPORTED_CURRENCIES,
                    CurrencyPriceConverter::SUPPORTED_CURRENCIES
                ))
                ->default(CurrencyPriceConverter::BASE_CURRENCY)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state === CurrencyPriceConverter::BASE_CURRENCY) {
                        $set('currency_rate', 1);
                    }
                }),

            TextInput::make('currency_rate')
                ->label('Курс к BYN')
                ->helperText('Цена поставщика × курс = цена на сайте в BYN. Для BYN курс = 1. Пример: 100 RUB × 0.035 = 3.50 BYN')
                ->numeric()
                ->step('0.0001')
                ->minValue(0.0001)
                ->default(1)
                ->required()
                ->disabled(fn (callable $get) => $get('currency') === CurrencyPriceConverter::BASE_CURRENCY)
                ->dehydrated(),

            TextInput::make('contact')
                ->label('Контакт / сайт')
                ->maxLength(255),

            Toggle::make('is_active')
                ->label('Активен')
                ->default(true),

            Textarea::make('notes')
                ->label('Заметки')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('currency')
                    ->label('Валюта')
                    ->badge()
                    ->color(fn (?string $state) => $state === CurrencyPriceConverter::BASE_CURRENCY ? 'success' : 'warning'),

                TextColumn::make('currency_rate')
                    ->label('Курс к BYN')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 4, '.', ' '), '0'), '.')),

                TextColumn::make('supplier_products_count')
                    ->label('Связок товаров')
                    ->counts('supplierProducts')
                    ->alignRight(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('fetchNbrb')
                    ->label('Курс НБРБ')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->tooltip('Загрузить официальный курс НБРБ и пересчитать BYN цены')
                    ->visible(fn (Supplier $record) => $record->currency !== CurrencyPriceConverter::BASE_CURRENCY)
                    ->action(function (Supplier $record) {
                        $url  = 'https://api.nbrb.by/exrates/rates/' . $record->currency . '?parammode=2';
                        $resp = Http::timeout(10)->get($url);
                        if (!$resp->ok() || !($rate = $resp->json('Cur_OfficialRate'))) {
                            Notification::make()
                                ->title('Ошибка НБРБ')
                                ->body('Не удалось получить курс ' . $record->currency)
                                ->danger()
                                ->send();
                            return;
                        }
                        $rate = round((float) $rate, 4);
                        $record->update(['currency_rate' => $rate]);

                        // Пересчитать price_byn в supplier_products
                        \Illuminate\Support\Facades\DB::table('supplier_products')
                            ->where('supplier_id', $record->id)
                            ->where('currency', $record->currency)
                            ->update([
                                'currency_rate' => $rate,
                                'price_byn'     => \Illuminate\Support\Facades\DB::raw("ROUND(price * {$rate}, 2)"),
                                'updated_at'    => now(),
                            ]);

                        // Обновить products.price
                        $sps = \Illuminate\Support\Facades\DB::table('supplier_products')
                            ->where('supplier_id', $record->id)
                            ->whereNotNull('product_id')
                            ->get(['product_id', 'price_byn']);

                        foreach ($sps as $sp) {
                            if ($sp->price_byn > 0) {
                                \Illuminate\Support\Facades\DB::table('products')
                                    ->where('id', $sp->product_id)
                                    ->update(['price' => $sp->price_byn, 'updated_at' => now()]);
                            }
                        }

                        Notification::make()
                            ->title("Курс обновлён: 1 {$record->currency} = {$rate} BYN")
                            ->body("Пересчитано {$sps->count()} товаров.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
