<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RestoreLegacyImagesCommand extends Command
{
    protected $signature = 'images:restore-legacy {--target=images : What to restore: images, img-products, all}';

    protected $description = 'Re-sync legacy product photos from the old server into public/images or public/img/products';

    private const OLD_SERVER = 'root@178.172.161.109';
    private const SSH_KEY = '/.ssh/id_rsa_kotlov';

    public function handle(): int
    {
        $home = getenv('HOME') ?: '/root';
        $keyPath = $home . self::SSH_KEY;

        $this->line('Looking for SSH key at: ' . $keyPath);

        if (!file_exists($keyPath)) {
            $this->error('SSH key not found at ' . $keyPath . ' — cannot rsync from the old server. Aborting.');
            return self::FAILURE;
        }

        $targets = $this->targets((string) $this->option('target'));

        if ($targets === []) {
            $this->error('Unknown target. Use one of: images, img-products, all.');
            return self::FAILURE;
        }

        foreach ($targets as $target) {
            if (!$this->restoreTarget($target, $keyPath)) {
                return self::FAILURE;
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, sources: array<int, string>, target: string, verifyDir: string, checks: array<int, string>, discoverFile?: string}>
     */
    private function targets(string $target): array
    {
        $all = [
            'images' => [
                'name' => 'public/images',
                'sources' => [
                    '/home/kotlov.by/www/images/',
                ],
                'target' => public_path('images') . '/',
                'verifyDir' => public_path('images/product'),
                'checks' => [
                    'product/008/008011/eco_4s.jpg',
                    'product/0010/010397/660313bb042d2.png',
                ],
            ],
            'img-products' => [
                'name' => 'public/img/products',
                'sources' => [
                    '/home/kotlov.by/www/img/products/',
                    '/home/kotlov.by/www/public/img/products/',
                    '/home/kotlov.by/www/kotlov-new2026/public/img/products/',
                    '/home/kotlov.by/www/kotlov/public/img/products/',
                ],
                'target' => public_path('img/products') . '/',
                'verifyDir' => public_path('img/products'),
                'checks' => [
                    'teplodvor/18027_0.jpg',
                    'teplodvor/18060_0.png',
                    'teplodvor/18005_0.jpg',
                ],
                'discoverFile' => 'teplodvor/18027_0.jpg',
            ],
        ];

        if ($target === 'all') {
            return array_values($all);
        }

        return isset($all[$target]) ? [$all[$target]] : [];
    }

    /**
     * @param array{name: string, sources: array<int, string>, target: string, verifyDir: string, checks: array<int, string>, discoverFile?: string} $target
     */
    private function restoreTarget(array $target, string $keyPath): bool
    {
        @mkdir($target['target'], 0755, true);

        $this->line('');
        $this->line('=== Restoring ' . $target['name'] . ' ===');
        $this->line('Target: ' . $target['target']);

        $lastExitCode = null;

        $sources = $target['sources'];

        if (isset($target['discoverFile'])) {
            array_push($sources, ...$this->discoverSources($target['discoverFile'], $keyPath));
            $sources = array_values(array_unique($sources));
        }

        foreach ($sources as $source) {
            $this->line('Source: ' . self::OLD_SERVER . ':' . $source);
            $this->line('Starting rsync...');

            $process = new Process([
                'rsync',
                '-avz',
                '--stats',
                '-e', "ssh -i {$keyPath} -o StrictHostKeyChecking=no",
                self::OLD_SERVER . ':' . $source,
                $target['target'],
            ]);
            $process->setTimeout(3600);

            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if ($process->isSuccessful()) {
                $this->verifyTarget($target);
                return true;
            }

            $lastExitCode = $process->getExitCode();
            $this->warn('rsync failed for this source with exit code ' . $lastExitCode . '; trying next source if available.');
        }

        $this->error('All rsync sources failed. Last exit code: ' . ($lastExitCode ?? 'unknown'));
        return false;
    }

    /**
     * @return array<int, string>
     */
    private function discoverSources(string $relativeFile, string $keyPath): array
    {
        $this->line('Searching old server for: */img/products/' . $relativeFile);

        $process = new Process([
            'ssh',
            '-i',
            $keyPath,
            '-o',
            'StrictHostKeyChecking=no',
            self::OLD_SERVER,
            'find /home -path "*/img/products/' . $relativeFile . '" -print 2>/dev/null | head -n 10',
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn('Remote find failed with exit code ' . $process->getExitCode());
            return [];
        }

        $sources = [];
        $suffix = '/' . str_replace('\\', '/', $relativeFile);

        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $found) {
            $found = trim($found);

            if ($found === '' || !str_ends_with($found, $suffix)) {
                continue;
            }

            $source = substr($found, 0, -strlen($suffix)) . '/';
            $this->line('Discovered source: ' . self::OLD_SERVER . ':' . $source);
            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * @param array{name: string, sources: array<int, string>, target: string, verifyDir: string, checks: array<int, string>, discoverFile?: string} $target
     */
    private function verifyTarget(array $target): void
    {
        $count = 0;

        if (is_dir($target['verifyDir'])) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($target['verifyDir'], \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }

        $this->line('');
        $this->line('=== Verification: ' . $target['name'] . ' ===');
        $this->line('Files under ' . $target['verifyDir'] . ': ' . $count);

        foreach ($target['checks'] as $path) {
            $full = $target['target'] . $path;
            $this->line('check ' . $path . ': ' . (file_exists($full) ? 'EXISTS (' . filesize($full) . ' bytes)' : 'still missing'));
        }
    }
}
