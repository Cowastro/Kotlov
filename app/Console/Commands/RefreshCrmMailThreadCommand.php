<?php

namespace App\Console\Commands;

use App\Models\CrmMailThread;
use Illuminate\Console\Command;

class RefreshCrmMailThreadCommand extends Command
{
    protected $signature = 'crm-mail:refresh-thread {thread : ID почтовой карточки}';

    protected $description = 'Пересчитать тему, дату, количество и непрочитанные письма карточки';

    public function handle(): int
    {
        $thread = CrmMailThread::query()->findOrFail((int) $this->argument('thread'));
        $thread->refreshMessageSummary();

        $this->info('refreshed=1');
        $this->line('thread_id='.$thread->id);
        $this->line('messages='.$thread->messages_count);

        return self::SUCCESS;
    }
}
