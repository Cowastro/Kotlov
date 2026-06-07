<?php

namespace App\Filament\Resources\SupplierApplications\Tables;

use App\Models\SupplierApplication;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('Контакт')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label('Сайт')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new'       => 'info',
                        'contacted' => 'warning',
                        'approved'  => 'success',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => SupplierApplication::$statuses[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(SupplierApplication::$statuses),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('contacted')
                    ->label('Связались')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'new')
                    ->action(fn ($record) => $record->update(['status' => 'contacted'])),
                Action::make('approved')
                    ->label('Принять')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['new', 'contacted']))
                    ->action(fn ($record) => $record->update(['status' => 'approved'])),
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
