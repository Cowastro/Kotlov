<?php

namespace App\Console\Commands;

use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailThreadMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ConsolidateCrmMailThreadsCommand extends Command
{
    protected $signature = 'crm-mail:consolidate-threads {--apply : Выполнить объединение}';

    protected $description = 'Объединить дубли почтовых карточек одного контрагента или email';

    public function handle(CrmMailThreadMerger $merger): int
    {
        $groups = CrmMailThread::query()
            ->where('status', '!=', 'spam')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (CrmMailThread $thread): string => $this->groupKey($thread))
            ->filter(fn (Collection $threads): bool => $threads->count() > 1);

        $this->line('duplicate_groups='.$groups->count());
        $this->line('duplicate_threads='.$groups->sum(fn (Collection $threads): int => $threads->count() - 1));

        if (! $this->option('apply')) {
            $this->info('Предварительная проверка завершена. Для объединения добавьте --apply.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($groups as $threads) {
            /** @var CrmMailThread $target */
            $target = $threads->first();
            foreach ($threads->skip(1) as $source) {
                $target = $merger->merge($source, $target);
                $merged++;
            }
        }

        $this->info('merged_threads='.$merged);

        return self::SUCCESS;
    }

    private function groupKey(CrmMailThread $thread): string
    {
        if ($thread->crm_counterparty_id) {
            $identity = 'counterparty:'.$thread->crm_counterparty_id;
        } else {
            $email = mb_strtolower(trim((string) $thread->participant_email));
            $identity = $email !== '' ? 'email:'.$email : 'thread:'.$thread->id;
        }

        return $thread->mailbox_id.'|'.$identity;
    }
}
