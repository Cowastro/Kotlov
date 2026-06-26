<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnrichThermostudioCommand extends Command
{
    protected $signature = 'supplier:enrich-thermostudio
        {--apply : Write changes to the database}
        {--price-file= : Local Thermostudio XLSX file}
        {--sheet-url= : Google Sheets URL}
        {--brand=* : Process only selected brands, repeatable or comma-separated}
        {--create-new : Create new products from the price list}
        {--sync-retail-prices : Update site retail prices from the price list}
        {--candidate-report=storage/app/reports/thermostudio/candidates-all-tabs.csv : Write candidate CSV}
        {--skip-pricelist : Skip price/stock sync step}
        {--skip-source-discovery : Skip automatic source_url discovery step}
        {--skip-source-enrichment : Skip source_url enrichment step}
        {--source-discovery-limit=0 : Max supplier rows for source_url discovery, 0 means all}
        {--source-discovery-offset=0 : Skip N supplier rows during source_url discovery}
        {--source=teplo : Source index for discovery: teplo, teplodvor, or all}
        {--force-source-discovery : Re-check supplier rows that already have source_url}
        {--refresh-source-index : Rebuild cached source URL index}
        {--source-limit=0 : Max source_url products to enrich, 0 means all}
        {--source-offset=0 : Skip N source_url products}
        {--source-domain= : Enrich only source URLs from a domain}
        {--max-current-attrs= : Enrich only products with this many or fewer current attribute rows}
        {--force-source : Enrich source_url products even if they already have photos/specs/content}
        {--overwrite-images : Replace existing product images during source enrichment}
        {--replace-specs : Replace existing attributes/specs during source enrichment}
        {--min-specs-to-replace=4 : Skip source spec replacement if fewer specs were found}
        {--skip-documents : Do not copy documents/PDFs from source pages}
        {--clear-documents : Remove existing product documents during source enrichment}
        {--skip-ai : Skip AI content generation during source enrichment}
        {--sleep=800 : Delay between source enrichment HTTP requests, ms}';

    protected $description = 'Run the safe Thermostudio pipeline: price list sync, then source_url enrichment.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Thermostudio pipeline will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: Thermostudio pipeline preview only.</>');

        $exitCode = self::SUCCESS;

        if (! (bool) $this->option('skip-pricelist')) {
            $this->newLine();
            $this->info('Step 1/3: Thermostudio price list');

            $arguments = [
                $apply ? '--apply' : '--dry-run' => true,
                '--available-only' => true,
                '--candidate-report' => (string) $this->option('candidate-report'),
            ];

            if ($priceFile = trim((string) $this->option('price-file'))) {
                $arguments['--price-file'] = $priceFile;
            }

            if ($sheetUrl = trim((string) $this->option('sheet-url'))) {
                $arguments['--sheet-url'] = $sheetUrl;
            }

            $brands = $this->brandOptions();
            if ($brands !== []) {
                $arguments['--brand'] = $brands;
            }

            if ((bool) $this->option('create-new')) {
                $arguments['--create-new'] = true;
            }

            if ((bool) $this->option('sync-retail-prices')) {
                $arguments['--sync-retail-prices'] = true;
            }

            $code = $this->call('supplier:sync-thermostudio-pricelist', $arguments);
            $exitCode = $code === self::SUCCESS ? $exitCode : $code;
        }

        if (! (bool) $this->option('skip-source-discovery')) {
            $this->newLine();
            $this->info('Step 2/3: Thermostudio source_url discovery');

            $arguments = [
                '--limit' => (string) max(0, (int) $this->option('source-discovery-limit')),
                '--offset' => (string) max(0, (int) $this->option('source-discovery-offset')),
                '--source' => (string) $this->option('source'),
            ];

            if ($apply) {
                $arguments['--apply'] = true;
            }

            $brands = $this->brandOptions();
            if ($brands !== []) {
                $arguments['--brand'] = $brands;
            }

            if ((bool) $this->option('refresh-source-index')) {
                $arguments['--refresh-index'] = true;
            }

            if ((bool) $this->option('force-source-discovery')) {
                $arguments['--force'] = true;
            }

            $code = $this->call('supplier:discover-thermostudio-sources', $arguments);
            $exitCode = $code === self::SUCCESS ? $exitCode : $code;
        }

        if (! (bool) $this->option('skip-source-enrichment')) {
            $this->newLine();
            $this->info('Step 3/3: Thermostudio source_url enrichment');

            $arguments = [
                '--supplier' => 'thermostudio',
                '--limit' => (string) max(0, (int) $this->option('source-limit')),
                '--offset' => (string) max(0, (int) $this->option('source-offset')),
                '--sleep' => (string) max(300, (int) $this->option('sleep')),
                '--min-specs-to-replace' => (string) max(0, (int) $this->option('min-specs-to-replace')),
            ];

            if ($apply) {
                $arguments['--apply'] = true;
            }

            $brands = $this->brandOptions();
            if (count($brands) === 1) {
                $arguments['--brand'] = $brands[0];
            }

            if ($domain = trim((string) $this->option('source-domain'))) {
                $arguments['--domain'] = $domain;
            }

            if (($maxCurrentAttrs = trim((string) $this->option('max-current-attrs'))) !== '') {
                $arguments['--max-current-attrs'] = $maxCurrentAttrs;
            }

            if ((bool) $this->option('force-source')) {
                $arguments['--force'] = true;
            }

            if ((bool) $this->option('overwrite-images')) {
                $arguments['--overwrite-images'] = true;
            }

            if ((bool) $this->option('replace-specs')) {
                $arguments['--replace-specs'] = true;
            }

            if ((bool) $this->option('skip-documents')) {
                $arguments['--skip-documents'] = true;
            }

            if ((bool) $this->option('clear-documents')) {
                $arguments['--clear-documents'] = true;
            }

            if ((bool) $this->option('skip-ai')) {
                $arguments['--skip-ai'] = true;
            }

            $code = $this->call('supplier:enrich-source-products', $arguments);
            $exitCode = $code === self::SUCCESS ? $exitCode : $code;
        }

        return $exitCode;
    }

    /**
     * @return array<int,string>
     */
    private function brandOptions(): array
    {
        $brands = [];
        foreach ((array) $this->option('brand') as $value) {
            foreach (explode(',', (string) $value) as $brand) {
                $brand = trim($brand);
                if ($brand !== '') {
                    $brands[] = $brand;
                }
            }
        }

        return array_values(array_unique($brands));
    }
}
