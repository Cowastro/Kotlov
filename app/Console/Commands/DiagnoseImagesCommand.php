<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseImagesCommand extends Command
{
    protected $signature = 'images:diagnose';

    protected $description = 'Diagnose the state of public/images (rsynced legacy product photos)';

    public function handle(): int
    {
        $base = public_path('images');

        $this->line('base_path: ' . base_path());
        $this->line('public_path(images): ' . $base);
        $this->line('is_link: ' . (is_link($base) ? 'yes -> ' . readlink($base) : 'no'));
        $this->line('exists: ' . (file_exists($base) ? 'yes' : 'NO'));
        $this->line('is_dir: ' . (is_dir($base) ? 'yes' : 'NO'));
        $this->line('is_readable: ' . (is_readable($base) ? 'yes' : 'NO'));

        if (is_dir($base)) {
            $entries = @scandir($base) ?: [];
            $entries = array_slice(array_diff($entries, ['.', '..']), 0, 20);
            $this->line('top-level entries (first 20): ' . implode(', ', $entries));

            $productDir = $base . DIRECTORY_SEPARATOR . 'product';
            $this->line('product/ exists: ' . (is_dir($productDir) ? 'yes' : 'NO'));

            if (is_dir($productDir)) {
                $count = 0;
                $sample = [];
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($productDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iter as $file) {
                    if ($file->isFile()) {
                        $count++;
                        if (count($sample) < 5) {
                            $sample[] = str_replace($base . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        }
                        if ($count % 5000 === 0) {
                            $this->line('...counted ' . $count . ' so far');
                        }
                    }
                }
                $this->line('TOTAL FILES under product/: ' . $count);
                $this->line('sample files: ' . implode(' | ', $sample));

                // Check the specific known SKU from the bug report
                $checkPaths = [
                    'product/008/008011/eco_4s.jpg',
                    'product/0010/010397/660313bb042d2.png',
                ];
                foreach ($checkPaths as $p) {
                    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
                    $this->line('check ' . $p . ': ' . (file_exists($full) ? 'EXISTS (' . filesize($full) . ' bytes)' : 'MISSING'));
                }
            }
        }

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($free !== false && $total !== false) {
            $this->line(sprintf('disk free: %.2f GB / %.2f GB total', $free / 1073741824, $total / 1073741824));
        }

        return self::SUCCESS;
    }
}
