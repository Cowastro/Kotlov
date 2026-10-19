<?php

namespace App\Console\Commands;

use App\Models\CrmMailThread;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditCrmMailThreadsCommand extends Command
{
    protected $signature = 'crm-mail:audit-threads
        {--apply : Вернуть скрытые входящие письма в очередь «Требуют реакции»}';

    protected $description = 'Проверить почтовые карточки на скрытые входящие письма и смешанные темы';

    public function handle(): int
    {
        $hiddenIncoming = CrmMailThread::query()
            ->where('status', 'in_progress')
            ->latestMessageDirection('incoming')
            ->with('latestMessage')
            ->orderBy('id')
            ->get();

        $mixedSubjects = CrmMailThread::query()
            ->where('status', '!=', 'spam')
            ->whereHas('messages', fn ($query) => $query->whereNotNull('subject')->where('subject', '!=', ''))
            ->with(['messages' => fn ($query) => $query->select('id', 'thread_id', 'subject')])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (CrmMailThread $thread): array {
                $subjects = $thread->messages
                    ->pluck('subject')
                    ->map(fn (?string $subject): string => $this->normalizeSubject($subject))
                    ->filter()
                    ->unique()
                    ->values();

                return $subjects->count() > 1 ? [$thread->id => $subjects] : [];
            });

        $this->line('hidden_incoming='.$hiddenIncoming->count());
        $this->line('mixed_subject_threads='.$mixedSubjects->count());

        $hiddenIncoming->each(function (CrmMailThread $thread): void {
            $this->line(sprintf(
                'hidden thread=%d subject=%s',
                $thread->id,
                $thread->latestMessage?->subject ?: '-',
            ));
        });

        $mixedSubjects->each(function (Collection $subjects, int|string $threadId): void {
            $this->line(sprintf(
                'mixed thread=%s subjects=%s',
                $threadId,
                $subjects->implode(' | '),
            ));
        });

        if (! $this->option('apply')) {
            $this->info('Проверка завершена без изменений. Для восстановления очереди добавьте --apply.');

            return self::SUCCESS;
        }

        $hiddenIncoming->each(function (CrmMailThread $thread): void {
            $thread->forceFill([
                'status' => 'waiting_manager',
                'handled_at' => null,
            ])->save();
        });

        $this->info('recovered_reactions='.$hiddenIncoming->count());

        return self::SUCCESS;
    }

    private function normalizeSubject(?string $subject): string
    {
        $normalized = trim((string) $subject);

        do {
            $previous = $normalized;
            $normalized = (string) preg_replace(
                '/^\s*(?:re|fw|fwd|ответ|пересылка)\s*:\s*/iu',
                '',
                $normalized,
            );
        } while ($normalized !== $previous);

        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($normalized)));
    }
}
