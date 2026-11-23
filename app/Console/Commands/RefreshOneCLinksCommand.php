<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCNomenclature;
use Illuminate\Console\Command;

class RefreshOneCLinksCommand extends Command
{
    protected $signature = 'onec:refresh-links';

    protected $description = 'Обновить отображение существующих привязок 1С без запросов в 1С и изменения карточек';

    public function handle(OneCNomenclature $catalog): int
    {
        $catalog->refresh();
        $this->info('Отображение связей обновлено. Привязки, цены и остатки не изменялись.');

        return self::SUCCESS;
    }
}
