<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
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
            ->modifyQueryUsing(fn ($query) => $query->with(['latestLeadMessage', 'responsibleUser']))
            ->poll('15s')
            ->recordClasses(fn (ContactRequest $record): ?string => $record->needs_attention && ! $record->archived_at ? 'contact-request-needs-attention' : null)
            ->recordAction(fn (ContactRequest $record): ?string => self::hasDialog($record) ? 'dialog' : null)
            ->recordUrl(fn (ContactRequest $record): ?string => ! self::hasDialog($record) && ContactRequestResource::canView($record)
                ? ContactRequestResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('name')
                    ->label('Клиент')
                    ->icon(fn (ContactRequest $record): ?string => $record->needs_attention ? 'heroicon-m-exclamation-circle' : null)
                    ->iconColor('warning')
                    ->tooltip(fn (ContactRequest $record): ?string => $record->needs_attention ? 'Требует реакции' : null)
                    ->searchable(['name', 'phone', 'email', 'product_name', 'city'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Не указан')
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('message')
                    ->label('Последнее сообщение')
                    ->state(fn (ContactRequest $record): string => $record->latestLeadMessage?->body ?? $record->message)
                    ->limit(90)
                    ->lineClamp(1)
                    ->tooltip(fn (ContactRequest $record): string => $record->latestLeadMessage?->body ?? $record->message),

                TextColumn::make('product_name')
                    ->label('Товар')
                    ->limit(55)
                    ->lineClamp(2)
                    ->tooltip(fn ($record) => $record->product_name)
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city')
                    ->label('Город')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('source')
                    ->label('Канал')
                    ->state(fn (ContactRequest $record): string => $record->channelLabel())
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'AI-подбор' => 'warning',
                        'Telegram', 'WhatsApp', 'Instagram', 'Viber' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->tooltip(fn (ContactRequest $record): ?string => $record->responsibleUser?->name)
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_follow_up_at')
                    ->label('Следующий контакт')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('—')
                    ->color(fn (ContactRequest $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Активность')
                    ->dateTime('d.m H:i', 'Europe/Minsk')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->checkIfRecordIsSelectableUsing(fn (ContactRequest $record): bool => ! $record->isSiteChat())
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ContactRequest::$statuses),
                SelectFilter::make('responsible_user_id')
                    ->label('Ответственный')
                    ->relationship('responsibleUser', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(ContactRequest::$sources),
            ])
            ->recordActions([
                Action::make('dialog')
                    ->label('Диалог')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->authorize(fn (ContactRequest $record): bool => ContactRequestResource::canView($record))
                    ->visible(fn (ContactRequest $record): bool => self::hasDialog($record))
                    ->modalHeading(fn (ContactRequest $record): string => $record->name)
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn (ContactRequest $record) => view('filament.contact-requests.history', [
                        'lead' => $record,
                        'placement' => 'queue',
                    ])),
                Action::make('callBack')
                    ->label('Перезвонить')
                    ->icon('heroicon-o-phone')
                    ->iconButton()
                    ->visible(fn (ContactRequest $record): bool => $record->source === 'quick_callback'
                        && ! $record->archived_at && in_array($record->status, ContactRequest::OPEN_STATUSES, true)
                        && strlen(AdminPhoneLinks::digits($record->phone)) >= 7)
                    ->url(fn (ContactRequest $record): string => 'tel:+'.AdminPhoneLinks::digits($record->phone)),
                ViewAction::make()->iconButton()->tooltip('Открыть карточку'),
                ActionGroup::make([
                    Action::make('markSeen')
                        ->label('Просмотрено')
                        ->icon('heroicon-o-check')
                        ->authorize(fn (ContactRequest $record): bool => ContactRequestResource::canEdit($record))
                        ->visible(fn (ContactRequest $record): bool => (bool) $record->needs_attention)
                        ->action(fn (ContactRequest $record) => $record->clearInboxAttention()),
                    Action::make('archive')
                        ->label('В архив')
                        ->icon('heroicon-o-archive-box')
                        ->authorize(fn (ContactRequest $record): bool => ContactRequestResource::canEdit($record))
                        ->visible(fn (ContactRequest $record): bool => ContactRequest::inboxStateAvailable()
                            && ! $record->archived_at && in_array($record->status, ContactRequest::OPEN_STATUSES, true))
                        ->action(fn (ContactRequest $record) => $record->update([
                            'archived_at' => now(), 'needs_attention' => false,
                        ])),
                    Action::make('restore')
                        ->label('Вернуть в активные')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->authorize(fn (ContactRequest $record): bool => ContactRequestResource::canEdit($record))
                        ->visible(fn (ContactRequest $record): bool => filled($record->archived_at))
                        ->action(fn (ContactRequest $record) => $record->update(['archived_at' => null])),
                    Action::make('read')
                        ->label('Взять в работу')
                        ->icon('heroicon-o-hand-raised')
                        ->color('warning')
                        ->visible(fn (ContactRequest $record) => ! $record->isSiteChat() && ! $record->archived_at && in_array($record->status, ['new', 'read'], true))
                        ->action(fn ($record) => $record->update([
                            'status' => 'in_progress',
                            'responsible_user_id' => $record->responsible_user_id ?: auth()->id(),
                        ])),

                    Action::make('done')
                        ->label('Обработана')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (ContactRequest $record) => ! $record->isSiteChat() && $record->status !== 'done')
                        ->action(fn ($record) => $record->update([
                            'status' => 'done',
                            'responsible_user_id' => $record->responsible_user_id ?: auth()->id(),
                            'next_follow_up_at' => null,
                        ])),

                    EditAction::make()->hidden(fn (ContactRequest $record): bool => $record->isSiteChat()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function hasDialog(ContactRequest $record): bool
    {
        return filled($record->external_source);
    }
}
