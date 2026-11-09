<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SchedulerDatabaseLoadTest extends TestCase
{
    public function test_frequent_database_tasks_are_staggered_across_the_hour(): void
    {
        $schedule = file_get_contents(__DIR__.'/../../routes/console.php');

        $expectedOffsets = [
            "deal:pull-orders')\n    // Offset from the supplier auto-publish task" => "->cron('1-59/2 * * * *')",
            "telephony:sync-a1')" => "->cron('2-59/5 * * * *')",
            "crm:mail-sync')" => "->cron('4-59/5 * * * *')",
            "market:collect-prices')" => "->cron('8,23,38,53 * * * *')",
            "supplier:sync-chisty-bereg --apply --if-new')" => "->cron('6,16,26,36,46,56 * * * *')",
            "supplier:chisty-bereg-create --limit=120 --apply')" => "->cron('10,25,40,55 * * * *')",
            "supplier:chisty-bereg-create --refill-specs --limit=150 --apply')" => "->cron('12,27,42,57 * * * *')",
            "supplier:chisty-bereg-photos --limit=12 --apply')" => "->cron('14 * * * *')",
            "supplier:chisty-bereg-market-prices --limit=10 --recheck-days=90 --apply')" => "->cron('17 */2 * * *')",
            "catalog:finish-offer-cards')" => "->cron('3,18,33,48 * * * *')",
        ];

        foreach ($expectedOffsets as $command => $cron) {
            $commandPosition = strpos($schedule, $command);

            self::assertNotFalse($commandPosition, "Scheduled command not found: {$command}");
            self::assertStringContainsString($cron, substr($schedule, $commandPosition, 420));
        }
    }

    public function test_default_log_stack_uses_daily_rotation(): void
    {
        $logging = file_get_contents(__DIR__.'/../../config/logging.php');

        self::assertStringContainsString("env('LOG_STACK', 'daily')", $logging);
    }
}
