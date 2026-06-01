<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Автор')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('— Гость —'),

                        TextInput::make('author_name')
                            ->label('Имя автора')
                            ->required(),

                        TextInput::make('author_email')
                            ->label('Email автора')
                            ->email(),
                    ]),

                Section::make('Отзыв')
                    ->columns(2)
                    ->schema([
                        Select::make('reviewable_type')
                            ->label('Тип объекта')
                            ->options([
                                'App\Models\Product'          => 'Товар',
                                'App\Models\InstallerProfile' => 'Монтажник',
                                'App\Models\SupplierProfile'  => 'Поставщик',
                            ])
                            ->required(),

                        TextInput::make('reviewable_id')
                            ->label('ID объекта')
                            ->numeric()
                            ->required(),

                        Select::make('rating')
                            ->label('Рейтинг')
                            ->options([
                                1 => '★ 1 — Плохо',
                                2 => '★★ 2 — Неплохо',
                                3 => '★★★ 3 — Нормально',
                                4 => '★★★★ 4 — Хорошо',
                                5 => '★★★★★ 5 — Отлично',
                            ])
                            ->required(),

                        Toggle::make('is_approved')
                            ->label('Одобрен')
                            ->default(false),

                        Textarea::make('text')
                            ->label('Текст отзыва')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('photos')
                            ->label('Фото к отзыву')
                            ->image()
                            ->multiple()
                            ->directory('reviews')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}