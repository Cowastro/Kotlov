<?php

namespace App\Filament\Resources\InstallerApplications\Schemas;

use App\Models\InstallerApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InstallerApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('contact_name')->label('Имя'),
            TextEntry::make('phone')->label('Телефон'),
            TextEntry::make('email')->label('Email')->placeholder('-'),
            TextEntry::make('city')->label('Город')->placeholder('-'),
            TextEntry::make('company_name')->label('Компания')->placeholder('-'),
            TextEntry::make('experience_years')->label('Опыт, лет')->placeholder('-'),
            TextEntry::make('specializations')
                ->label('Специализации')
                ->formatStateUsing(function ($state) {
                    if (!$state) return '-';
                    $labels = InstallerApplication::$specializationLabels;
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
                ->formatStateUsing(fn ($state) => InstallerApplication::$statuses[$state] ?? $state),
            TextEntry::make('admin_notes')->label('Заметки')->placeholder('-'),
            TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
        ]);
    }
}
