<?php

namespace App\Console\Commands;

use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailThreadMerger;
use Illuminate\Console\Command;

class MergeCrmMailThreadsCommand extends Command
{
    protected $signature = 'crm-mail:merge-threads
        {source : ID устаревшей карточки}
        {target : ID карточки с актуальной перепиской}
        {--apply : Выполнить объединение}';

    protected $description = 'Объединить две карточки одной почтовой сделки без потери писем и стадии';

    public function handle(CrmMailThreadMerger $merger): int
    {
        $source = CrmMailThread::query()->findOrFail((int) $this->argument('source'));
        $target = CrmMailThread::query()->findOrFail((int) $this->argument('target'));

        $this->line('source_id='.$source->id);
        $this->line('target_id='.$target->id);
        $this->line('source_messages='.$source->messages()->count());
        $this->line('target_messages='.$target->messages()->count());

        if (! $this->option('apply')) {
            $this->warn('Проверка завершена без изменений. Добавьте --apply для объединения.');

            return self::SUCCESS;
        }

        $result = $merger->merge($source, $target);
        $this->info('merged=1');
        $this->line('result_messages='.$result->messages_count);
        $this->line('result_stage='.($result->sales_stage ?: 'none'));

        return self::SUCCESS;
    }
}
