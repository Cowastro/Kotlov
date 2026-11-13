<?php

namespace App\Services\CommercialDocuments;

use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\CommercialDocument;
use App\Models\IntegrationSource;
use App\Models\Order;

class CommercialDocumentAccountingSync
{
    public function __construct(private readonly B2bAccountingLedgerSync $ledger) {}

    public function sync(
        Order $order,
        IntegrationSource $source,
        ?string $externalId,
        ?string $number,
        ?string $status,
        ?string $paymentStatus,
        array $accountingEntries = [],
    ): ?CommercialDocument {
        $document = $order->commercialDocument;
        if (! $document || $document->type !== 'invoice') {
            return null;
        }

        $incoming = array_filter([
            'accounting_source_id' => $source->id,
            'accounting_external_id' => $this->clean($externalId),
            'accounting_number' => $this->clean($number),
            'accounting_status' => $this->clean($status),
            'accounting_payment_status' => $this->clean($paymentStatus),
        ], static fn (mixed $value): bool => filled($value));

        if (count($incoming) === 1 && $accountingEntries === []) {
            return null;
        }

        $changes = collect($incoming)
            ->filter(fn (mixed $value, string $field): bool => (string) $document->{$field} !== (string) $value)
            ->all();
        if ($changes !== []) {
            $changes['accounting_confirmed_at'] = now();
            if ($paymentStatus === 'paid' && ! in_array($document->status, ['cancelled', 'rejected'], true)) {
                $changes['status'] = 'paid';
                $changes['closed_at'] = $document->closed_at ?: now();
            }
            $document->forceFill($changes)->saveQuietly();
        }

        $client = $this->clientFor($order);
        if ($client) {
            $ledgerChanges = $this->ledger->sync($client, $order, $document, $source, $accountingEntries);
            if ($changes !== []) {
                $this->audit($client, $order, $document, $source, 'invoice_accounting_synced');
            }
            if ($ledgerChanges > 0) {
                $this->audit($client, $order, $document, $source, 'accounting_ledger_synced', [
                    'entries_changed' => $ledgerChanges,
                ]);
            }
        }

        return $document->fresh();
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function clientFor(Order $order): ?B2bClient
    {
        $snapshotClientId = (int) data_get($order->b2b_commercial_terms_snapshot, 'b2b_client_id', 0);
        if ($snapshotClientId > 0) {
            return B2bClient::query()->find($snapshotClientId);
        }

        return B2bClient::query()
            ->where('user_id', $order->user_id)
            ->where('status', 'approved')
            ->first();
    }

    /** @param array<string, mixed> $extra */
    private function audit(
        B2bClient $client,
        Order $order,
        CommercialDocument $document,
        IntegrationSource $source,
        string $event,
        array $extra = [],
    ): void {
        B2bClientAuditLog::query()->create([
            'b2b_client_id' => $client->id,
            'actor_user_id' => null,
            'event' => $event,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'document_id' => $document->id,
                'document_number' => $document->number,
                'accounting_number' => $document->accounting_number,
                'payment_status' => $document->accounting_payment_status,
                'source_id' => $source->id,
            ] + $extra,
        ]);
    }
}
