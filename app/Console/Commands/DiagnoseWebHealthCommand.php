<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseWebHealthCommand extends Command
{
    protected $signature = 'system:diagnose-web-health';

    protected $description = 'Report a privacy-safe signature for the latest production web error.';

    public function handle(): int
    {
        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            $this->line('audit.web_health.signature=no_log');
            $this->line('metric.web_health.log_present=0');

            return self::SUCCESS;
        }

        $contents = $this->readTail($logPath, 1024 * 1024);
        $position = strrpos($contents, '.ERROR:');

        if ($position === false) {
            $this->line('audit.web_health.signature=no_error');
            $this->line('metric.web_health.log_present=1');
            $this->line('metric.web_health.error_found=0');

            return self::SUCCESS;
        }

        $error = substr($contents, $position, 32768);
        [$signature, $symbol] = $this->classify($error);

        $this->line('audit.web_health.signature='.$signature);
        if ($symbol !== null) {
            $this->line('audit.web_health.symbol='.$symbol);
        }
        if (preg_match('/[\\\\\/]vendor[\\\\\/]([A-Za-z0-9_.-]+)[\\\\\/]([A-Za-z0-9_.-]+)/', $error, $package) === 1) {
            $this->line('audit.web_health.package='.$package[1].'-'.$package[2]);
        }
        if (preg_match('/ at [^\r\n:]+[\\\\\/]([A-Za-z0-9_.-]+\.php):(\d+)/', $error, $source) === 1) {
            $this->line('audit.web_health.source='.$source[1].'-'.$source[2]);
        }
        $this->line('metric.web_health.log_present=1');
        $this->line('metric.web_health.error_found=1');

        return self::SUCCESS;
    }

    private function readTail(string $path, int $maxBytes): string
    {
        $size = filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($size > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
        }

        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    /** @return array{0: string, 1: string|null} */
    private function classify(string $error): array
    {
        if (preg_match('/[\"\']exception[\"\']\s*:\s*[\"\']\[object\] \(([A-Za-z0-9_\\\\]+)\(/', $error, $matches) === 1) {
            return ['exception_class', $matches[1]];
        }

        $patterns = [
            'missing_class' => '/Class [\"\']([A-Za-z0-9_\\\\]+)[\"\'] not found/',
            'missing_target' => '/Target class \[([A-Za-z0-9_\\\\]+)\] does not exist/',
            'undefined_method' => '/Call to undefined method ([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)/',
            'undefined_function' => '/Call to undefined function ([A-Za-z0-9_\\\\]+)/',
        ];

        foreach ($patterns as $signature => $pattern) {
            if (preg_match($pattern, $error, $matches) === 1) {
                return [$signature, implode('::', array_slice($matches, 1))];
            }
        }

        foreach ([
            'platform_php' => 'Your Composer dependencies require a PHP version',
            'database' => 'SQLSTATE[',
            'permission' => 'Permission denied',
            'type_error' => 'TypeError',
            'view' => 'ViewException',
            'memory' => 'Allowed memory size',
        ] as $signature => $needle) {
            if (str_contains($error, $needle)) {
                return [$signature, null];
            }
        }

        return ['other', null];
    }
}
