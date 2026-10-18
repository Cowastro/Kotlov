<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\Order;
use App\Services\Crm\CrmCounterpartyFromTaxpayerLookup;

class CheckoutInvoiceService
{
    public function __construct(
        private readonly CrmCounterpartyFromTaxpayerLookup $counterparties,
    ) {}

    /** @param array<string, mixed> $company */
    public function createDraft(Order $order, array $company): CommercialDocument
    {
        $crm = $this->counterparties->upsert(
            $company,
            contactName: $order->customer_name,
        );

        $document = CommercialDocument::query()->create([
            'type' => 'invoice',
            'status' => 'draft',
            'crm_counterparty_id' => $crm['counterparty']->id,
            'crm_contact_id' => $crm['contact']?->id,
            'client_name' => $order->customer_name,
            'client_company' => $company['short_name'] ?? $company['legal_name'],
            'client_legal_name' => $company['legal_name'],
            'client_email' => $company['email'],
            'client_phone' => $company['phone'],
            'client_unp' => $company['unp'],
            'client_address' => $company['address'],
            'currency' => 'BYN',
            'discount_total' => $order->discount,
            'delivery_price' => $order->delivery_price,
            'delivery_to_client' => $order->delivery_type !== 'pickup',
            'vat_rate' => 20,
            'payment_terms' => '100% предоплата по счёту после подтверждения наличия менеджером.',
            'delivery_terms' => 'Наличие и срок поставки подтверждает менеджер до отправки счёта.',
            'notes' => 'Черновик создан автоматически из заказа '.$order->number.'. Не отправлять клиенту до ручного подтверждения наличия и срока поставки всех позиций.',
        ]);

        // Публичный заказ не должен назначать клиента ответственным за документ.
        $document->forceFill([
            'created_by' => null,
            'manager_id' => null,
        ])->saveQuietly();

        foreach ($order->items as $position => $item) {
            $document->items()->create([
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'unit' => 'шт.',
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
                'discount_percent' => 0,
                'vat_rate' => 20,
                'sort_order' => $position,
            ]);
        }

        $order->forceFill([
            'commercial_document_id' => $document->id,
            'crm_counterparty_id' => $crm['counterparty']->id,
            'crm_contact_id' => $crm['contact']?->id,
        ])->saveQuietly();

        return $document->fresh('items');
    }
}
