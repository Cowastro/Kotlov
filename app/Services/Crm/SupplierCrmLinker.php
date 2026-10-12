<?php

namespace App\Services\Crm;

use App\Models\CrmCounterparty;
use App\Models\Supplier;
use App\Services\Crm\Imports\CrmNameNormalizer;

class SupplierCrmLinker
{
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

        Supplier::query()->select(['id', 'crm_counterparty_id', 'name'])->each(
            function (Supplier $supplier) use (&$summary, $index, $apply): void {
                if ($supplier->crm_counterparty_id) {
                    $summary['already_linked']++;

                    return;
                }

                $key = CrmNameNormalizer::organizationKey($supplier->name);
                $candidateIds = array_keys($index[$key] ?? []);
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
