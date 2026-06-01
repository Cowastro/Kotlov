<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_url')
                    ->label('Старый URL')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('to_url')
                    ->label('Новый URL')
                    ->searchable(),

                TextColumn::make('status_code')
                    ->label('Тип')
                    ->badge()
                    ->color(fn($state) => $state === 301 ? 'success' : 'warning')
                    ->formatStateUsing(fn($state) => $state . ' ' . ($state === 301 ? 'Постоянный' : 'Временный')),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Добавлен')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status_code')
                    ->label('Тип')
                    ->options([
                        301 => '301 Постоянный',
                        302 => '302 Временный',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}