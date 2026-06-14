<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ContactRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Имя'),
            TextEntry::make('phone')->label('Телефон'),
            TextEntry::make('email')->label('Email')->placeholder('-'),
            TextEntry::make('message')->label('Сообщение'),
            TextEntry::make('source')
                ->label('Источник')
                ->placeholder('-')
                ->formatStateUsing(fn (?string $state) => match ($state) {
                    'product_page' => 'Карточка товара',
                    'consultation_form' => 'Форма консультации',
                    default => $state ?: '-',
                }),
            TextEntry::make('city')->label('Город')->placeholder('-'),
            TextEntry::make('product_name')->label('Товар')->placeholder('-'),
            TextEntry::make('product_url')
                ->label('Ссылка на страницу')
                ->placeholder('-')
                ->url(fn ($state) => $state ?: null)
                ->openUrlInNewTab(),
            TextEntry::make('status')
                ->label('Статус')
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'new'  => 'info',
                    'read' => 'warning',
                    'done' => 'success',
                    default => 'gray',
                })
                ->formatStateUsing(fn ($state) => ContactRequest::$statuses[$state] ?? $state),
            TextEntry::make('admin_notes')->label('Заметки')->placeholder('-'),
            TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
        ]);
    }
}
