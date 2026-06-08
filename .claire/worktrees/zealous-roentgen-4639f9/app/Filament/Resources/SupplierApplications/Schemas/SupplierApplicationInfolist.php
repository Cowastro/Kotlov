<?php

namespace App\Filament\Resources\SupplierApplications\Schemas;

use App\Models\SupplierApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupplierApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('company_name')->label('Компания'),
            TextEntry::make('contact_name')->label('Контактное лицо'),
            TextEntry::make('phone')->label('Телефон'),
            TextEntry::make('email')->label('Email')->placeholder('-'),
            TextEntry::make('website')->label('Сайт')->placeholder('-'),
            TextEntry::make('product_categories')
                ->label('Категории товаров')
                ->formatStateUsing(function ($state) {
                    if (!$state) return '-';
                    $labels = SupplierApplication::$categoryLabels;
                    return collect($state)->map(fn ($s) => $labels[$s] ?? $s)->join(', ');
                }),
            TextEntry::make('message')->label('Сообщение')->placeholder('-'),
            TextEntry::make('status')
                ->label('Статус')
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'new'       => 'info',
                    'contacted' => 'warning',
                    'approved'  => 'success',
                    'rejected'  => 'danger',
                    default     => 'gray',
                })
                ->formatStateUsing(fn ($state) => SupplierApplication::$statuses[$state] ?? $state),
            TextEntry::make('admin_notes')->label('Заметки')->placeholder('-'),
            TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
        ]);
    }
}
