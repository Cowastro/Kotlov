<?php

namespace App\Services\Crm\Imports;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmExternalIdentity;
use App\Models\CrmImportConflict;
use App\Models\CrmImportRun;
use App\Models\TelephonyCall;
use App\Services\Crm\CrmContactMatcher;
use App\Services\Crm\SupplierCrmLinker;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class OneCCrmImporter
{
    public const TYPES = [
        'trade_contacts' => '1С УТ — контактная информация партнёров',
        'accounting_counterparties' => '1С Бухгалтерия — список контрагентов',
    ];

    public function __construct(
        private readonly OneCMxlParser $parser,
        private readonly CrmContactMatcher $callMatcher,
        private readonly SupplierCrmLinker $supplierLinker,
    ) {}

    /** @return array<string, int|string> */
    public function import(string $type, string $path, bool $dryRun = true, ?int $userId = null): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new RuntimeException('Неизвестный тип импорта CRM.');
        }

        $source = $type === 'trade_contacts' ? '1c_trade' : '1c_accounting';
        $rows = $type === 'trade_contacts'
            ? $this->parser->tradeContactReport($path)
            : $this->parser->accountingCounterparties($path);

        $summary = [
            'rows' => count($rows), 'created' => 0, 'matched' => 0, 'conflicts' => 0,
            'identities' => 0, 'contact_points' => 0, 'calls_matched' => 0, 'suppliers_linked' => 0,
        ];

        $run = $dryRun ? null : CrmImportRun::query()->create([
            'source' => $source,
            'import_type' => $type,
            'file_name' => basename($path),
            'checksum' => hash_file('sha256', $path) ?: null,
            'status' => 'processing',
            'rows_count' => count($rows),
            'imported_by' => $userId,
        ]);

        DB::beginTransaction();

        try {
            $nameIndex = $this->nameIndex();

            foreach ($rows as $row) {
                $match = $this->match($source, $row, $nameIndex);

                if ($match['conflict']) {
                    $summary['conflicts']++;
                    if ($run) {
                        CrmImportConflict::query()->create([
                            'import_run_id' => $run->id,
                            'source' => $source,
                            'import_type' => $type,
                            'external_id' => $row['external_id'] ?? null,
                            'external_name' => $row['name'],
                            'reason' => $match['reason'],
                            'candidate_ids' => $match['candidate_ids'],
                            'payload' => $row,
                        ]);
                    }

                    continue;
                }

                $counterparty = $match['counterparty'];
                if (! $counterparty) {
                    $counterparty = CrmCounterparty::query()->create([
                        'name' => $row['name'],
                        'type' => $this->counterpartyType($row),
                        'classification' => 'unclassified',
                        'business_unit' => 'wholesale',
                        'status' => 'active',
                        'source' => $source,
                    ]);
                    $summary['created']++;
                    $nameIndex[CrmNameNormalizer::normalize($counterparty->name)][] = $counterparty->id;
                } else {
                    $summary['matched']++;
                }

                $this->updateCounterparty($counterparty, $source, $row);

                CrmExternalIdentity::query()->updateOrCreate(
                    ['source' => $source, 'external_id' => $row['external_id']],
                    [
                        'counterparty_id' => $counterparty->id,
                        'external_code' => $row['external_code'] ?? null,
                        'external_name' => $row['name'],
                        'payload' => $row,
                        'synced_at' => now(),
                    ],
                );
                $summary['identities']++;

                if ($source === '1c_trade') {
                    $summary['contact_points'] += $this->syncContactPoints($counterparty, $row);
                }
            }

            $summary['suppliers_linked'] = $this->supplierLinker->reconcile(apply: true)['linked'];

            if ($dryRun) {
                DB::rollBack();

                return $summary;
            }

            DB::commit();

            $summary['calls_matched'] = $this->matchCalls();
            $run?->update([
                'status' => 'completed',
                'created_count' => $summary['created'],
                'matched_count' => $summary['matched'],
                'conflict_count' => $summary['conflicts'],
                'contact_point_count' => $summary['contact_points'],
                'call_match_count' => $summary['calls_matched'],
                'summary' => $summary,
                'finished_at' => now(),
            ]);

            return $summary;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $run?->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            throw $exception;
        }
    }

    /** @return array<string, list<int>> */
    private function nameIndex(): array
    {
        $index = [];
        CrmCounterparty::query()->select(['id', 'name', 'legal_name', 'public_name'])
            ->chunkById(1000, function ($counterparties) use (&$index): void {
                foreach ($counterparties as $counterparty) {
                    foreach ([$counterparty->name, $counterparty->legal_name, $counterparty->public_name] as $name) {
                        $key = CrmNameNormalizer::normalize($name);
                        if ($key !== '') {
                            $index[$key][] = $counterparty->id;
                        }
                    }
                }
            });

        foreach ($index as &$ids) {
            $ids = array_values(array_unique($ids));
        }

        return $index;
    }

    /** @param array<string, mixed> $row @param array<string, list<int>> $nameIndex */
    private function match(string $source, array $row, array $nameIndex): array
    {
        $identity = CrmExternalIdentity::query()
            ->where('source', $source)->where('external_id', $row['external_id'])->first();
        if ($identity) {
            return ['counterparty' => $identity->counterparty, 'conflict' => false, 'reason' => null, 'candidate_ids' => []];
        }

        $candidates = collect();

        if (filled($row['unp'] ?? null)) {
            $candidates = CrmCounterparty::query()->where('unp', $row['unp'])->get();
        }

        if ($candidates->isEmpty() && $source === '1c_trade') {
            $normalized = collect($row['phones'] ?? [])->map(fn (string $phone): ?string => PhoneNumber::normalize($phone))
                ->filter()->merge(collect($row['emails'] ?? [])->map(fn (string $email): string => mb_strtolower($email)))->unique();
            if ($normalized->isNotEmpty()) {
                $candidateIds = CrmContactPoint::query()
                    ->whereIn('normalized_value', $normalized)
                    ->with('contact:id,counterparty_id')
                    ->get()
                    ->map(fn (CrmContactPoint $point): ?int => $point->counterparty_id ?: $point->contact?->counterparty_id)
                    ->filter()->unique()->values();
                $candidates = CrmCounterparty::query()->whereKey($candidateIds)->get();
            }
        }

        if ($candidates->isEmpty()) {
            $nameCandidates = [];
            foreach ([$row['name'] ?? null, $row['legal_name'] ?? null, $row['public_name'] ?? null] as $name) {
                $key = CrmNameNormalizer::normalize($name);
                $nameCandidates = array_merge($nameCandidates, $key !== '' ? ($nameIndex[$key] ?? []) : []);
            }
            $candidates = CrmCounterparty::query()->whereKey(array_values(array_unique($nameCandidates)))->get();
        }

        if ($candidates->count() > 1) {
            return [
                'counterparty' => null, 'conflict' => true,
                'reason' => 'Найдено несколько возможных карточек CRM',
                'candidate_ids' => $candidates->pluck('id')->all(),
            ];
        }

        return [
            'counterparty' => $candidates->first(), 'conflict' => false,
            'reason' => null, 'candidate_ids' => [],
        ];
    }

    /** @param array<string, mixed> $row */
    private function updateCounterparty(CrmCounterparty $counterparty, string $source, array $row): void
    {
        $data = array_filter([
            'public_name' => $row['public_name'] ?? null,
            'legal_name' => $row['legal_name'] ?? null,
            'unp' => $row['unp'] ?? null,
            'business_region' => $row['business_region'] ?? null,
            'one_c_manager_name' => $row['manager_name'] ?? null,
            'registered_at' => $row['registered_at'] ?? null,
            'abc_xyz_classification' => $row['abc_xyz_classification'] ?? null,
            'legal_address' => $row['legal_address'] ?? null,
            'postal_address' => $row['postal_address'] ?? null,
            'actual_address' => $row['actual_address'] ?? null,
        ], static fn ($value): bool => filled($value));

        // УТ хранит рабочую карточку партнёра и имеет приоритет по названию.
        // Бухгалтерия обогащает её УНП и юридическими данными, не переименовывая.
        if ($source === '1c_trade' || $counterparty->source === '1c_accounting') {
            $data['name'] = $row['name'];
        }

        if (blank($counterparty->external_1c_id)) {
            $data['external_1c_id'] = $row['external_id'];
        }
        if ($counterparty->source === 'manual' && $counterparty->wasRecentlyCreated) {
            $data['source'] = $source;
        }

        $counterparty->fill($data)->save();
    }

    /** @param array<string, mixed> $row */
    private function syncContactPoints(CrmCounterparty $counterparty, array $row): int
    {
        $created = 0;
        foreach (['phone' => $row['phones'] ?? [], 'email' => $row['emails'] ?? []] as $type => $values) {
            foreach ($values as $value) {
                $normalized = $type === 'phone' ? PhoneNumber::normalize($value) : mb_strtolower($value);
                if (! $normalized) {
                    continue;
                }

                $point = CrmContactPoint::query()->firstOrCreate(
                    ['counterparty_id' => $counterparty->id, 'type' => $type, 'normalized_value' => $normalized],
                    [
                        'contact_id' => null, 'value' => $value, 'label' => '1С УТ',
                        'is_primary' => ! CrmContactPoint::query()->where('counterparty_id', $counterparty->id)->where('type', $type)->exists(),
                        'is_active' => true,
                    ],
                );
                if ($point->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /** @param array<string, mixed> $row */
    private function counterpartyType(array $row): string
    {
        $type = mb_strtolower((string) ($row['type_1c'] ?? ''));

        return str_contains($type, 'физ') ? 'person' : 'organization';
    }

    private function matchCalls(): int
    {
        $matched = 0;
        TelephonyCall::query()->whereNotNull('customer_phone')->whereNull('crm_counterparty_id')
            ->chunkById(500, function ($calls) use (&$matched): void {
                foreach ($calls as $call) {
                    if ($this->callMatcher->attachToCall($call)) {
                        $call->save();
                        $matched++;
                    }
                }
            });

        return $matched;
    }
}
