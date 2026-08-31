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
     * @return array<int, array{name: string, source: string, target: string, verifyDir: string, checks: array<int, string>}>
     */
    private function targets(string $target): array
    {
        $all = [
            'images' => [
                'name' => 'public/images',
                'source' => '/home/kotlov.by/www/images/',
                'target' => public_path('images') . '/',
                'verifyDir' => public_path('images/product'),
                'checks' => [
                    'product/008/008011/eco_4s.jpg',
                    'product/0010/010397/660313bb042d2.png',
                ],
            ],
            'img-products' => [
                'name' => 'public/img/products',
                'source' => '/home/kotlov.by/www/img/products/',
                'target' => public_path('img/products') . '/',
                'verifyDir' => public_path('img/products'),
                'checks' => [
                    'teplodvor/18027_0.jpg',
                    'teplodvor/18060_0.png',
                    'teplodvor/18005_0.jpg',
                ],
            ],
        ];

        if ($target === 'all') {
            return array_values($all);
        }

        return isset($all[$target]) ? [$all[$target]] : [];
    }

    /**
     * @param array{name: string, source: string, target: string, verifyDir: string, checks: array<int, string>} $target
     */
    private function restoreTarget(array $target, string $keyPath): bool
    {
        @mkdir($target['target'], 0755, true);

        $this->line('');
        $this->line('=== Restoring ' . $target['name'] . ' ===');
        $this->line('Target: ' . $target['target']);
        $this->line('Source: ' . self::OLD_SERVER . ':' . $target['source']);
        $this->line('Starting rsync...');

        $process = new Process([
            'rsync',
            '-avz',
            '--stats',
            '-e', "ssh -i {$keyPath} -o StrictHostKeyChecking=no",
            self::OLD_SERVER . ':' . $target['source'],
            $target['target'],
        ]);
        $process->setTimeout(3600);

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            $this->error('rsync failed with exit code ' . $process->getExitCode());
            return false;
        }

        $this->verifyTarget($target);

        return true;
    }

    /**
     * @param array{name: string, source: string, target: string, verifyDir: string, checks: array<int, string>} $target
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
