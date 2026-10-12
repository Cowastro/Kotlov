<?php

namespace App\Services\Crm;

use App\Models\CrmCounterparty;
use App\Models\Supplier;
use App\Services\Crm\Imports\CrmNameNormalizer;

class SupplierCrmLinker
{
    /**
     * Verified trading-name aliases whose legal 1C name is different.
     * Keep this list explicit: supplier links must never be based on fuzzy matching.
     *
     * @var array<string, list<string>>
     */
    private const VERIFIED_NAME_ALIASES = [
        'bania' => ['БАНЬКА'],
    ];

    /** @return array{linked:int,already_linked:int,unmatched:int,ambiguous:int} */
    public function reconcile(bool $apply = false): array
    {
        $index = [];
        CrmCounterparty::query()
            ->select(['id', 'name', 'public_name', 'legal_name'])
            ->each(function (CrmCounterparty $counterparty) use (&$index): void {
                foreach ([$counterparty->name, $counterparty->public_name, $counterparty->legal_name] as $name) {
                    $key = CrmNameNormalizer::organizationKey($name);
                    if ($key !== '') {
                        $index[$key][$counterparty->id] = true;
                    }
                }
            });

        $summary = ['linked' => 0, 'already_linked' => 0, 'unmatched' => 0, 'ambiguous' => 0];

        Supplier::query()->select(['id', 'crm_counterparty_id', 'code', 'name'])->each(
            function (Supplier $supplier) use (&$summary, $index, $apply): void {
                if ($supplier->crm_counterparty_id) {
                    $summary['already_linked']++;

                    return;
                }

                $keys = [CrmNameNormalizer::organizationKey($supplier->name)];
                foreach (self::VERIFIED_NAME_ALIASES[mb_strtolower((string) $supplier->code)] ?? [] as $alias) {
                    $keys[] = CrmNameNormalizer::organizationKey($alias);
                }

                $candidateIds = [];
                foreach (array_unique(array_filter($keys)) as $key) {
                    $candidateIds = array_merge($candidateIds, array_keys($index[$key] ?? []));
                }
                $candidateIds = array_values(array_unique($candidateIds));
                if ($candidateIds === []) {
                    $summary['unmatched']++;

                    return;
                }
                if (count($candidateIds) > 1) {
                    $summary['ambiguous']++;

                    return;
                }

                $summary['linked']++;
                if (! $apply) {
                    return;
                }

                $supplier->update(['crm_counterparty_id' => $candidateIds[0]]);
                CrmCounterparty::query()
                    ->whereKey($candidateIds[0])
                    ->where('classification', 'unclassified')
                    ->update(['classification' => 'supplier']);
            },
        );

        return $summary;
    }
}
