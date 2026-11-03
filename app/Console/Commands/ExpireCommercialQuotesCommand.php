<?php

namespace App\Console\Commands;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Console\Command;

class ExpireCommercialQuotesCommand extends Command
{
    protected $signature = 'commercial-documents:expire-quotes';

    protected $description = 'Put expired commercial quotes under manager control without closing them as rejected';

    public function handle(CommercialDocumentWorkflow $workflow): int
    {
        $expired = 0;

        CommercialDocument::query()
            ->where('type', 'quote')
            ->where('status', 'sent')
            ->whereDate('valid_until', '<', today())
            ->orderBy('id')
            ->eachById(function (CommercialDocument $document) use ($workflow, &$expired): void {
                $workflow->expire($document);
                $expired++;
            });

        $this->info("Просроченных КП поставлено на контроль: {$expired}");

        return self::SUCCESS;
    }
}
