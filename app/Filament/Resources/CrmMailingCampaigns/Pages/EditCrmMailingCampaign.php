<?php

namespace App\Filament\Resources\CrmMailingCampaigns\Pages;

use App\Filament\Resources\CrmMailingCampaigns\CrmMailingCampaignResource;
use App\Models\CrmMailingCampaign;
use App\Services\Crm\MailingSender;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class EditCrmMailingCampaign extends EditRecord
{
    protected static string $resource = CrmMailingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Тест на мой номер')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->visible(fn (): bool => $this->campaign()->status === 'draft')
                ->schema([TextInput::make('phone')->label('Мобильный номер')->required()->tel()->placeholder('+375 29 123-45-67')])
                ->modalDescription('Уйдёт одно сообщение с текущим текстом (сохраните изменения перед тестом). Стоимость — по тарифу SMS.BY.')
                ->action(function (array $data): void {
                    if (RateLimiter::hit('crm-mailing-test:'.auth()->id(), 60) > 3) {
                        Notification::make()->danger()->title('Не больше трёх тестов в минуту')->send();

                        return;
                    }
                    try {
                        $id = app(MailingSender::class)->sendTest($this->campaign(), (string) $data['phone']);
                        Notification::make()->success()->title('Тест отправлен')->body('ID в SMS.BY: '.$id)->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->persistent()->title('Не отправлено')->body($e->getMessage())->send();
                    }
                }),
            Action::make('start')
                ->label('Запустить рассылку')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => $this->campaign()->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Запустить рассылку?')
                ->modalDescription(function (): string {
                    $count = count(app(MailingSender::class)->recipients($this->campaign()));

                    return "Сообщение получат {$count} мобильных номеров из списка «{$this->campaign()->mailingList?->name}» (без отписавшихся). "
                        .'Деньги спишутся с баланса SMS.BY. Отправка начнётся в течение минуты, отменить уже отправленное нельзя.';
                })
                ->modalSubmitActionLabel('Да, отправить')
                ->action(function (): void {
                    try {
                        $count = app(MailingSender::class)->start($this->campaign());
                        Notification::make()->success()->title("Рассылка запущена: {$count} номеров")->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->persistent()->title('Не запущена')->body($e->getMessage())->send();
                    }
                    $this->refreshFormData(['status']);
                }),
            Action::make('cancel')
                ->label('Остановить')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn (): bool => $this->campaign()->status === 'sending')
                ->requiresConfirmation()
                ->modalDescription('Ещё не отправленные сообщения не уйдут. Уже отправленные отозвать нельзя.')
                ->action(function (): void {
                    $campaign = $this->campaign();
                    $campaign->messages()->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Рассылка остановлена']);
                    $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
                    Notification::make()->success()->title('Рассылка остановлена')->send();
                }),
            DeleteAction::make()->visible(fn (): bool => $this->campaign()->status === 'draft'),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->visible(fn (): bool => $this->campaign()->status === 'draft');
    }

    private function campaign(): CrmMailingCampaign
    {
        /** @var CrmMailingCampaign $record */
        $record = $this->record;

        return $record->refresh();
    }
}
