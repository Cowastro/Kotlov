<?php

namespace App\Console\Commands;

use App\Models\CrmMailbox;
use App\Services\CrmMail\ImapMailboxSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncCrmMail extends Command
{
    protected $signature = 'crm:mail-sync {--mailbox= : ID или адрес конкретного ящика}';

    protected $description = 'Загрузить новые письма из подключённых CRM-ящиков';

    public function handle(ImapMailboxSynchronizer $synchronizer): int
    {
        $query = CrmMailbox::query()->where('is_enabled', true);
        if ($mailbox = $this->option('mailbox')) {
            $query->where(fn ($q) => $q->whereKey($mailbox)->orWhere('email', $mailbox));
        }

        $failed = false;
        foreach ($query->get() as $connection) {
            try {
                $result = $synchronizer->sync($connection);
                $this->info("{$connection->email}: загружено {$result['imported']}, пропущено {$result['skipped']}");
            } catch (Throwable $exception) {
                $message = mb_scrub($exception->getMessage(), 'UTF-8');
                $connection->forceFill(['last_sync_at' => now(), 'last_error' => $message])->save();
                $this->error("{$connection->email}: {$message}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
