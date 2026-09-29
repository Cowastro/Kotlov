<?php

namespace App\Console\Commands;

use App\Filament\Pages\ImportReports;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseImportReports extends Command
{
    protected $signature = 'admin:diagnose-import-reports';

    protected $description = 'Safely verify that the import reports page can read its report files';

    public function handle(): int
    {
        try {
            $page = new ImportReports();
            $page->mount();

            $reports = $page->reports();
            $selected = $page->selectedReport();
            $rows = $page->selectedRows();
            $headers = $page->selectedHeaders();

            $this->components->info('Import reports data is readable.');
            $this->line('Reports: ' . count($reports));
            $this->line('Selected: ' . ($selected === null ? 'no' : 'yes'));
            $this->line('Rows: ' . count($rows));
            $this->line('Columns: ' . count($headers));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error('Import reports failed: ' . $exception::class);
            $this->line('Message: ' . mb_substr($exception->getMessage(), 0, 300));

            return self::FAILURE;
        }
    }
}
