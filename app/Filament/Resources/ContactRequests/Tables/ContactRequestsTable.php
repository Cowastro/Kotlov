<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Models\ContactRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('message')
                    ->label('Сообщение')
                    ->limit(90)
                    ->lineClamp(2)
                    ->tooltip(fn ($record) => $record->message),

                TextColumn::make('product_name')
                    ->label('Товар')
                    ->limit(55)
                    ->lineClamp(2)
                    ->tooltip(fn ($record) => $record->product_name)
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('city')
                    ->label('Город')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'product_page' => 'Карточка товара',
                        'consultation_form' => 'Форма консультации',
                        default => $state ?: '—',
                    })
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new'  => 'info',
                        'read' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'new' => 'heroicon-o-sparkles',
                        'read' => 'heroicon-o-eye',
                        'done' => 'heroicon-o-check-circle',
                        default => null,
                    })
                    ->formatStateUsing(fn ($state) => ContactRequest::$statuses[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ContactRequest::$statuses),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('read')
                        ->label('Прочитана')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'new')
                        ->action(fn ($record) => $record->update(['status' => 'read'])),

                    Action::make('done')
                        ->label('Обработана')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status !== 'done')
                        ->action(fn ($record) => $record->update(['status' => 'done'])),

                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
