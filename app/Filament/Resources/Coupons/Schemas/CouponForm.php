<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Промокод')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Код купона')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('KOTLOV10'),

                        Select::make('type')
                            ->label('Тип скидки')
                            ->options([
                                'percent' => 'Процент (%)',
                                'fixed'   => 'Фиксированная сумма (BYN)',
                            ])
                            ->default('percent')
                            ->required(),

                        TextInput::make('value')
                            ->label('Размер скидки')
                            ->numeric()
                            ->required()
                            ->placeholder('10'),

                        TextInput::make('min_order_amount')
                            ->label('Минимальная сумма заказа')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),
                    ]),

                Section::make('Ограничения')
                    ->columns(2)
                    ->schema([
                        TextInput::make('uses_limit')
                            ->label('Максимум использований')
                            ->numeric()
                            ->placeholder('Без ограничений'),

                        TextInput::make('uses_count')
                            ->label('Использовано раз')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        DateTimePicker::make('expires_at')
                            ->label('Действует до')
                            ->placeholder('Без ограничений'),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }
}