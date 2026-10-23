<?php

namespace App\Console\Commands;

use App\Models\CrmMailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StageCrmMailAttachmentCommand extends Command
{
    protected $signature = 'crm-mail:stage-attachment
        {thread : CRM mail thread ID}
        {--extension=xlsx : Attachment extension to select}
        {--name-contains= : Optional case-insensitive filename fragment}
        {--output=pricelists/crm-mail-attachment.xlsx : Destination on local disk, limited to pricelists/}
        {--apply : Copy the attachment to the staging path}';

    protected $description = 'Stage one CRM mail attachment into storage/app/pricelists for supplier import';

    public function handle(): int
    {
        $threadId = (int) $this->argument('thread');
        $extension = ltrim(mb_strtolower(trim((string) $this->option('extension'))), '.');
        $nameContains = mb_strtolower(trim((string) $this->option('name-contains')));
        $output = ltrim(trim((string) $this->option('output')), '/');

        if ($threadId <= 0) {
            $this->error('Thread ID must be a positive integer.');
            return self::FAILURE;
        }

        if ($extension === '' || ! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            $this->error('Invalid --extension value.');
            return self::FAILURE;
        }

        if (! str_starts_with($output, 'pricelists/')) {
            $this->error('--output is limited to the storage/app/pricelists/ directory.');
            return self::FAILURE;
        }

        $candidate = CrmMailMessage::query()
            ->where('thread_id', $threadId)
            ->where('attachments_count', '>', 0)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (CrmMailMessage $message): array {
                return collect($message->metadata['attachments'] ?? [])
                    ->values()
                    ->filter(fn ($attachment): bool => is_array($attachment))
                    ->map(fn (array $attachment): array => [
                        'message_id' => $message->id,
                        'name' => (string) ($attachment['name'] ?? ''),
                        'path' => (string) ($attachment['path'] ?? ''),
                        'mime_type' => (string) ($attachment['mime_type'] ?? ''),
                    ])
                    ->all();
            })
            ->first(function (array $attachment) use ($extension, $nameContains): bool {
                $name = (string) ($attachment['name'] ?? '');
                $path = (string) ($attachment['path'] ?? '');
                $actualExtension = mb_strtolower(pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION));

                if ($actualExtension !== $extension) {
                    return false;
                }

                if ($nameContains !== '' && ! Str::contains(mb_strtolower($name), $nameContains)) {
                    return false;
                }

                return str_starts_with($path, 'crm-mail-attachments/');
            });

        if (! $candidate) {
            $this->error(sprintf(
                'No matching .%s attachment found in CRM thread #%d.',
                $extension,
                $threadId,
            ));
            return self::FAILURE;
        }

        if (! Storage::disk('local')->exists($candidate['path'])) {
            $this->error('Attachment metadata exists, but the source file is missing on local storage.');
            return self::FAILURE;
        }

        $this->table(['thread', 'message', 'attachment', 'destination'], [[
            $threadId,
            $candidate['message_id'],
            $candidate['name'] ?: basename($candidate['path']),
            $output,
        ]]);

        if (! $this->option('apply')) {
            $this->warn('[DRY-RUN] Attachment found; nothing copied.');
            return self::SUCCESS;
        }

        Storage::disk('local')->makeDirectory(dirname($output));
        Storage::disk('local')->delete($output);

        if (! Storage::disk('local')->copy($candidate['path'], $output)) {
            $this->error('Failed to copy attachment into pricelist staging.');
            return self::FAILURE;
        }

        $absolutePath = Storage::disk('local')->path($output);
        $this->info('Attachment staged successfully.');
        $this->line('path='.$absolutePath);
        $this->line('bytes='.Storage::disk('local')->size($output));

        return self::SUCCESS;
    }
}
