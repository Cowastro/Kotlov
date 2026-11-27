<?php

namespace App\Filament\Resources\VamExpress\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressSettings;
use App\Support\Shipping\VamExpressStatus;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Livewire\Attributes\Locked;

class ViewVamExpressShipment extends ViewRecord
{
    protected static string $resource = VamExpressShipmentResource::class;

    #[Locked]
    public ?int $statusCheckRequestedAt = null;

    public function getTitle(): string
    {
        return 'Отправление ВАМ Экспресс '.$this->record->display_id;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Доставка')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('order.number')->label('Заказ KOTLOV')->placeholder('Не привязан')
                    ->url(fn (VamExpressShipment $record): ?string => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null),
                TextEntry::make('carrier_status')->label('Статус перевозчика')->badge()->placeholder('Статус ещё не проверен')
                    ->color(fn (?string $state): array => VamExpressStatus::color($state)),
                TextEntry::make('city')->label('Город доставки')->placeholder('—'),
                TextEntry::make('checked_at')->label('Последняя проверка (Минск)')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('Ещё не проверялось')
                    ->helperText(fn (): ?string => $this->statusCheckRequestedAt ? 'Проверка в очереди' : null)
                    ->extraAttributes(fn (): array => $this->statusCheckRequestedAt
                        ? ['wire:poll.10s.visible' => 'refreshCheckedStatus']
                        : []),
                TextEntry::make('cargo')->label('Груз')->placeholder('—'),
                TextEntry::make('delivery_cost')->label('Тариф ВАМ')->money('BYN')->placeholder('—'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')->label('Обновить статус')->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => VamExpressShipmentResource::canView($this->getRecord()))
                ->disabled(fn (): bool => $this->statusCheckRequestedAt !== null)
                ->action(function (VamExpressSettings $settings): void {
                    $requestedAt = now()->timestamp;
                    try {
                        $settings->requestSync($this->getRecord()->remote_id);
                    } catch (VamExpressException | LockTimeoutException $exception) {
                        Notification::make()->danger()->title($exception instanceof VamExpressException
                            ? $exception->getMessage()
                            : 'Обмен ВАМ Экспресс занят. Попробуйте чуть позже.')->send();

                        return;
                    }

                    $this->statusCheckRequestedAt = $requestedAt;
                    Notification::make()->success()->title('Проверка поставлена в очередь')->send();
                }),
        ];
    }

    public function refreshCheckedStatus(): void
    {
        if ($this->statusCheckRequestedAt === null) {
            return;
        }

        $shipment = $this->getRecord()->refresh();
        if ($shipment->checked_at?->timestamp >= $this->statusCheckRequestedAt) {
            $this->statusCheckRequestedAt = null;
        } elseif (now()->timestamp - $this->statusCheckRequestedAt >= 240) {
            $this->statusCheckRequestedAt = null;
            Notification::make()->warning()->title('Перевозчик пока не подтвердил проверку.')->send();
        }
    }
}
