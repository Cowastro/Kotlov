<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncRnProfiChunksCommand extends Command
{
    protected $signature = 'supplier:sync-rn-profi-chunks
        {--apply : Write changes}
        {--dry-run : Preview only}
        {--sheet=* : Exact Google Sheet tab name; repeatable. Defaults to all RN-Profi price tabs}
        {--sync-retail-prices : Update products.price from detected retail price column}
        {--only-linked : Update only existing RN-Profi supplier links}
        {--sleep=2 : Seconds to pause between sheet tabs}';

    protected $description = 'Run RN-Profi price and stock sync by Google Sheet tabs to avoid large XLSX exports.';

    private const SHEETS = [
        '1. Радиаторы секционные БП',
        '2. Радиаторы секционные НП',
        '3. Дизайн-радиаторы SHIFT, INSI',
        '4. Комплектующие',
        '5. Сталь RT',
        '6. Varmega повер.отопление',
        '7. Varmega Slide-fit',
        '8. VARMEGA Inox Press',
        '9. VARMEGA Радиаторная арматура',
        '10. VARMEGA Арматура',
        '11. VARMEGA Насосы',
        '12. Котлы THERMEX',
        '13. Бойлеры ROYAL THERMO',
        'Полотенцесушители',
        'Конвекторы ROYAL THERMO',
        '14. Конвекторы НОВАТЕРМ',
        '15. Радиаторы ИТАЛИЯ NOVA FLORI',
        'Инструмент',
        '16. Вентиляция',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('No --apply passed: running as dry-run.');
        }

        $sheets = $this->selectedSheets();
        if ($sheets === []) {
            $this->error('No RN-Profi sheets selected.');
            return self::FAILURE;
        }

        $sleep = max(0, (int) $this->option('sleep'));
        $exitCode = self::SUCCESS;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: RN-Profi chunk sync will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: RN-Profi chunk sync will preview only.</>');
        $this->info('Sheets: ' . count($sheets));
        $this->newLine();

        foreach ($sheets as $index => $sheet) {
            $this->line(sprintf('=== [%d/%d] %s ===', $index + 1, count($sheets), $sheet));

            $params = [
                '--google-csv-sheet' => $sheet,
                '--sync-retail-prices' => (bool) $this->option('sync-retail-prices'),
                '--only-linked' => (bool) $this->option('only-linked'),
            ];

            if ($apply) {
                $params['--apply'] = true;
            } else {
                $params['--dry-run'] = true;
            }

            $code = Artisan::call('supplier:sync-rn-profi', $params);
            $this->output->write(Artisan::output());

            if ($code !== self::SUCCESS) {
                $exitCode = self::FAILURE;
                $this->warn("Sheet failed: {$sheet}");
            }

            if ($sleep > 0 && $index < count($sheets) - 1) {
                sleep($sleep);
            }
        }

        return $exitCode;
    }

    private function selectedSheets(): array
    {
        $selected = $this->option('sheet') ?: [];
        $selected = array_values(array_filter(array_map('trim', $selected)));

        if ($selected === []) {
            return self::SHEETS;
        }

        return $selected;
    }
}
