<?php

namespace App\Console\Commands;

use App\Services\Crm\MailingSender;
use Illuminate\Console\Command;

/**
 * Sends queued Viber/SMS mailing messages and updates delivery statuses. Runs every minute from the scheduler;
 * a campaign only gets here after a manager pressed «Запустить» in CRM → Viber/SMS рассылки. Prints counts only.
 */
class SendMailingsCommand extends Command
{
    protected $signature = 'crm:send-mailings {--limit=200 : Messages to send per run} {--status-limit=200 : Statuses to check per run}';

    protected $description = 'Send queued Viber/SMS mailings through SMS.BY and refresh delivery statuses';

    public function handle(MailingSender $sender): int
    {
        $sent = $sender->sendQueued(max(1, (int) $this->option('limit')));
        $statuses = $sender->refreshStatuses(max(0, (int) $this->option('status-limit')));

        foreach (['sent' => $sent['sent'], 'failed' => $sent['failed'], 'skipped' => $sent['skipped'],
            'status_checked' => $statuses['checked'], 'status_delivered' => $statuses['delivered'], 'status_failed' => $statuses['failed']] as $key => $value) {
            $this->line("metric.send_mailings.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
