<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Models\ContactRequest;
use App\Support\AdminPhoneLinks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
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
                        'ai_selection' => 'AI-подбор',
                        default => $state ?: '—',
                    })
                    ->color(fn (?string $state) => $state === 'ai_selection' ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new' => 'info',
                        'read' => 'gray',
                        'in_progress' => 'warning',
                        'waiting' => 'primary',
                        'done' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state) => match ($state) {
                        'new' => 'heroicon-o-sparkles',
                        'read' => 'heroicon-o-eye',
                        'in_progress' => 'heroicon-o-arrow-path',
                        'waiting' => 'heroicon-o-clock',
                        'done' => 'heroicon-o-check-circle',
                        'cancelled' => 'heroicon-o-x-circle',
                        default => null,
                    })
                    ->formatStateUsing(fn ($state) => ContactRequest::$statuses[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('responsibleUser.name')
                    ->label('Ответственный')
                    ->icon('heroicon-o-user-circle')
                    ->placeholder('Не назначен')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('next_follow_up_at')
                    ->label('Следующий контакт')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('—')
                    ->color(fn (ContactRequest $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(),

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
                SelectFilter::make('responsible_user_id')
                    ->label('Ответственный')
                    ->relationship('responsibleUser', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('read')
                        ->label('Взять в работу')
                        ->icon('heroicon-o-hand-raised')
                        ->color('warning')
                        ->visible(fn ($record) => in_array($record->status, ['new', 'read'], true))
                        ->action(fn ($record) => $record->update([
                            'status' => 'in_progress',
                            'responsible_user_id' => $record->responsible_user_id ?: auth()->id(),
                        ])),

                    Action::make('done')
                        ->label('Обработана')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status !== 'done')
                        ->action(fn ($record) => $record->update([
                            'status' => 'done',
                            'responsible_user_id' => $record->responsible_user_id ?: auth()->id(),
                            'next_follow_up_at' => null,
                        ])),

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
