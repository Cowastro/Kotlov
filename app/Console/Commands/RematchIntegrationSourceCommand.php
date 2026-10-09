<?php

namespace App\Console\Commands;

use App\Services\Integrations\CommerceMlCatalogImporter;
use Illuminate\Console\Command;

class RematchIntegrationSourceCommand extends Command
{
    protected $signature = 'integration:rematch-source {source}';

    protected $description = 'Recalculate unconfirmed product matches for an integration source';

    public function handle(CommerceMlCatalogImporter $importer): int
    {
        $stats = $importer->rematchSource((string) $this->argument('source'));

        $this->table(['Status', 'Count'], collect($stats)
            ->map(fn (int $count, string $status): array => [$status, $count])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
