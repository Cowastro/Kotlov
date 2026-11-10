<?php

namespace App\Filament\Resources\CrmMailingCampaigns\RelationManagers;

use App\Models\CrmMailingCampaign;
use App\Models\CrmMailingMessage;
use App\Services\Crm\MailingSender;
use App\Services\Telephony\PhoneNumber;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Получатели и доставка';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('member'))
            ->description(function (): string {
                /** @var CrmMailingCampaign $campaign */
                $campaign = $this->getOwnerRecord();
                $counts = $campaign->messages()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
                if ($counts->isEmpty()) {
                    return 'Получатели появятся после запуска: все мобильные номера партнёров списка, кроме отписавшихся.';
                }

                return collect(CrmMailingMessage::STATUSES)->filter(fn ($label, $key) => isset($counts[$key]))
                    ->map(fn ($label, $key) => $label.': '.$counts[$key])->implode(' · ').'. Статусы обновляются раз в 10 минут в течение '.MailingSender::STATUS_DAYS.' дней.';
            })
            ->columns([
                TextColumn::make('phone')->label('Телефон')->formatStateUsing(fn (string $state): string => PhoneNumber::format($state) ?? $state)->searchable(),
                TextColumn::make('member.name')->label('Партнёр')->wrap()->placeholder('—'),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => CrmMailingMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'delivered', 'read' => 'success', 'failed' => 'danger', 'sent' => 'info', default => 'gray',
                    }),
                TextColumn::make('provider_status')->label('Ответ SMS.BY')->placeholder('—')
                    ->description(fn (CrmMailingMessage $record): ?string => $record->error),
                TextColumn::make('sent_at')->label('Отправлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(CrmMailingMessage::STATUSES)])
            ->defaultSort('id')
            ->recordActions([
                Action::make('optOut')->label('Отписать')->icon('heroicon-o-no-symbol')->color('gray')
                    ->requiresConfirmation()->modalDescription('Номер больше не будет получать рассылки.')
                    ->action(function (CrmMailingMessage $record): void {
                        app(MailingSender::class)->optOut($record->phone, 'manual', 'Из рассылки #'.$record->campaign_id);
                        Notification::make()->success()->title('Номер отписан')->send();
                    }),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
