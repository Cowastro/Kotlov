<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RestoreLegacyImagesCommand extends Command
{
    protected $signature = 'images:restore-legacy';

    protected $description = 'Re-sync legacy product photos from the old server into public/images (recovery after they were swept away by git stash)';

    private const OLD_SERVER = 'root@178.172.161.109';
    private const OLD_PATH = '/home/kotlov.by/www/images/';
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

        $target = public_path('images') . '/';
        @mkdir($target, 0755, true);

        $this->line('Target: ' . $target);
        $this->line('Source: ' . self::OLD_SERVER . ':' . self::OLD_PATH);
        $this->line('Starting rsync (this can take a while for ~18k files)...');

        $process = new Process([
            'rsync',
            '-avz',
            '--stats',
            '-e', "ssh -i {$keyPath} -o StrictHostKeyChecking=no",
            self::OLD_SERVER . ':' . self::OLD_PATH,
            $target,
        ]);
        $process->setTimeout(3600);

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            $this->error('rsync failed with exit code ' . $process->getExitCode());
            return self::FAILURE;
        }

        // Verify
        $count = 0;
        $productDir = $target . 'product';
        if (is_dir($productDir)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($productDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }

        $this->line('');
        $this->line('=== Verification ===');
        $this->line('Files under public/images/product/: ' . $count);

        $checkPaths = [
            'product/008/008011/eco_4s.jpg',
            'product/0010/010397/660313bb042d2.png',
        ];
        foreach ($checkPaths as $p) {
            $full = $target . $p;
            $this->line('check ' . $p . ': ' . (file_exists($full) ? 'EXISTS (' . filesize($full) . ' bytes)' : 'still missing'));
        }

        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }
}
