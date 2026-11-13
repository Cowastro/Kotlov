<?php

namespace App\Services\CommercialDocuments;

use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\B2bReconciliationStatement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class B2bReconciliationStatements
{
    /** @return array<string, mixed> */
    public function snapshot(B2bClient $client, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $entries = $client->accountingEntries()
            ->whereBetween('occurred_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
        $openingEntries = $client->accountingEntries()
            ->where('occurred_at', '<', $dateFrom->startOfDay())
            ->get();
        $opening = round((float) $openingEntries->sum('debit') - (float) $openingEntries->sum('credit'), 2);
        $debit = round((float) $entries->sum('debit'), 2);
        $credit = round((float) $entries->sum('credit'), 2);
        $entrySnapshot = $entries->map(fn ($entry): array => [
            'entry_id' => $entry->id,
            'external_key' => $entry->external_key,
            'type' => $entry->entry_type,
            'occurred_at' => $entry->occurred_at?->toISOString(),
            'document_number' => $entry->document_number,
            'description' => $entry->description,
            'debit' => number_format((float) $entry->debit, 2, '.', ''),
            'credit' => number_format((float) $entry->credit, 2, '.', ''),
            'currency' => $entry->currency,
        ])->values()->all();
        $payload = [
            'b2b_client_id' => $client->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'opening_balance' => number_format($opening, 2, '.', ''),
            'period_debit' => number_format($debit, 2, '.', ''),
            'period_credit' => number_format($credit, 2, '.', ''),
            'closing_balance' => number_format($opening + $debit - $credit, 2, '.', ''),
            'currency' => 'BYN',
            'entries' => $entrySnapshot,
        ];

        return $payload + [
            'statement_hash' => hash('sha256', json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            )),
        ];
    }

    public function current(B2bClient $client, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): ?B2bReconciliationStatement
    {
        $snapshot = $this->snapshot($client, $dateFrom, $dateTo);

        return $client->reconciliationStatements()
            ->whereDate('date_from', $snapshot['date_from'])
            ->whereDate('date_to', $snapshot['date_to'])
            ->where('statement_hash', $snapshot['statement_hash'])
            ->latest('id')
            ->first();
    }

    public function confirmByClient(
        B2bClient $client,
        User $user,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): B2bReconciliationStatement {
        return DB::transaction(function () use ($client, $user, $dateFrom, $dateTo): B2bReconciliationStatement {
            $lockedClient = B2bClient::query()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($lockedClient, $dateFrom, $dateTo);
            if ($snapshot['entries'] === []) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'В выбранном периоде нет подтверждённых проводок 1С для согласования.',
                ]);
            }

            $statement = $lockedClient->reconciliationStatements()
                ->whereDate('date_from', $snapshot['date_from'])
                ->whereDate('date_to', $snapshot['date_to'])
                ->where('statement_hash', $snapshot['statement_hash'])
                ->first();

            if (! $statement) {
                $statement = $lockedClient->reconciliationStatements()->create([
                    'date_from' => $snapshot['date_from'],
                    'date_to' => $snapshot['date_to'],
                    'statement_hash' => $snapshot['statement_hash'],
                    'source' => 'onec',
                    'opening_balance' => $snapshot['opening_balance'],
                    'period_debit' => $snapshot['period_debit'],
                    'period_credit' => $snapshot['period_credit'],
                    'closing_balance' => $snapshot['closing_balance'],
                    'currency' => $snapshot['currency'],
                    'entries_snapshot' => $snapshot['entries'],
                    'status' => 'client_confirmed',
                    'client_confirmed_by' => $user->id,
                    'client_confirmed_at' => now(),
                ]);
            }

            if ($statement->wasRecentlyCreated) {
                $this->audit($lockedClient, $user, 'reconciliation_client_confirmed', $statement);
            }

            return $statement->fresh();
        });
    }

    public function confirmByManager(B2bReconciliationStatement $statement, User $manager): B2bReconciliationStatement
    {
        return DB::transaction(function () use ($statement, $manager): B2bReconciliationStatement {
            $statement = B2bReconciliationStatement::query()->whereKey($statement->id)->lockForUpdate()->firstOrFail();
            if (! $statement->client_confirmed_at) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Сначала акт должен подтвердить партнёр.',
                ]);
            }
            $snapshot = $this->snapshot(
                $statement->client,
                CarbonImmutable::parse($statement->date_from),
                CarbonImmutable::parse($statement->date_to),
            );
            if (! hash_equals($statement->statement_hash, $snapshot['statement_hash'])) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'После подтверждения партнёра проводки изменились. Нужна новая версия акта.',
                ]);
            }
            if ($statement->manager_confirmed_at) {
                return $statement;
            }

            $statement->update([
                'status' => 'agreed',
                'manager_confirmed_by' => $manager->id,
                'manager_confirmed_at' => now(),
            ]);
            $this->audit($statement->client, $manager, 'reconciliation_manager_confirmed', $statement);

            return $statement->fresh();
        });
    }

    private function audit(
        B2bClient $client,
        User $actor,
        string $event,
        B2bReconciliationStatement $statement,
    ): void {
        B2bClientAuditLog::query()->create([
            'b2b_client_id' => $client->id,
            'actor_user_id' => $actor->id,
            'event' => $event,
            'metadata' => [
                'statement_id' => $statement->id,
                'statement_hash' => $statement->statement_hash,
                'date_from' => $statement->date_from?->toDateString(),
                'date_to' => $statement->date_to?->toDateString(),
                'closing_balance' => $statement->closing_balance,
                'currency' => $statement->currency,
            ],
        ]);
    }
}
