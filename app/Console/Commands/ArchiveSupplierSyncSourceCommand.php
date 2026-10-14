<?php

namespace App\Console\Commands;

use App\Models\SupplierSyncRun;
use App\Services\SupplierSyncSourceArchive;
use Illuminate\Console\Command;

class ArchiveSupplierSyncSourceCommand extends Command
{
    protected $signature = 'supplier:archive-sync-source
        {sync-command : Exact supplier sync command}
        {file : Existing source file path}
        {--name= : Original download filename}
        {--all : Attach the source to every matching run without a file}';

    protected $description = 'Attach an existing supplier price-list file to the unified sync journal.';

    public function handle(SupplierSyncSourceArchive $archive): int
    {
        $command = trim((string) $this->argument('sync-command'));
        if (! str_starts_with($command, 'supplier:sync-')) {
            $this->error('Only supplier:sync-* commands are supported.');

            return self::INVALID;
        }

        $query = SupplierSyncRun::query()
            ->where('command', $command)
            ->whereNull('source_file_path')
            ->latest('started_at');
        $runs = (bool) $this->option('all') ? $query->get() : $query->limit(1)->get();
        if ($runs->isEmpty()) {
            $this->line('metric.sync_source.attached=0');

            return self::SUCCESS;
        }

        $attached = 0;
        foreach ($runs as $run) {
            $attached += $archive->archive(
                $run,
                (string) $this->argument('file'),
                filled($this->option('name')) ? (string) $this->option('name') : null,
            ) ? 1 : 0;
        }

        $this->line('metric.sync_source.attached='.$attached);

        return $attached > 0 ? self::SUCCESS : self::FAILURE;
    }
}
