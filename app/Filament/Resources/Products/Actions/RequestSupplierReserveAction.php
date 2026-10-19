<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use App\Services\Orders\SupplierReservationMailService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;

final class RequestSupplierReserveAction
{
    public static function make(): Action
    {
        return Action::make('request_supplier_reserve')
            ->label('Запросить резерв')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->tooltip('Отправить поставщику запрос наличия и резерва')
            ->visible(fn (Product $record): bool => app(SupplierReservationMailService::class)
                ->hasProductSupplier($record))
            ->modalHeading(fn (Product $record): string => 'Запрос резерва · '.$record->name)
            ->modalDescription('Письмо уйдёт с order@kotlov.by, сохранится в почте и будет привязано к этому товару.')
            ->fillForm(function (Product $record): array {
                $service = app(SupplierReservationMailService::class);
                $supplierId = array_key_first($service->productSupplierOptions($record));

                return [
                    'supplier_id' => $supplierId,
                    'recipient' => $supplierId ? $service->supplierEmail((int) $supplierId) : null,
                    'quantity' => 1,
                    'note' => 'Просим подтвердить резерв и возможную дату отгрузки.',
                ];
            })
            ->schema([
                Select::make('supplier_id')
                    ->label('Поставщик')
                    ->options(fn (Product $record): array => app(SupplierReservationMailService::class)
                        ->productSupplierOptions($record))
                    ->live()
                    ->afterStateUpdated(fn (mixed $state, Set $set) => $set(
                        'recipient',
                        $state ? app(SupplierReservationMailService::class)->supplierEmail((int) $state) : null,
                    ))
                    ->required(),
                TextInput::make('quantity')
                    ->label('Количество, шт.')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('recipient')
                    ->label('Email поставщика')
                    ->email()
                    ->required(),
                Textarea::make('note')
                    ->label('Комментарий поставщику')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->modalSubmitActionLabel('Отправить запрос')
            ->action(function (Product $record, array $data): void {
                $thread = app(SupplierReservationMailService::class)->sendProductRequest(
                    $record,
                    (int) $data['supplier_id'],
                    auth()->user(),
                    (int) $data['quantity'],
                    $data['recipient'],
                    $data['note'] ?? null,
                );

                Notification::make()
                    ->success()
                    ->title('Запрос резерва отправлен')
                    ->body('Письмо отправлено с order@kotlov.by и сохранено в переписке #'.$thread->id.'.')
                    ->send();
            });
    }
}
