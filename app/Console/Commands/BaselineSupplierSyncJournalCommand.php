<?php

namespace App\Console\Commands;

use App\Services\SupplierSyncJournalRecorder;
use Illuminate\Console\Command;

class BaselineSupplierSyncJournalCommand extends Command
{
    protected $signature = 'supplier:journal-baseline {--reset : Replace the existing comparison baseline}';

    protected $description = 'Create the supplier sync journal baseline without recording false changes.';

    public function handle(SupplierSyncJournalRecorder $journal): int
    {
        $count = $journal->establishBaseline((bool) $this->option('reset'));
        $this->info("Supplier journal baseline contains {$count} supplier product rows.");

        return self::SUCCESS;
    }
}
