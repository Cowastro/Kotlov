<?php

namespace App\Console\Commands;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Console\Command;

class ExpireCommercialQuotesCommand extends Command
{
    protected $signature = 'commercial-documents:expire-quotes';

    protected $description = 'Close sent commercial quotes whose validity period has expired';

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

        $this->info("Просроченных КП закрыто: {$expired}");

        return self::SUCCESS;
    }
}
