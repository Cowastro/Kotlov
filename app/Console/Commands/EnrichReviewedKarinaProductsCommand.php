<?php

namespace App\Console\Commands;

use App\Services\PriceList\EnrichReviewedKarinaProducts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnrichReviewedKarinaProductsCommand extends Command
{
    protected $signature = 'supplier:enrich-reviewed-karina-products
        {--report-blob= : Exact Git blob SHA of the reviewed KARINA audit}
        {--source-snapshot= : Exact SHA-256 of the official-content dry-run plan}
        {--apply : Apply the previously generated official-content plan}';

    protected $description = 'Dry-run or apply official karina.market content to reviewed hidden KARINA cards';

    public function handle(EnrichReviewedKarinaProducts $service): int
    {
        return Cache::lock('karina-reviewed-content-enrichment', 1800)->block(10, function () use ($service): int {
            $auditPath = storage_path('app/reports/suppliers/karina/audit.json');
            $json = file_get_contents($auditPath);
            $blob = sha1('blob '.strlen($json)."\0".$json);
            if (! preg_match('/^[a-f0-9]{40}$/', (string) $this->option('report-blob'))
                || ! hash_equals($blob, (string) $this->option('report-blob'))) {
                throw new RuntimeException('Enrichment report changed; audit must be reviewed again.');
            }
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $source = Storage::disk('local')->path('pricelists/karina-thread-281.xlsx');
            if (! hash_equals($report['workbook']['sha256'], hash_file('sha256', $source))) {
                throw new RuntimeException('Staged workbook changed since reviewed enrichment audit.');
            }

            $planPath = storage_path('app/reports/suppliers/karina/enrich-plan.json');
            File::ensureDirectoryExists(dirname($planPath));
            if ($this->option('apply')) {
                if (! is_file($planPath)) {
                    throw new RuntimeException('Official-content dry-run plan is missing.');
                }
                $planJson = file_get_contents($planPath);
                $planHash = hash('sha256', $planJson);
                if (! preg_match('/^[a-f0-9]{64}$/', (string) $this->option('source-snapshot'))
                    || ! hash_equals($planHash, (string) $this->option('source-snapshot'))) {
                    throw new RuntimeException('Official-content snapshot changed; dry run must be reviewed again.');
                }
                $plan = json_decode($planJson, true, 512, JSON_THROW_ON_ERROR);
                $result = $service->apply($report, $plan, true);
            } else {
                $plan = $service->buildPlan($report);
                $planJson = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                File::put($planPath, $planJson);
                $planHash = hash('sha256', $planJson);
                $result = $service->apply($report, $plan, false);
            }

            $receipt = [
                'applied' => (bool) $this->option('apply'),
                'report_blob' => $blob,
                'workbook_sha256' => $report['workbook']['sha256'],
                'source_snapshot_sha256' => $planHash,
                'generated_at' => now()->toIso8601String(),
            ] + $result + ['source_summary' => $plan['summary']];
            File::put(
                storage_path('app/reports/suppliers/karina/enrich.json'),
                json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n"
            );
            foreach ($result as $key => $value) {
                $this->line('metric.'.$key.'='.(int) $value);
            }

            return self::SUCCESS;
        });
    }
}
