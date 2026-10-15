<?php

namespace App\Console\Commands;

use App\Models\CrmMailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCrmMailAttachmentBodyCommand extends Command
{
    protected $signature = 'crm-mail:repair-attachment-body {message : ID письма} {--apply : Сохранить исправление}';

    protected $description = 'Убрать ошибочно сохранённый MIME-конверт письма с вложением без текста';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $message = CrmMailMessage::query()->lockForUpdate()->findOrFail((int) $this->argument('message'));
            $body = trim((string) $message->text_body);
            $attachments = collect($message->metadata['attachments'] ?? []);
            $eligible = $message->direction === 'incoming'
                && blank($message->html_body)
                && preg_match('/\A--[^\r\n]+\r?\n/', $body) === 1
                && preg_match('/^Content-Disposition:\s*attachment\b/im', $body) === 1
                && preg_match('/^Content-Transfer-Encoding:\s*base64\b/im', $body) === 1
                && preg_match('/^Content-Type:\s*text\/(?:plain|html)\b/im', $body) !== 1
                && $attachments->contains(fn ($item): bool => is_array($item) && ! empty($item['stored']) && filled($item['path'] ?? null));

            $this->line('eligible='.(int) $eligible);
            if (! $eligible) {
                $this->line('repaired=0');

                return self::SUCCESS;
            }

            if ($this->option('apply')) {
                $metadata = $message->metadata ?? [];
                $metadata['attachment_body_repair'] = [
                    'original_text_body' => $message->text_body,
                    'repaired_at' => now()->toIso8601String(),
                ];
                $message->forceFill(['text_body' => null, 'metadata' => $metadata])->save();
            }
            $this->line('repaired='.(int) $this->option('apply'));

            return self::SUCCESS;
        });
    }
}
