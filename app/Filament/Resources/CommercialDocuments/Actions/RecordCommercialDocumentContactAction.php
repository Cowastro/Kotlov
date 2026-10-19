<?php

namespace App\Filament\Resources\CommercialDocuments\Actions;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class RecordCommercialDocumentContactAction
{
    public static function make(): Action
    {
        return Action::make('record_contact')
            ->label('Результат контакта')
            ->icon('heroicon-o-phone-arrow-up-right')
            ->color(fn (CommercialDocument $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'info')
            ->visible(fn (CommercialDocument $record): bool => $record->type === 'quote'
                && in_array($record->status, ['sent', 'accepted', 'needs_revision'], true)
                && ! $record->newerRevision())
            ->fillForm(fn (CommercialDocument $record): array => [
                'stage' => $record->follow_up_stage ?: match ($record->status) {
                    'accepted' => 'won',
                    'needs_revision' => 'needs_revision',
                    default => 'proposal_sent',
                },
                'result' => $record->last_contact_result,
                'note' => $record->follow_up_note,
                'next_follow_up_at' => $record->next_follow_up_at ?: now()->addWeekday()->setTime(10, 0),
                'loss_reason' => $record->outcome_reason,
            ])
            ->schema([
                Select::make('result')
                    ->label('Результат звонка / контакта')
                    ->options(CommercialDocument::CONTACT_RESULTS)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $stage = match ($state) {
                            'no_answer', 'callback_later' => 'contact_planned',
                            'decision_pending' => 'decision_pending',
                            'negotiation' => 'negotiation',
                            'needs_revision' => 'needs_revision',
                            'accepted' => 'won',
                            'rejected' => 'lost',
                            default => null,
                        };

                        if ($stage) {
                            $set('stage', $stage);
                        }
                    }),
                Select::make('stage')
                    ->label('Стадия работы')
                    ->options(CommercialDocument::FOLLOW_UP_STAGES)
                    ->required()
                    ->helperText('Для согласования, отказа и корректировки стадия установится автоматически.'),
                Textarea::make('note')
                    ->label('О чём договорились')
                    ->placeholder('Например: клиент думает до пятницы, перезвонить после 14:00')
                    ->rows(4)
                    ->maxLength(3000)
                    ->required(),
                DateTimePicker::make('next_follow_up_at')
                    ->label('Когда связаться снова')
                    ->seconds(false)
                    ->native(false)
                    ->timezone('Europe/Minsk')
                    ->required(fn (Get $get): bool => in_array($get('result'), [
                        'no_answer', 'callback_later', 'decision_pending', 'negotiation',
                    ], true))
                    ->visible(fn (Get $get): bool => in_array($get('result'), [
                        'no_answer', 'callback_later', 'decision_pending', 'negotiation',
                    ], true)),
                Select::make('loss_reason')
                    ->label('Причина отказа')
                    ->options(CommercialDocument::OUTCOME_REASONS)
                    ->required(fn (Get $get): bool => $get('result') === 'rejected')
                    ->visible(fn (Get $get): bool => $get('result') === 'rejected'),
            ])
            ->modalHeading('Зафиксировать контакт с клиентом')
            ->modalDescription(fn (CommercialDocument $record): string => $record->reference().' · '
                .($record->client_company ?: ($record->client_name ?: 'Клиент не указан')))
            ->modalSubmitActionLabel('Сохранить результат')
            ->action(function (CommercialDocument $record, array $data): void {
                app(CommercialDocumentWorkflow::class)->recordContact(
                    $record,
                    auth()->user(),
                    $data['stage'],
                    $data['result'],
                    $data['note'],
                    $data['next_follow_up_at'] ?? null,
                    $data['loss_reason'] ?? null,
                );

                Notification::make()
                    ->success()
                    ->title('Результат контакта сохранён')
                    ->body(filled($data['next_follow_up_at'] ?? null)
                        ? 'КП появится в очереди контроля в назначенное время.'
                        : 'Стадия и история работы обновлены.')
                    ->send();
            });
    }
}
