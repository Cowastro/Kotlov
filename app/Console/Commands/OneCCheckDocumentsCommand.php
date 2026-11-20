<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use Illuminate\Console\Command;

/** Asks the agent on the 1C server whether the documents it created are posted (only when some are not yet). */
class OneCCheckDocumentsCommand extends Command
{
    protected $signature = 'onec:check-documents';

    protected $description = '«Мост 1С»: queue a read-only check of the 1C documents the agent created';

    public function handle(DocumentStatusTaskHandler $handler): int
    {
        $task = $handler->enqueueCheck(null);
        $this->line($task ? 'metric.onec_check_documents.queued='.$task->id : 'metric.onec_check_documents.queued=0');

        return self::SUCCESS;
    }
}
