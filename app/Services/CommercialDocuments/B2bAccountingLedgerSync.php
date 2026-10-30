<?php

namespace App\Services\CommercialDocuments;

use App\Models\B2bAccountingEntry;
use App\Models\B2bClient;
use App\Models\CommercialDocument;
use App\Models\IntegrationSource;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class B2bAccountingLedgerSync
{
    /**
     * @param  array<int, array{type?:string, external_id?:string|null, number?:string|null, date?:string|null, amount?:string|int|float|null, currency?:string|null, description?:string|null}>  $explicitEntries
     */
    public function sync(
        B2bClient $client,
        Order $order,
        CommercialDocument $document,
        IntegrationSource $source,
        array $explicitEntries = [],
    ): int {
        $baseKey = trim((string) ($document->accounting_external_id
            ?: $document->accounting_number
            ?: 'site-document-'.$document->id));
        if ((float) $document->total <= 0) {
            return 0;
        }

        $changed = 0;
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

        $changed += $this->upsert([
            'integration_source_id' => $source->id,
            'external_key' => 'invoice:'.$baseKey,
            'entry_type' => 'charge',
        ], array_replace($shared, [
            'occurred_at' => $document->issued_at?->startOfDay() ?: $document->accounting_confirmed_at ?: now(),
            'description' => 'Счёт '.($document->accounting_number ?: $document->number),
            'debit' => $document->total,
            'credit' => 0,
        ]));

        $normalizedEntries = collect($explicitEntries)
            ->map(fn (array $entry): ?array => $this->normalizeEntry($entry, $document))
            ->filter()
            ->values();

        if ($normalizedEntries->isNotEmpty()) {
            $inferredKeys = $normalizedEntries
                ->pluck('entry_type')
                ->unique()
                ->map(fn (string $type): string => $type.':'.$baseKey);
            $inferredEntries = B2bAccountingEntry::query()
                ->where('integration_source_id', $source->id)
                ->whereIn('external_key', $inferredKeys)
                ->get()
                ->filter(fn (B2bAccountingEntry $entry): bool => (bool) data_get($entry->metadata, 'is_inferred', false));
            foreach ($inferredEntries as $inferredEntry) {
                $changed += (int) $inferredEntry->delete();
            }

            foreach ($normalizedEntries as $entry) {
                $externalIdentity = $entry['external_id'] ?: hash('sha256', implode('|', [
                    $entry['entry_type'],
                    $entry['document_number'],
                    $entry['occurred_at']->toDateTimeString(),
                    number_format($entry['amount'], 2, '.', ''),
                ]));
                $isCredit = in_array($entry['entry_type'], ['payment', 'adjustment_credit'], true);
                $entryType = str_starts_with($entry['entry_type'], 'adjustment_') ? 'adjustment' : $entry['entry_type'];
                $changed += $this->upsert([
                    'integration_source_id' => $source->id,
                    'external_key' => $entryType.':'.$externalIdentity,
                    'entry_type' => $entryType,
                ], array_replace($shared, [
                    'occurred_at' => $entry['occurred_at'],
                    'document_number' => $entry['document_number'],
                    'description' => $entry['description'],
                    'debit' => $isCredit ? 0 : $entry['amount'],
                    'credit' => $isCredit ? $entry['amount'] : 0,
                    'currency' => $entry['currency'],
                    'metadata' => $shared['metadata'] + [
                        'is_inferred' => false,
                        'external_document_id' => $entry['external_id'],
                    ],
                ]));
            }
        }

        $hasExplicitPayment = $normalizedEntries->contains(
            fn (array $entry): bool => $entry['entry_type'] === 'payment'
        );
        $hasExplicitRefund = $normalizedEntries->contains(
            fn (array $entry): bool => $entry['entry_type'] === 'refund'
        );

        if ($document->accounting_payment_status === 'paid' && ! $hasExplicitPayment) {
            $changed += $this->upsert([
                'integration_source_id' => $source->id,
                'external_key' => 'payment:'.$baseKey,
                'entry_type' => 'payment',
            ], array_replace($shared, [
                'occurred_at' => $document->accounting_confirmed_at ?: now(),
                'description' => 'Оплата счёта '.($document->accounting_number ?: $document->number),
                'debit' => 0,
                'credit' => $document->total,
                'metadata' => $shared['metadata'] + ['is_inferred' => true],
            ]));
        }

        if ($document->accounting_payment_status === 'refunded' && ! $hasExplicitRefund) {
            $changed += $this->upsert([
                'integration_source_id' => $source->id,
                'external_key' => 'refund:'.$baseKey,
                'entry_type' => 'refund',
            ], array_replace($shared, [
                'occurred_at' => $document->accounting_confirmed_at ?: now(),
                'description' => 'Возврат оплаты по счёту '.($document->accounting_number ?: $document->number),
                'debit' => $document->total,
                'credit' => 0,
                'metadata' => $shared['metadata'] + ['is_inferred' => true],
            ]));
        }

        return $changed;
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $values */
    private function upsert(array $identity, array $values): int
    {
        $entry = B2bAccountingEntry::query()->firstOrNew($identity);
        $entry->fill(Arr::except($values, 'imported_at'));
        if ($entry->exists && ! $entry->isDirty()) {
            return 0;
        }

        $entry->imported_at = now();
        $entry->save();

        return 1;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed>|null */
    private function normalizeEntry(array $entry, CommercialDocument $document): ?array
    {
        $type = trim((string) ($entry['type'] ?? ''));
        if (! in_array($type, ['payment', 'refund', 'adjustment_debit', 'adjustment_credit'], true)) {
            return null;
        }

        $amount = $this->amount($entry['amount'] ?? null);
        if ($amount <= 0 && in_array($type, ['payment', 'refund'], true)) {
            $amount = (float) $document->total;
        }
        if ($amount <= 0) {
            return null;
        }

        return [
            'entry_type' => $type,
            'external_id' => $this->clean($entry['external_id'] ?? null),
            'document_number' => $this->clean($entry['number'] ?? null)
                ?: ($document->accounting_number ?: $document->number),
            'occurred_at' => $this->date($entry['date'] ?? null)
                ?: ($document->accounting_confirmed_at ?: now()),
            'amount' => $amount,
            'currency' => mb_strtoupper($this->clean($entry['currency'] ?? null) ?: ($document->currency ?: 'BYN')),
            'description' => $this->clean($entry['description'] ?? null) ?: match ($type) {
                'payment' => 'Оплата счёта '.($document->accounting_number ?: $document->number),
                'refund' => 'Возврат оплаты по счёту '.($document->accounting_number ?: $document->number),
                default => 'Корректировка по счёту '.($document->accounting_number ?: $document->number),
            },
        ];
    }

    private function amount(mixed $value): float
    {
        $normalized = preg_replace('/[^0-9,.-]/u', '', str_replace(["\u{00A0}", ' '], '', (string) $value));
        $lastComma = strrpos((string) $normalized, ',');
        $lastDot = strrpos((string) $normalized, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $normalized = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', (string) $normalized))
                : str_replace(',', '', (string) $normalized);
        } elseif ($lastComma !== false) {
            $normalized = str_replace(',', '.', (string) $normalized);
        }

        return round(max(0, (float) $normalized), 2);
    }

    private function date(mixed $value): ?Carbon
    {
        $value = $this->clean($value);
        if (! $value) {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd.m.Y H:i:s', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
