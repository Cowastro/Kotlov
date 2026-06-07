<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Models\ContactRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->message),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new'  => 'info',
                        'read' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ContactRequest::$statuses[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ContactRequest::$statuses),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('done')
                    ->label('Обработана')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'done')
                    ->action(fn ($record) => $record->update(['status' => 'done'])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
