<?php

namespace App\Console\Commands;

use App\Services\CrmMail\MailEncodingNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCrmMailEncoding extends Command
{
    protected $signature = 'crm:mail-repair-encoding {--dry-run : Только показать количество исправлений}';

    protected $description = 'Декодировать ранее сохранённые MIME-заголовки тем CRM-писем';

    public function handle(MailEncodingNormalizer $normalizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['crm_mail_threads' => 0, 'crm_mail_messages' => 0];

        foreach (array_keys($counts) as $table) {
            DB::table($table)
                ->select(['id', 'subject'])
                ->whereNotNull('subject')
                ->where('subject', 'like', '%=?%?%?=%')
                ->orderBy('id')
                ->chunkById(250, function ($rows) use ($table, $dryRun, $normalizer, &$counts): void {
                    foreach ($rows as $row) {
                        $decoded = $normalizer->decodeHeader($row->subject);

                        if ($decoded === null || $decoded === $row->subject) {
                            continue;
                        }

                        $counts[$table]++;
                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update([
                                'subject' => $decoded,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                });
        }

        $prefix = $dryRun ? 'Будет исправлено' : 'Исправлено';
        $this->info("{$prefix}: цепочек {$counts['crm_mail_threads']}, писем {$counts['crm_mail_messages']}.");

        return self::SUCCESS;
    }
}
