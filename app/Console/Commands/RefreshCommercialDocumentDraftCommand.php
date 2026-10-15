<?php

namespace App\Console\Commands;

use App\Models\CommercialDocument;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Illuminate\Console\Command;
use RuntimeException;

class RefreshCommercialDocumentDraftCommand extends Command
{
    protected $signature = 'commercial-documents:refresh-draft {document : ID коммерческого документа}';

    protected $description = 'Пересчитать коммерческий документ и обновить связанный черновик письма с PDF';

    public function handle(CommercialDocumentWorkflow $workflow): int
    {
        $document = CommercialDocument::query()->findOrFail((int) $this->argument('document'));
        $thread = $document->mailThread;

        if (! $thread || ! $thread->messages()->where('direction', 'draft')->exists()) {
            throw new RuntimeException('У документа нет связанного неотправленного черновика.');
        }

        $user = User::query()->find($document->manager_id)
            ?? User::query()->find($document->created_by);

        if (! $user) {
            throw new RuntimeException('Не найден ответственный пользователь документа.');
        }

        $workflow->createMailDraft($document, $user);

        $this->line('document_id='.$document->id);
        $this->line('draft_refreshed=1');
        $this->line('total='.$document->fresh()->total);

        return self::SUCCESS;
    }
}
