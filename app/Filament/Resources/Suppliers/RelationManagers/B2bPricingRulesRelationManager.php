<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\B2bPricingRule;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\B2bPartnerPricingEngine;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class B2bPricingRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'b2bPricingRules';

    protected static ?string $title = 'Правила B2B-цен';

    protected static ?string $label = 'правило B2B-цены';

    protected static ?string $pluralLabel = 'Правила B2B-цен';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255)->columnSpanFull(),
            Select::make('scope_type')
                ->label('Область применения')
                ->options(self::scopeOptions())
                ->default(B2bPricingRule::SCOPE_SUPPLIER)
                ->required()->live(),
            Select::make('scope_id')
                ->label(fn (Get $get): string => match ($get('scope_type')) {
                    B2bPricingRule::SCOPE_CATEGORY => 'Категория',
                    B2bPricingRule::SCOPE_BRAND => 'Бренд',
                    B2bPricingRule::SCOPE_PRODUCT => 'Товар',
                    default => 'Весь поставщик',
                })
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, Get $get): array => self::scopeSearchResults((string) $get('scope_type'), $search))
                ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => self::scopeOptionLabel((string) $get('scope_type'), $value))
                ->required(fn (Get $get): bool => $get('scope_type') !== B2bPricingRule::SCOPE_SUPPLIER)
                ->hidden(fn (Get $get): bool => $get('scope_type') === B2bPricingRule::SCOPE_SUPPLIER),
            Select::make('calculation_type')
                ->label('Способ расчёта')
                ->options(self::calculationOptions())
                ->default(B2bPricingRule::TYPE_TARGET_MARGIN)
                ->required(),
            TextInput::make('value')
                ->label('Значение')
                ->helperText('Для маржи и наценки — процент; для фиксированной цены — BYN.')
                ->numeric()->minValue(0)->required(),
            TextInput::make('minimum_profit_byn')->label('Минимальная прибыль')->numeric()->minValue(0)->default(0)->suffix('BYN'),
            TextInput::make('minimum_margin_percent')
                ->label('Минимально допустимая маржа')
                ->helperText('Ни ценовой уровень клиента, ни скидка не смогут опустить продажную цену ниже этого порога.')
                ->numeric()->minValue(0)->maxValue(99)->default(10)->suffix('%'),
            Select::make('rounding_step_byn')->label('Округлять вверх до')->options([
                '0.1' => '0,10 BYN',
                '1' => '1 BYN',
                '5' => '5 BYN',
            ])->default('1')->required(),
            TextInput::make('minimum_retail_discount_percent')->label('Минимальная скидка от розницы')->numeric()->minValue(0)->maxValue(99)->default(0)->suffix('%'),
            TextInput::make('priority')->label('Приоритет правила')->numeric()->integer()->default(100),
            Select::make('status')->label('Статус')->options(self::statusOptions())->default(B2bPricingRule::STATUS_DRAFT)->required(),
            DateTimePicker::make('valid_from')->label('Действует с')->seconds(false),
            DateTimePicker::make('valid_until')->label('Действует до')->seconds(false),
            Textarea::make('notes')->label('Комментарий менеджеру')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Правило')->weight('bold')->searchable()->wrap(),
                TextColumn::make('scope_type')->label('Для чего')->formatStateUsing(fn (string $state): string => self::scopeOptions()[$state] ?? $state)->badge(),
                TextColumn::make('calculation_type')->label('Расчёт')->formatStateUsing(fn (string $state): string => self::calculationOptions()[$state] ?? $state),
                TextColumn::make('value')->label('Значение')->formatStateUsing(fn ($state, B2bPricingRule $record): string => number_format((float) $state, 2, ',', ' ').($record->calculation_type === B2bPricingRule::TYPE_FIXED_PRICE ? ' BYN' : '%')),
                TextColumn::make('status')->label('Статус')->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state)->badge()
                    ->color(fn (string $state): string => match ($state) {
                        B2bPricingRule::STATUS_ACTIVE => 'success',
                        B2bPricingRule::STATUS_DRAFT => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('valid_until')->label('До')->dateTime('d.m.Y H:i')->placeholder('Без срока'),
            ])
            ->headerActions([
                Action::make('preview')
                    ->label('Предпросмотр применения')
                    ->icon('heroicon-o-eye')
                    ->action(function (): void {
                        $preview = app(B2bPartnerPricingEngine::class)->preview($this->getOwnerRecord(), true);
                        $reasonLabels = [
                            B2bPartnerPricingEngine::REASON_UNMATCHED => 'Не сопоставлено',
                            B2bPartnerPricingEngine::REASON_INACTIVE_PRODUCT => 'Карточка не активна',
                            B2bPartnerPricingEngine::REASON_NO_PURCHASE_PRICE => 'Нет закупочной цены',
                            B2bPartnerPricingEngine::REASON_STALE_STOCK => 'Нет свежего остатка',
                            B2bPartnerPricingEngine::REASON_NO_RETAIL_PRICE => 'Нет розницы',
                            B2bPartnerPricingEngine::REASON_NO_RULE => 'Нет правила',
                            B2bPartnerPricingEngine::REASON_INVALID_RULE => 'Ошибка в правиле',
                            B2bPartnerPricingEngine::REASON_PRICE_CONFLICT => 'Конфликт цены',
                            B2bPartnerPricingEngine::REASON_EXCLUDED => 'Исключено менеджером',
                        ];
                        $reasons = collect($preview['reasons'])
                            ->map(fn (int $count, string $reason): string => ($reasonLabels[$reason] ?? $reason).': '.$count)
                            ->values();

                        Notification::make()
                            ->title('Предпросмотр B2B-цен')
                            ->body(collect([
                                'Всего предложений: '.$preview['total'],
                                'Готово по черновым и активным правилам: '.$preview['would_publish'],
                                'Сейчас опубликовано: '.$preview['published'],
                                ...$reasons,
                            ])->implode("\n"))
                            ->info()->persistent()->send();
                    }),
                CreateAction::make()
                    ->label('Добавить правило')
                    ->visible(fn (): bool => SupplierResource::canEdit($this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => SupplierResource::canEdit($this->getOwnerRecord())),
                DeleteAction::make()->visible(fn (): bool => SupplierResource::canEdit($this->getOwnerRecord())),
            ])
            ->emptyStateHeading('Правила ещё не заданы')
            ->emptyStateDescription('Начните с общего чернового правила поставщика и проверьте расчёт до включения публикации.');
    }

    private static function scopeOptions(): array
    {
        return [
            B2bPricingRule::SCOPE_SUPPLIER => 'Весь поставщик',
            B2bPricingRule::SCOPE_CATEGORY => 'Категория',
            B2bPricingRule::SCOPE_BRAND => 'Бренд',
            B2bPricingRule::SCOPE_PRODUCT => 'Конкретный товар',
        ];
    }

    private static function calculationOptions(): array
    {
        return [
            B2bPricingRule::TYPE_TARGET_MARGIN => 'Целевая маржа',
            B2bPricingRule::TYPE_MARKUP => 'Наценка',
            B2bPricingRule::TYPE_FIXED_PRICE => 'Фиксированная B2B-цена',
        ];
    }

    private static function statusOptions(): array
    {
        return [
            B2bPricingRule::STATUS_DRAFT => 'Черновик',
            B2bPricingRule::STATUS_ACTIVE => 'Активно',
            B2bPricingRule::STATUS_DISABLED => 'Отключено',
        ];
    }

    private static function scopeSearchResults(string $scope, string $search): array
    {
        return match ($scope) {
            B2bPricingRule::SCOPE_CATEGORY => Category::query()->where('name', 'like', "%{$search}%")->orderBy('name')->limit(50)->pluck('name', 'id')->all(),
            B2bPricingRule::SCOPE_BRAND => Brand::query()->where('name', 'like', "%{$search}%")->orderBy('name')->limit(50)->pluck('name', 'id')->all(),
            B2bPricingRule::SCOPE_PRODUCT => Product::query()->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))->orderBy('name')->limit(50)->get()->mapWithKeys(fn (Product $product): array => [$product->id => $product->sku.' · '.$product->name])->all(),
            default => [],
        };
    }

    private static function scopeOptionLabel(string $scope, mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return match ($scope) {
            B2bPricingRule::SCOPE_CATEGORY => Category::find($value)?->name,
            B2bPricingRule::SCOPE_BRAND => Brand::find($value)?->name,
            B2bPricingRule::SCOPE_PRODUCT => ($product = Product::find($value)) ? $product->sku.' · '.$product->name : null,
            default => null,
        };
    }
}
