<?php

namespace App\Console\Commands;

use App\Models\CommercialDocument;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
use Illuminate\Console\Command;

class RefreshCommercialDocumentPortalCommand extends Command
{
    protected $signature = 'commercial-documents:refresh-portal {document : ID коммерческого предложения}';

    protected $description = 'Обновить товарный снимок опубликованного онлайн-КП из актуального каталога';

    public function handle(CommercialDocumentPortal $portal): int
    {
        $document = CommercialDocument::query()->findOrFail((int) $this->argument('document'));
        $portal->refreshSnapshot($document);

        $this->info('Онлайн-КП обновлено.');

        return self::SUCCESS;
    }
}
