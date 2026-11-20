<?php

namespace App\Console\Commands;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Why 1C does not record a successful export: the exchange journal of the last days and the files of the
 * current exchange sessions, as safe one-line facts (no names, prices or error texts).
 */
class OneCExchangeReportCommand extends Command
{
    protected $signature = 'onec:exchange-report {--days=4 : How many days of the journal to show}
        {--log= : Application log to scan (default storage/logs/laravel.log)}';

    protected $description = 'Show the recent 1C exchange runs and session files as safe audit lines';

    /** Known error texts → a short code for the log. */
    private const ERRORS = [
        'Сеанс прерван' => 'superseded_by_new_exchange',
        'превысил допустимый' => 'file_too_large',
        'SQLSTATE' => 'database',
        'Allowed memory' => 'memory',
        'Maximum execution' => 'time_limit',
        'Lock' => 'lock',
        'lock' => 'lock',
        'XML' => 'xml',
        'xml' => 'xml',
        'Integrity' => 'database',
        'Undefined' => 'code',
        'TypeError' => 'code',
    ];

    public function handle(): int
    {
        $sources = IntegrationSource::query()->get();
        foreach ($sources as $source) {
            $this->line('audit.onec_exchange.source='.$source->id.':'.$this->slug($source->code).':auth='.($source->last_authenticated_at?->format('YmdHi') ?? 'never'));
        }

        IntegrationExchangeRun::query()
            ->where('started_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->orderBy('id')
            ->get()
            ->each(function (IntegrationExchangeRun $run) use ($sources): void {
                $this->line('audit.onec_exchange.run='.implode(':', [
                    $run->id,
                    $this->slug((string) $sources->firstWhere('id', $run->integration_source_id)?->code),
                    $this->slug((string) $run->direction),
                    $this->slug((string) $run->operation),
                    $this->slug((string) $run->status),
                    $run->started_at?->timezone('Europe/Minsk')->format('YmdHi') ?? '0',
                    $run->finished_at?->timezone('Europe/Minsk')->format('YmdHi') ?? '0',
                    'files='.(int) $run->files_count,
                    'bytes='.(int) $run->bytes_received,
                    'items='.(int) $run->items_received,
                    'orders='.(int) $run->orders_count,
                    'error='.$this->errorCode($run->error_message),
                ]));
            });

        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));
        $base = trim((string) config('onec.exchange.storage_path'), '/');
        foreach ($disk->allFiles($base) as $path) {
            $relative = substr($path, strlen($base) + 1);
            $parts = explode('/', $relative);
            if (count($parts) < 3 || str_ends_with($path, '.received') || str_ends_with($path, '.result.json')) {
                continue;
            }
            $this->line('audit.onec_exchange.file='.implode(':', [
                $this->slug($parts[0]),
                substr($this->slug($parts[1]), 0, 8),
                $this->slug(basename($path)),
                $disk->size($path),
                date('YmdHi', $disk->lastModified($path) + 3 * 3600),
                'received='.($disk->exists($path.'.received') ? 1 : 0),
            ]));
        }

        $this->logErrors(max(1, (int) $this->option('days')));

        return self::SUCCESS;
    }

    /**
     * Errors of the application log in the window: time, exception class and the first frame in app/ —
     * no message text. A request killed by the PHP time or memory limit shows up here and nowhere else.
     */
    private function logErrors(int $days): void
    {
        $path = (string) ($this->option('log') ?: storage_path('logs/laravel.log'));
        if (! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        $size = filesize($path);
        fseek($handle, max(0, $size - 60 * 1024 * 1024));
        $since = now()->subDays($days)->format('Y-m-d H:i');
        $current = null;
        $entries = [];

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = $m[1] >= $since ? ['at' => $m[1], 'head' => $line, 'frame' => null] : null;

                continue;
            }
            if ($current && $current['frame'] === null && preg_match('#[\\\\/]app[\\\\/]([A-Za-z0-9_\\\\/]+\.php)[(:](\d+)#', $line, $f)) {
                $current['frame'] = str_replace('\\', '/', $f[1]).':'.$f[2];
            }
        }
        if ($current) {
            $entries[] = $current;
        }
        fclose($handle);

        foreach ($entries as $entry) {
            $head = $entry['head'];
            $kind = match (true) {
                str_contains($head, 'Maximum execution time') => 'time_limit',
                str_contains($head, 'Allowed memory size') => 'memory_limit',
                (bool) preg_match('/\(([A-Za-z\\\\]+(Exception|Error))\(code/', $head, $c) => strtolower(str_replace('\\', '.', $c[1])),
                default => 'other',
            };
            if (! $entry['frame'] && preg_match('#[\\\\/]app[\\\\/]([A-Za-z0-9_\\\\/]+\.php)[(:](\d+)#', $head, $f)) {
                $entry['frame'] = str_replace('\\', '/', $f[1]).':'.$f[2];
            }
            $related = in_array($kind, ['time_limit', 'memory_limit'], true)
                || preg_match('/OneC|CommerceMl|1c\/exchange|Integrations/i', $head.' '.$entry['frame']);
            if (! $related) {
                continue;
            }
            $this->line('audit.onec_exchange.log='.str_replace([' ', '-', ':'], '', $entry['at']).':'.$this->slug($kind).':'.$this->slug(strtolower((string) ($entry['frame'] ?: 'none'))));
        }
    }

    private function errorCode(?string $message): string
    {
        if (blank($message)) {
            return 'none';
        }
        foreach (self::ERRORS as $needle => $code) {
            if (str_contains($message, $needle)) {
                return $code;
            }
        }

        return 'other_'.substr(md5($message), 0, 6);
    }

    private function slug(string $value): string
    {
        return preg_replace('/[^a-z0-9_.-]/', '_', strtolower($value)) ?: 'none';
    }
}
