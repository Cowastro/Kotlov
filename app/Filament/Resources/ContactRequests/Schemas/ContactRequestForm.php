<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Управление')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('status')->label('Статус')->options(ContactRequest::$statuses)->required()->native(false),
                        Select::make('responsible_user_id')
                            ->label('Ответственный')
                            ->options(fn (): array => User::query()
                                ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DateTimePicker::make('next_follow_up_at')->label('Следующий контакт')->seconds(false)->timezone('Europe/Minsk'),
                        Textarea::make('admin_notes')->label('Комментарий / следующий шаг')->rows(3)->columnSpanFull(),
                    ]),
                Section::make('Клиент')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Имя')->required(),
                        TextInput::make('phone')->label('Телефон')->tel()->required(),
                        TextInput::make('email')->label('Email')->email(),
                        TextInput::make('city')->label('Город'),
                    ]),
                Section::make('Обращение')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('message')->label('Сообщение')->rows(5)->columnSpanFull(),
                        TextInput::make('product_name')->label('Товар'),
                        TextInput::make('product_url')->label('Ссылка на страницу')->url(),
                        TextInput::make('product_id')->label('ID товара')->numeric(),
                        TextInput::make('source')->label('Источник'),
                    ]),
            ]);
    }
}
