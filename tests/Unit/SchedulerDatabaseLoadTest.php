<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SchedulerDatabaseLoadTest extends TestCase
{
    public function test_frequent_database_tasks_are_staggered_across_the_hour(): void
    {
        $schedule = file_get_contents(__DIR__.'/../../routes/console.php');

        // Чистый берег runs once a day, its steps 20 minutes apart (09:00–10:20 Minsk); the rest is spread across the hour.
        $expectedOffsets = [
            "deal:pull-orders')\n    // Offset from the supplier auto-publish task" => "->cron('1-59/2 * * * *')",
            "telephony:sync-a1')" => "->cron('2-59/5 * * * *')",
            "crm:mail-sync')" => "->cron('4-59/5 * * * *')",
            "market:collect-prices')" => "->cron('8,23,38,53 * * * *')",
            "supplier:sync-chisty-bereg --apply --if-new')" => "->dailyAt('09:00')",
            "supplier:chisty-bereg-create --limit=120 --apply')" => "->dailyAt('09:20')",
            "supplier:chisty-bereg-create --refill-specs --limit=150 --apply')" => "->dailyAt('09:40')",
            "supplier:chisty-bereg-photos --limit=12 --apply')" => "->dailyAt('10:00')",
            "supplier:chisty-bereg-market-prices --limit=10 --recheck-days=90 --apply')" => "->dailyAt('10:20')",
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
