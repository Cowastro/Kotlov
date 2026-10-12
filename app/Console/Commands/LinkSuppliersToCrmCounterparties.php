<?php

namespace App\Console\Commands;

use App\Services\Crm\SupplierCrmLinker;
use Illuminate\Console\Command;

class LinkSuppliersToCrmCounterparties extends Command
{
    protected $signature = 'crm:link-suppliers {--apply : Save unique supplier-to-CRM matches}';

    protected $description = 'Link suppliers to CRM counterparties by a unique normalized organization name';

    public function handle(SupplierCrmLinker $linker): int
    {
        $apply = (bool) $this->option('apply');
        $summary = $linker->reconcile($apply);

        $this->table(['Result', 'Count'], collect($summary)->map(
            fn (int $count, string $label): array => [$label, $count],
        )->values()->all());
        $this->info($apply ? 'Unique matches were saved.' : 'Dry run only. Use --apply to save.');

        return self::SUCCESS;
    }
}
