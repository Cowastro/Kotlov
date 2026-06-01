<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('number'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_phone'),
                TextEntry::make('customer_email')
                    ->placeholder('-'),
                TextEntry::make('delivery_type')
                    ->badge(),
                TextEntry::make('delivery_region')
                    ->placeholder('-'),
                TextEntry::make('delivery_city')
                    ->placeholder('-'),
                TextEntry::make('delivery_address')
                    ->placeholder('-'),
                TextEntry::make('delivery_price')
                    ->money(),
                TextEntry::make('payment_type')
                    ->badge(),
                TextEntry::make('payment_status')
                    ->badge(),
                TextEntry::make('coupon_code')
                    ->placeholder('-'),
                TextEntry::make('discount')
                    ->numeric(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('admin_comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
