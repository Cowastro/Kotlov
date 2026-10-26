<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\CommercialDocument;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentClientResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class CreateCommercialDocumentFromOrderAction
{
    public static function make(): Action
    {
        return Action::make('createCommercialDocumentFromOrder')
            ->label('Счёт / КП клиенту')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (): bool => auth()->user() instanceof User
                && auth()->user()->isManager()
                && CommercialDocumentResource::canCreate())
            ->modalHeading('Создать документ из заказа')
            ->modalDescription('Клиент, товары, количество, цены, скидка и доставка будут перенесены из заказа. Перед сохранением всё можно проверить и изменить.')
            ->schema([
                Select::make('type')
                    ->label('Документ')
                    ->options(CommercialDocument::TYPES)
                    ->default('invoice')
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Подготовить документ')
            ->action(fn (Order $record, array $data): RedirectResponse => self::prepare(
                $record,
                (string) $data['type'],
            ));
    }

    public static function prepare(Order $order, string $type): RedirectResponse
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->isManager(), 403);
        abort_unless(array_key_exists($type, CommercialDocument::TYPES), 422);

        $order->loadMissing(['items.product']);

        $document = DB::transaction(function () use ($order, $type): CommercialDocument {
            $document = CommercialDocument::query()->create(self::prefill($order, $type));

            foreach ($order->items as $index => $item) {
                $document->items()->create([
                    'product_id' => $item->product_id,
                    'name' => $item->product_name ?: $item->product?->name ?: 'Товар из заказа',
                    'sku' => $item->product_sku ?: $item->product?->sku,
                    'unit' => $item->product?->unit ?: 'шт.',
                    'quantity' => max(1, (int) $item->quantity),
                    'unit_price' => round((float) $item->price, 2),
                    'discount_percent' => 0,
                    'vat_rate' => 20,
                    'sort_order' => $index,
                ]);
            }

            $document->recalculate();

            return $document->fresh('items');
        });

        app(CommercialDocumentClientResolver::class)->sync($document);

        return redirect(CommercialDocumentResource::getUrl('edit', ['record' => $document]));
    }

    /** @return array<string, mixed> */
    public static function prefill(Order $order, string $type): array
    {
        $delivery = Order::DELIVERY_TYPES[$order->delivery_type] ?? $order->delivery_type;
        $deliveryDetails = collect([
            $delivery,
            $order->delivery_address,
            $order->planned_delivery_date?->format('d.m.Y'),
        ])->filter()->implode(' · ');
        $payment = Order::PAYMENT_TYPES[$order->payment_type] ?? $order->payment_type;

        return [
            'type' => $type,
            'crm_counterparty_id' => $order->crm_counterparty_id,
            'crm_contact_id' => $order->crm_contact_id,
            'client_company' => $order->company_name,
            'client_legal_name' => $order->company_name,
            'client_name' => $order->customer_name,
            'client_email' => $order->company_email ?: $order->customer_email,
            'client_phone' => $order->customer_phone,
            'client_unp' => $order->company_unp,
            'client_address' => $order->company_address ?: $order->delivery_address,
            'currency' => 'BYN',
            'discount_total' => round((float) $order->discount, 2),
            'delivery_price' => round((float) $order->delivery_price, 2),
            'delivery_to_client' => $order->delivery_type !== 'pickup',
            'payment_terms' => $payment ? 'Способ оплаты: '.$payment.'.' : 'Условия оплаты согласовываются с менеджером.',
            'delivery_terms' => $deliveryDetails ?: 'Срок и способ поставки согласовываются с менеджером.',
            'notes' => 'Создано из заказа '.$order->number.'.',
        ];
    }
}
