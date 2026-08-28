<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RestoreRemainingSupplierImagesCommand extends Command
{
    protected $signature = 'images:restore-remaining';

    protected $description = 'Re-run the remaining supplier sync commands whose downloaded images were swept by the stash bug (belkomin-tis already done separately)';

    private const COMMANDS = [
        'supplier:sync-ecokamin-fireboxes' => ['--apply' => true],
        'supplier:sync-ecokamin-stoves' => ['--apply' => true],
        'supplier:sync-elicon-gas-meters' => ['--apply' => true],
        'supplier:sync-gorodkotlov-vaillant' => ['--apply' => true],
    ];

    public function handle(): int
    {
        foreach (self::COMMANDS as $command => $args) {
            $this->line('');
            $this->line('=== Running: ' . $command . ' ' . implode(' ', array_keys($args)) . ' ===');

            try {
                $exitCode = Artisan::call($command, $args, $this->getOutput());
                $this->line('Exit code: ' . $exitCode);
            } catch (\Throwable $e) {
                $this->error('Failed: ' . $e->getMessage());
            }
        }

        $this->line('');
        $this->info('Done restoring remaining supplier images.');

        return self::SUCCESS;
    }
}
