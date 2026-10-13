<?php

namespace App\Console\Commands;

use App\Models\SupplierOrderRequest;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Console\Command;

class SyncSupplierMailDraftsCommand extends Command
{
    protected $signature = 'crm-mail:sync-supplier-drafts {--request= : ID конкретной заявки поставщику}';

    protected $description = 'Создать недостающие почтовые черновики для заявок поставщикам';

    public function handle(SupplierRequestMailDraftService $drafts): int
    {
        $query = SupplierOrderRequest::query()
            ->where('status', 'draft')
            ->with(['creator', 'order', 'items', 'supplier.crmCounterparty.contactPoints']);

        if ($this->option('request')) {
            $query->whereKey((int) $this->option('request'));
        }

        $created = 0;
        $query->each(function (SupplierOrderRequest $request) use ($drafts, &$created): void {
            $drafts->sync($request, $request->creator);
            $created++;
        });

        $this->info('Синхронизировано почтовых черновиков: '.$created);

        return self::SUCCESS;
    }
}
