<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')
                    ->label('Автор')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('reviewable_type')
                    ->label('Объект')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'App\Models\Product'          => 'Товар',
                        'App\Models\InstallerProfile' => 'Монтажник',
                        'App\Models\SupplierProfile'  => 'Поставщик',
                        default => $state,
                    }),

                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->sortable()
                    ->formatStateUsing(fn($state) => str_repeat('★', $state)),

                TextColumn::make('text')
                    ->label('Отзыв')
                    ->limit(60),

                IconColumn::make('is_approved')
                    ->label('Одобрен')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Модерация'),

                SelectFilter::make('rating')
                    ->label('Рейтинг')
                    ->options([
                        1 => '★ 1',
                        2 => '★★ 2',
                        3 => '★★★ 3',
                        4 => '★★★★ 4',
                        5 => '★★★★★ 5',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Одобрить')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(fn($r) => $r->update(['is_approved' => true]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('reject')
                        ->label('Отклонить')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            $records->each(fn($r) => $r->update(['is_approved' => false]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}