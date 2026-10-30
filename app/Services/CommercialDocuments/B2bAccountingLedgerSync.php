<?php

namespace App\Services\CommercialDocuments;

use App\Models\B2bAccountingEntry;
use App\Models\B2bClient;
use App\Models\CommercialDocument;
use App\Models\IntegrationSource;
use App\Models\Order;

class B2bAccountingLedgerSync
{
    public function sync(
        B2bClient $client,
        Order $order,
        CommercialDocument $document,
        IntegrationSource $source,
    ): void {
        $baseKey = trim((string) ($document->accounting_external_id ?: $document->accounting_number));
        if ($baseKey === '' || (float) $document->total <= 0) {
            return;
        }

        $shared = [
            'b2b_client_id' => $client->id,
            'commercial_document_id' => $document->id,
            'order_id' => $order->id,
            'document_number' => $document->accounting_number ?: $document->number,
            'currency' => $document->currency ?: 'BYN',
            'imported_at' => now(),
            'metadata' => [
                'accounting_status' => $document->accounting_status,
                'site_document_number' => $document->number,
                'site_order_number' => $order->number,
            ],
        ];

        B2bAccountingEntry::query()->updateOrCreate([
            'integration_source_id' => $source->id,
            'external_key' => 'invoice:'.$baseKey,
            'entry_type' => 'charge',
        ], $shared + [
            'occurred_at' => $document->issued_at?->startOfDay() ?: $document->accounting_confirmed_at ?: now(),
            'description' => 'Счёт '.($document->accounting_number ?: $document->number),
            'debit' => $document->total,
            'credit' => 0,
        ]);

        if ($document->accounting_payment_status === 'paid') {
            B2bAccountingEntry::query()->updateOrCreate([
                'integration_source_id' => $source->id,
                'external_key' => 'payment:'.$baseKey,
                'entry_type' => 'payment',
            ], $shared + [
                'occurred_at' => $document->accounting_confirmed_at ?: now(),
                'description' => 'Оплата счёта '.($document->accounting_number ?: $document->number),
                'debit' => 0,
                'credit' => $document->total,
            ]);
        }

        if ($document->accounting_payment_status === 'refunded') {
            B2bAccountingEntry::query()->updateOrCreate([
                'integration_source_id' => $source->id,
                'external_key' => 'refund:'.$baseKey,
                'entry_type' => 'refund',
            ], $shared + [
                'occurred_at' => $document->accounting_confirmed_at ?: now(),
                'description' => 'Возврат оплаты по счёту '.($document->accounting_number ?: $document->number),
                'debit' => $document->total,
                'credit' => 0,
            ]);
        }
    }
}
