<?php

namespace App\Console\Commands;

use App\Services\PriceList\CreateReviewedKarinaProducts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CreateReviewedKarinaProductsCommand extends Command
{
    protected $signature = 'supplier:create-reviewed-karina-products
        {--report-blob= : Exact Git blob SHA of the reviewed creation audit}
        {--apply : Create and link reviewed hidden products}';

    protected $description = 'Create only cross-catalog-reviewed KARINA products as hidden cards';

    public function handle(CreateReviewedKarinaProducts $service): int
    {
        return Cache::lock('karina-reviewed-product-creation', 600)->block(10, function () use ($service): int {
            $path = storage_path('app/reports/suppliers/karina/audit.json');
            $json = file_get_contents($path);
            $blob = sha1('blob '.strlen($json)."\0".$json);
            if (! preg_match('/^[a-f0-9]{40}$/', (string) $this->option('report-blob'))
                || ! hash_equals($blob, (string) $this->option('report-blob'))) {
                throw new RuntimeException('Creation report changed; audit must be reviewed again.');
            }

            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $source = Storage::disk('local')->path('pricelists/karina-thread-281.xlsx');
            if (! hash_equals($report['workbook']['sha256'], hash_file('sha256', $source))) {
                throw new RuntimeException('Staged workbook changed since reviewed creation audit.');
            }

            $result = $service->run($report, (bool) $this->option('apply'));
            $receipt = [
                'applied' => (bool) $this->option('apply'),
                'report_blob' => $blob,
                'workbook_sha256' => $report['workbook']['sha256'],
                'generated_at' => now()->toIso8601String(),
            ] + $result;
            file_put_contents(
                storage_path('app/reports/suppliers/karina/create.json'),
                json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n"
            );
            foreach ($result as $key => $value) {
                $this->line('metric.'.$key.'='.(int) $value);
            }

            return self::SUCCESS;
        });
    }
}
