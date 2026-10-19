<?php

namespace App\Console\Commands;

use App\Models\CrmMailThread;
use Illuminate\Console\Command;

class RefreshCrmMailThreadCommand extends Command
{
    protected $signature = 'crm-mail:refresh-thread
        {thread : ID почтовой карточки}
        {--recover-reaction : Вернуть последнее входящее письмо в очередь «Требуют реакции»}';

    protected $description = 'Пересчитать тему, дату, количество и непрочитанные письма карточки';

    public function handle(): int
    {
        $thread = CrmMailThread::withTrashed()->findOrFail((int) $this->argument('thread'));
        $thread->refreshMessageSummary();

        $recovered = false;
        if ($this->option('recover-reaction') && $thread->latestMessage?->direction === 'incoming') {
            if ($thread->trashed()) {
                $thread->restore();
            }

            $thread->forceFill([
                'status' => $thread->status === 'new' ? 'new' : 'waiting_manager',
                'handled_at' => null,
            ])->save();
            $recovered = true;
        }

        $this->info('refreshed=1');
        $this->line('thread_id='.$thread->id);
        $this->line('messages='.$thread->messages_count);
        $this->line('reaction_recovered='.(int) $recovered);
        $this->line('thread_visible='.(int) ! $thread->trashed());

        return self::SUCCESS;
    }
}
