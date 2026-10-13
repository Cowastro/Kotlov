<?php

namespace App\Console\Commands;

use App\Models\IntegrationExchangeRun;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class PruneIntegrationExchangeFilesCommand extends Command
{
    protected $signature = 'integration:prune-exchange-files
        {--hours= : Keep exchange files modified during this many hours}';

    protected $description = 'Remove expired CommerceML upload sessions without touching active exchanges';

    public function handle(): int
    {
        $retentionHours = (int) ($this->option('hours') ?: config('onec.exchange.file_retention_hours', 48));

        if ($retentionHours < 1) {
            $this->error('The retention period must be at least one hour.');

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));
        $root = trim((string) config('onec.exchange.storage_path'), '/');

        if ($root === '' || ! $disk->exists($root)) {
            $this->info('There are no retained CommerceML files to clean.');

            return self::SUCCESS;
        }

        $activeSessionKeys = IntegrationExchangeRun::query()
            ->where('status', 'running')
            ->whereNotNull('session_key')
            ->pluck('session_key')
            ->filter()
            ->flip();

        $cutoff = now()->subHours($retentionHours)->timestamp;
        $deletedSessions = 0;
        $deletedFiles = 0;
        $deletedBytes = 0;
        $activeSessions = 0;

        foreach ($disk->directories($root) as $sourceDirectory) {
            foreach ($disk->directories($sourceDirectory) as $sessionDirectory) {
                if ($activeSessionKeys->has(hash('sha256', $sessionDirectory))) {
                    $activeSessions++;

                    continue;
                }

                $files = $disk->allFiles($sessionDirectory);

                if ($files !== [] && $this->newestModificationTime($disk, $files) > $cutoff) {
                    continue;
                }

                foreach ($files as $file) {
                    $deletedBytes += $disk->size($file);
                }

                if ($disk->deleteDirectory($sessionDirectory)) {
                    $deletedSessions++;
                    $deletedFiles += count($files);
                }
            }

            if ($disk->allFiles($sourceDirectory) === [] && $disk->directories($sourceDirectory) === []) {
                $disk->deleteDirectory($sourceDirectory);
            }
        }

        $this->info(sprintf(
            'Removed %d expired session(s), %d file(s), %s. Active sessions skipped: %d.',
            $deletedSessions,
            $deletedFiles,
            $this->formatBytes($deletedBytes),
            $activeSessions,
        ));

        return self::SUCCESS;
    }

    /** @param array<int, string> $files */
    private function newestModificationTime(FilesystemAdapter $disk, array $files): int
    {
        return max(array_map(
            static fn (string $file): int => $disk->lastModified($file),
            $files,
        ));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, '.', ' ').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, '.', ' ').' MB';
    }
}
