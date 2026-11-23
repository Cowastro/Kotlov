<?php

namespace App\Filament\Resources\VamExpressDrafts\Pages;

use App\Filament\Concerns\HasVamExpressNavigation;
use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use App\Services\Shipping\VamExpressDrafts;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressQuote;
use App\Services\Shipping\VamExpressReference;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditVamExpressDraft extends EditRecord
{
    use HasVamExpressNavigation;

    protected static string $resource = VamExpressDraftResource::class;

    #[Locked]
    public int $loadedVersion = 0;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Сохранить черновик');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['payload'] = $this->record->payload;
        if (blank($data['payload']['sender_city_id'] ?? null)) {
            try {
                $data['payload'] = array_replace(app(VamExpressReference::class)->sender(), $data['payload']);
            } catch (VamExpressException) {
                // A saved local draft remains editable while the carrier is unavailable.
            }
        }

        return $data;
    }

    protected function afterFill(): void
    {
        $this->loadedVersion = $this->record->version;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $saved = app(VamExpressDrafts::class)->save($record, $data['payload'], $this->loadedVersion, auth()->user());
        $this->loadedVersion = $saved->version;

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('estimate')->label('Сохранить и рассчитать')->icon('heroicon-o-calculator')
            ->action(function (): void {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $this->record->refresh();
                try {
                    $quote = app(VamExpressQuote::class)->estimate($this->record->payload);
                    app(VamExpressDrafts::class)->attachQuote($this->record, $quote, $this->loadedVersion, auth()->user());
                    $this->record->refresh();
                    $this->loadedVersion = $this->record->version;
                    Notification::make()->success()->title('Предварительно '.$quote['amount'].' BYN')->body('Черновик и расчёт сохранены. Заявка перевозчику не отправлялась.')->send();
                } catch (VamExpressException $e) {
                    Notification::make()->warning()->title('Черновик сохранён')->body($e->getMessage())->send();
                } catch (ValidationException $e) {
                    Notification::make()->warning()->title('Для расчёта заполните поля')->body(implode(' ', array_slice($e->validator->errors()->all(), 0, 3)))->send();
                }
            })];
    }
}
