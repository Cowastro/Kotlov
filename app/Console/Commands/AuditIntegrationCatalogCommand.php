<?php

namespace App\Console\Commands;

use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationCatalogAudit;
use Illuminate\Console\Command;

class AuditIntegrationCatalogCommand extends Command
{
    protected $signature = 'integration:audit-catalog {--source=onec : Integration source code} {--sample=5 : Ungrouped product sample size}';

    protected $description = 'Read-only audit of imported integration groups and in-stock products';

    public function handle(IntegrationCatalogAudit $audit): int
    {
        $source = IntegrationSource::query()->where('code', (string) $this->option('source'))->first();
        if (! $source) {
            $this->error('Integration source not found.');

            return self::FAILURE;
        }

        $snapshot = $audit->snapshot($source, (int) $this->option('sample'));
        $this->table(['Metric', 'Value'], [
            ['Source', $snapshot['source_name'].' ('.$snapshot['source'].')'],
            ['Imported groups', $snapshot['groups']],
            ['Groups mapped to site', $snapshot['groups_with_site_category']],
            ['In-stock products', $snapshot['products_in_stock']],
            ['Products with source group', $snapshot['products_grouped']],
            ['Products without source group', $snapshot['products_ungrouped']],
        ]);

        if ($snapshot['ungrouped_sample'] !== []) {
            $this->newLine();
            $this->warn('Ungrouped product sample:');
            $this->table(
                ['ID', 'External ID', 'Name', 'Group references in payload'],
                collect($snapshot['ungrouped_sample'])->map(fn (array $row): array => [
                    $row['id'],
                    $row['external_id'],
                    $row['name'],
                    implode(', ', $row['group_references']) ?: 'not sent',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
