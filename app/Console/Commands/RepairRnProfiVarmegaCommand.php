<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RepairRnProfiVarmegaCommand extends Command
{
    protected $signature = 'supplier:repair-rn-profi-varmega
        {--apply : Write supplier/product updates, default is dry-run}
        {--limit= : Optional row limit for tests; omit to process all Varmega rows}
        {--offset=0 : Optional row offset for tests}
        {--price-file=storage/app/supplier-cache/rn-profi-pricelist.xlsx : Local RN-Profi XLSX/CSV file}
        {--max-delivery-days=3 : Maximum delivery days accepted as available}
        {--probe-limit=1000 : Maximum missing Varmega rows to probe by guessed official URLs}
        {--enrich : Enrich products with official varmega.ru source URLs after sync}
        {--enrich-force : Re-enrich already filled products too}
        {--enrich-created-today : Enrich only products created today}
        {--enrich-created-from= : Enrich only products created from this date/time}
        {--enrich-created-to= : Enrich only products created before this date/time}
        {--enrich-sleep=1200 : Sleep in milliseconds between source page requests}
        {--create-missing : Create missing Varmega products from the price list; off by default to avoid duplicates}
        {--deduplicate : Run duplicate audit/archive after sync}
        {--deduplicate-apply : Archive duplicate unbound products, implies --deduplicate}
        {--skip-sync : Skip RN-Profi sync step}
        {--skip-dedupe-slugs : Do not move clean slugs to kept supplier-linked cards}';

    protected $description = 'One-command Varmega repair for RN-Profi: sync missing rows by article, attach official URLs, update prices, and optionally deduplicate.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $deduplicate = (bool) $this->option('deduplicate') || (bool) $this->option('deduplicate-apply');
        $dedupeApply = (bool) $this->option('deduplicate-apply');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Varmega RN-Profi repair will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: no changes will be written.</>');

        if (! (bool) $this->option('skip-sync')) {
            $syncCode = $this->callChildCommand('supplier:sync-rn-profi', array_filter([
                '--apply' => $apply,
                '--dry-run' => ! $apply,
                '--limit' => $this->option('limit') !== null ? (string) $this->option('limit') : null,
                '--offset' => (string) $this->option('offset'),
                '--price-file' => (string) $this->option('price-file'),
                '--brand' => ['Varmega'],
                '--available-only' => true,
                '--max-delivery-days' => (string) $this->option('max-delivery-days'),
                '--varmega-official' => true,
                '--varmega-probe-missing' => true,
                '--varmega-probe-limit' => (string) $this->option('probe-limit'),
                '--create-unmatched-from-price' => (bool) $this->option('create-missing'),
                '--sync-retail-prices' => true,
            ], fn ($value): bool => $value !== false && $value !== null));

            if ($syncCode !== self::SUCCESS) {
                $this->error('RN-Profi Varmega sync failed.');
                return $syncCode;
            }
        }

        if ((bool) $this->option('enrich')) {
            $enrichCode = $this->callChildCommand('supplier:enrich-source-products', array_filter([
                '--apply' => $apply,
                '--supplier' => 'rn-profi',
                '--brand' => 'Varmega',
                '--domain' => 'varmega.ru',
                '--force' => (bool) $this->option('enrich-force'),
                '--created-today' => (bool) $this->option('enrich-created-today'),
                '--created-from' => trim((string) $this->option('enrich-created-from')) ?: null,
                '--created-to' => trim((string) $this->option('enrich-created-to')) ?: null,
                '--sleep' => (string) $this->option('enrich-sleep'),
            ], fn ($value): bool => $value !== false && $value !== null));

            if ($enrichCode !== self::SUCCESS) {
                $this->error('Varmega source enrichment failed.');
                return $enrichCode;
            }
        } else {
            $this->newLine();
            $this->line('Source enrichment skipped. Add <fg=green>--enrich</> to fill photos/specs/content from varmega.ru.');
        }

        if ($deduplicate) {
            $dedupeCode = $this->callChildCommand('products:deduplicate', array_filter([
                '--apply' => $dedupeApply,
                '--brand' => 'Varmega',
                '--prefer-supplier' => 'rn-profi',
                '--only-unbound' => true,
                '--fix-slugs' => ! (bool) $this->option('skip-dedupe-slugs'),
            ], fn ($value): bool => $value !== false && $value !== null));

            if ($dedupeCode !== self::SUCCESS) {
                $this->error('Varmega deduplication failed.');
                return $dedupeCode;
            }
        } else {
            $this->newLine();
            $this->line('Deduplication skipped. Add <fg=green>--deduplicate</> for audit or <fg=green>--deduplicate-apply</> to archive unbound duplicates.');
        }

        return self::SUCCESS;
    }

    private function callChildCommand(string $command, array $parameters): int
    {
        $this->newLine();
        $this->line('<fg=cyan>Running:</> ' . $command);

        $code = Artisan::call($command, $parameters);
        $this->output->write(Artisan::output());

        return $code;
    }
}
