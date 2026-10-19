<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\SupplierOrderRequestBuilder;
use App\Services\Orders\SupplierReservationMailService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class RequestOrderSupplierReserveAction
{
    public static function make(): Action
    {
        return Action::make('requestSupplierReserve')
            ->label('Запросить резерв')
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->button()
            ->size('sm')
            ->visible(fn (Order $record): bool => $record->items->contains(
                fn (OrderItem $item): bool => $item->product_id !== null,
            ))
            ->modalHeading(fn (Order $record): string => 'Запросить резерв · '.$record->number)
            ->modalDescription('Выберите поставщика для каждой нужной позиции. Позиции одного поставщика объединятся в одно письмо с order@kotlov.by.')
            ->modalWidth('6xl')
            ->fillForm(fn (Order $record): array => [
                'items' => self::requestableItems($record)
                    ->map(fn (OrderItem $item): array => [
                        'item_id' => $item->id,
                        'product_label' => $item->product_name.' · '.$item->quantity.' шт.'.($item->product_sku ? ' · '.$item->product_sku : ''),
                        'supplier_id' => self::defaultSupplierId($item),
                    ])->values()->all(),
                'note' => 'Просим подтвердить наличие, актуальную входную цену и поставить товар в резерв. Сообщите срок возможной отгрузки.',
            ])
            ->schema([
                Repeater::make('items')
                    ->label('Позиции заказа')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->schema([
                        Hidden::make('item_id'),
                        TextInput::make('product_label')
                            ->label('Товар')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(7),
                        Select::make('supplier_id')
                            ->label('Кому отправить')
                            ->placeholder('Не запрашивать эту позицию')
                            ->options(fn (Get $get): array => self::supplierOptions((int) $get('item_id')))
                            ->searchable()
                            ->columnSpan(5),
                    ]),
                Textarea::make('note')
                    ->label('Комментарий поставщикам')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->modalSubmitActionLabel('Отправить запросы')
            ->action(function (Order $record, array $data): void {
                $selections = collect($data['items'] ?? [])
                    ->filter(fn (array $item): bool => filled($item['supplier_id'] ?? null));

                if ($selections->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'Выберите хотя бы одного поставщика.',
                    ]);
                }

                $user = auth()->user();
                if (! $user) {
                    throw ValidationException::withMessages(['items' => 'Не удалось определить менеджера.']);
                }

                $mail = app(SupplierReservationMailService::class);
                $prepared = $selections->map(function (array $selection) use ($record, $mail): array {
                    $item = $record->items->firstWhere('id', (int) $selection['item_id']);
                    if (! $item?->product) {
                        throw ValidationException::withMessages([
                            'items' => 'Одна из позиций не связана с карточкой товара.',
                        ]);
                    }

                    $supplierId = (int) $selection['supplier_id'];
                    $snapshot = $mail->productSupplierSnapshot($item->product, $supplierId);
                    $recipient = $mail->supplierEmail($supplierId);
                    if (! $recipient) {
                        throw ValidationException::withMessages([
                            'items' => 'У поставщика «'.$snapshot['supplier']->name.'» не указан email.',
                        ]);
                    }

                    $existing = $record->supplierOrderRequests()
                        ->where('supplier_id', $supplierId)
                        ->where('route', 'supplier_purchase')
                        ->whereNotIn('status', ['draft', 'cancelled'])
                        ->first();
                    if ($existing) {
                        throw ValidationException::withMessages([
                            'items' => 'Поставщику «'.$snapshot['supplier']->name.'» запрос уже отправляли: '.$existing->number.'.',
                        ]);
                    }

                    return [
                        'item' => $item,
                        'supplier' => $snapshot['supplier'],
                        'purchase_price' => $snapshot['purchase_price'],
                        'recipient' => $recipient,
                    ];
                });

                foreach ($prepared as $selection) {
                    app(OrderItemFulfillmentManager::class)->confirm(
                        $selection['item'],
                        'supplier_purchase',
                        $selection['supplier'],
                        $user,
                        'Поставщик выбран при отправке запроса резерва из списка заказов.',
                        $selection['purchase_price'],
                    );
                }

                $sent = 0;
                foreach ($prepared->groupBy(fn (array $selection): int => $selection['supplier']->id) as $supplierId => $supplierItems) {
                    $request = app(SupplierOrderRequestBuilder::class)
                        ->buildDrafts($record, $user, (int) $supplierId, 'supplier_purchase')
                        ->first();

                    if (! $request) {
                        continue;
                    }

                    $mail->sendOrderRequest(
                        $request,
                        $user,
                        (string) $supplierItems->first()['recipient'],
                        $data['note'] ?? null,
                    );
                    $sent++;
                }

                Notification::make()
                    ->success()
                    ->title('Запросы резерва отправлены')
                    ->body('Писем поставщикам: '.$sent.' · позиций: '.$prepared->count().'. Статусы показаны в колонке «Резерв».')
                    ->send();
            });
    }

    /** @return Collection<int, OrderItem> */
    private static function requestableItems(Order $order): Collection
    {
        $order->loadMissing([
            'items.product.supplierProducts.supplier.crmCounterparty.contactPoints',
            'items.product.integrationProducts.source.supplier.crmCounterparty.contactPoints',
            'items.fulfillmentSupplier.crmCounterparty.contactPoints',
            'items.supplySupplier.crmCounterparty.contactPoints',
            'supplierOrderRequests',
        ]);

        return $order->items
            ->filter(fn (OrderItem $item): bool => $item->product !== null && self::supplierOptions($item->id) !== []);
    }

    /** @return array<int, string> */
    private static function supplierOptions(int $itemId): array
    {
        $item = OrderItem::query()->with('product')->find($itemId);
        if (! $item?->product) {
            return [];
        }

        $mail = app(SupplierReservationMailService::class);

        return collect($mail->productSupplierOptions($item->product))
            ->map(function (string $label, int|string $supplierId) use ($mail): string {
                $email = $mail->supplierEmail((int) $supplierId);

                return $label.' · '.($email ?: 'email не указан');
            })
            ->all();
    }

    private static function defaultSupplierId(OrderItem $item): ?int
    {
        $options = self::supplierOptions($item->id);
        foreach ([$item->fulfillment_supplier_id, $item->supply_supplier_id] as $supplierId) {
            if ($supplierId && array_key_exists($supplierId, $options)) {
                return (int) $supplierId;
            }
        }

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }
}
