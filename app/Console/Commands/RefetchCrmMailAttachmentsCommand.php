<?php

namespace App\Console\Commands;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Services\CrmMail\ImapMailboxSynchronizer;
use Illuminate\Console\Command;

/**
 * Incoming attachments saved as 0 bytes are fetched again from the mailbox: what the server holds (raw length,
 * encoding, what the strict and the tolerant decode give) and, with --apply, saved again.
 *
 *   php artisan crm-mail:refetch-attachments --from=bereg.by
 *   php artisan crm-mail:refetch-attachments --from=bereg.by --apply
 */
class RefetchCrmMailAttachmentsCommand extends Command
{
    protected $signature = 'crm-mail:refetch-attachments
        {--from= : Part of the sender address}
        {--days=7 : Messages received in these days}
        {--apply : Save the attachments again}';

    protected $description = 'Fetch empty (0 bytes) mail attachments again from the mailbox';

    public function handle(ImapMailboxSynchronizer $synchronizer): int
    {
        $stats = ['messages' => 0, 'attachments' => 0, 'restored' => 0, 'errors' => 0];
        $messages = CrmMailMessage::query()->where('direction', 'incoming')->where('attachments_count', '>', 0)
            ->where('received_at', '>=', now()->subDays((int) $this->option('days')))
            ->when($this->option('from'), fn ($q, $from) => $q->where('from_email', 'like', '%'.$from.'%'))
            ->orderBy('id')->get()
            ->filter(fn (CrmMailMessage $m): bool => collect($m->metadata['attachments'] ?? [])->contains(fn ($a) => is_array($a) && (int) ($a['size'] ?? 0) === 0));
        foreach ($messages as $message) {
            $stats['messages']++;
            $mailbox = CrmMailbox::query()->find($message->mailbox_id);
            try {
                foreach ($synchronizer->refetchAttachments($mailbox, $message, (bool) $this->option('apply')) as $part) {
                    $stats['attachments']++;
                    $this->line(sprintf('audit.mail_refetch.part=%d:%s:%d:%d:%d:%d', $message->id, $part['part'], $part['encoding'], $part['raw'], $part['strict'], $part['size']));
                }
                $stats['restored'] += (int) collect($message->fresh()->metadata['attachments'] ?? [])->every(fn ($a) => (int) ($a['size'] ?? 0) > 0);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("{$message->id}: ".class_basename($e));
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.mail_refetch.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
