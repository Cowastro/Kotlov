<?php

namespace App\Filament\Supplier\Resources\SupplierOrderRequests\Pages;

use App\Filament\Supplier\Resources\SupplierOrderRequests\SupplierOrderRequestResource;
use App\Models\SupplierOrderRequest;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewSupplierOrderRequest extends ViewRecord
{
    protected static string $resource = SupplierOrderRequestResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('acknowledge')
                ->label('Принять в работу')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === 'sent')
                ->requiresConfirmation()
                ->modalHeading('Принять заявку в работу?')
                ->modalDescription('KOTLOV увидит подтверждение и время принятия заявки.')
                ->schema([
                    Textarea::make('note')->label('Комментарий поставщика')->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->applyRequestTransition('acknowledged', $data['note'] ?? null)),
            Action::make('fulfill')
                ->label('Отметить исполненной')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'acknowledged')
                ->requiresConfirmation()
                ->modalHeading('Подтвердить исполнение заявки?')
                ->modalDescription('Статус станет окончательным и будет виден менеджеру KOTLOV.')
                ->schema([
                    Textarea::make('note')->label('Комментарий поставщика')->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->applyRequestTransition('fulfilled', $data['note'] ?? null)),
            Action::make('reject')
                ->label('Отклонить')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, ['sent', 'acknowledged'], true))
                ->modalHeading('Отклонить заявку?')
                ->modalDescription('Причина обязательна и сразу станет видна менеджеру KOTLOV.')
                ->schema([
                    Textarea::make('note')
                        ->label('Причина отклонения')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->applyRequestTransition('rejected', $data['note'] ?? null)),
        ];
    }

    private function applyRequestTransition(string $status, ?string $note): void
    {
        $this->record = app(SupplierOrderRequestWorkflow::class)->respond(
            $this->record,
            $status,
            auth()->user(),
            $note,
        );

        Notification::make()
            ->success()
            ->title('Статус заявки обновлён')
            ->body(SupplierOrderRequest::STATUSES[$status] ?? $status)
            ->send();
    }
}
