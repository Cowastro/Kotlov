<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditPhpSecurityCommand extends Command
{
    protected $signature = 'system:audit-php-security';

    protected $description = 'Report whether the current PHP runtime includes the October 2026 critical RCE fixes.';

    /** Minimum patched releases published by the PHP security team. */
    private const PATCHED = [
        802 => 80235,
        803 => 80336,
        804 => 80427,
        805 => 80512,
    ];

    public function handle(): int
    {
        $branch = PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;
        $minimum = self::PATCHED[$branch] ?? 0;
        $patched = $minimum > 0 && PHP_VERSION_ID >= $minimum;

        $this->line('audit.php_security.version='.PHP_VERSION);
        $this->line('audit.php_security.sapi='.preg_replace('/[^a-z0-9_-]/i', '_', PHP_SAPI));
        $this->line('metric.php_security.version_id='.PHP_VERSION_ID);
        $this->line('metric.php_security.minimum_patched_id='.$minimum);
        $this->line('metric.php_security.is_supported_branch='.(isset(self::PATCHED[$branch]) ? 1 : 0));
        $this->line('metric.php_security.is_patched='.($patched ? 1 : 0));

        return $patched ? self::SUCCESS : self::FAILURE;
    }
}
