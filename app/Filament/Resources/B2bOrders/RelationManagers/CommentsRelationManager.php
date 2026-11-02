<?php

namespace App\Filament\Resources\B2bOrders\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';
    protected static ?string $title = 'Хронология комментариев';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')->label('Комментарий')->rows(4)->required()->maxLength(5000),
            Toggle::make('visible_to_client')->label('Показать партнёру в кабинете')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author')->latest())
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('author.name')->label('Кто')->placeholder('Система'),
                TextColumn::make('body')->label('Что сделал / комментарий')->wrap(),
                IconColumn::make('visible_to_client')->label('Виден партнёру')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить запись')->mutateDataUsing(function (array $data): array {
                    $data['author_id'] = auth()->id();
                    return $data;
                }),
            ]);
    }
}
