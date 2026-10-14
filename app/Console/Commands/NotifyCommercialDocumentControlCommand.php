<?php

namespace App\Console\Commands;

use App\Services\CommercialDocuments\CommercialDocumentControlNotifier;
use Illuminate\Console\Command;

class NotifyCommercialDocumentControlCommand extends Command
{
    protected $signature = 'commercial-documents:notify-control';

    protected $description = 'Notify responsible managers about commercial documents requiring action';

    public function handle(CommercialDocumentControlNotifier $notifier): int
    {
        $count = $notifier->notify();
        $this->info("КП с отправленным напоминанием: {$count}");

        return self::SUCCESS;
    }
}
