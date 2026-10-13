<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Services\CrmMail\CrmMailSender;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Throwable;

class ViewCrmMailThread extends ViewRecord
{
    protected static string $resource = CrmMailThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->messages()->where('direction', 'incoming')->update(['is_read' => true]);
        $this->record->forceFill(['unread_count' => 0])->save();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('take')
                ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                ->visible(fn (): bool => $this->record->assigned_user_id !== auth()->id() || $this->record->status === 'new')
                ->action(function (): void {
                    $this->record->takeFor((int) auth()->id());
                    $this->record->refresh();
                }),
            Action::make('reply')
                ->label('Ответить')->icon('heroicon-o-paper-airplane')->color('primary')
                ->schema([
                    Textarea::make('body')->label('Ответ клиенту')->rows(10)->required()->maxLength(20000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Отправить ответ клиенту')
                ->modalDescription(fn (): string => 'Письмо будет отправлено на '.$this->record->participant_email)
                ->action(function (array $data): void {
                    try {
                        app(CrmMailSender::class)->reply($this->record, $data['body'], auth()->user());
                        $this->record->refresh();
                        Notification::make()->success()->title('Ответ отправлен')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                    }
                }),
            EditAction::make()->label('Карточка лида'),
        ];
    }
}
