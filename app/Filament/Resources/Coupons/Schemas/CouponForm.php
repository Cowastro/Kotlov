<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                Select::make('type')
                    ->options(['percent' => 'Percent', 'fixed' => 'Fixed'])
                    ->default('percent')
                    ->required(),
                TextInput::make('value')
                    ->required()
                    ->numeric(),
                TextInput::make('min_order_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('uses_limit')
                    ->numeric(),
                TextInput::make('uses_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('expires_at'),
            ]);
    }
}
