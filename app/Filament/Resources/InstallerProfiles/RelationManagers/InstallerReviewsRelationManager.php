<?php

namespace App\Filament\Resources\InstallerProfiles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InstallerReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';
    protected static ?string $title = 'Отзывы монтажника';
    protected static ?string $label = 'Отзыв';
    protected static ?string $pluralLabel = 'Отзывы';

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('author_name')
                ->label('Имя клиента')
                ->required()
                ->maxLength(255),

            TextInput::make('author_email')
                ->label('Email клиента')
                ->email()
                ->maxLength(255),

            Select::make('rating')
                ->label('Оценка')
                ->options([
                    1 => '1 — Плохо',
                    2 => '2 — Ниже среднего',
                    3 => '3 — Нормально',
                    4 => '4 — Хорошо',
                    5 => '5 — Отлично',
                ])
                ->required(),

            Toggle::make('is_approved')
                ->label('Одобрен')
                ->default(false),

            Textarea::make('text')
                ->label('Текст отзыва')
                ->rows(5)
                ->required()
                ->columnSpanFull(),

            FileUpload::make('photos')
                ->label('Фотографии')
                ->image()
                ->multiple()
                ->directory('installers/reviews')
                ->disk('public')
                ->maxSize(4096)
                ->columnSpanFull(),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')
                    ->label('Клиент')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author_email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rating')
                    ->label('Оценка')
                    ->badge()
                    ->color(fn ($state) => match ((int) $state) {
                        5       => 'success',
                        4       => 'info',
                        3       => 'warning',
                        1, 2    => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state . ' ★')
                    ->sortable(),
                TextColumn::make('text')
                    ->label('Отзыв')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->text),
                IconColumn::make('is_approved')
                    ->label('Одобрен')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Статус')
                    ->trueLabel('Одобренные')
                    ->falseLabel('На модерации'),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить отзыв'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
