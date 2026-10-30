<?php

namespace App\Console\Commands;

use App\Models\B2bSpecificationAttachment;
use App\Services\B2bSpecificationOcrQueue;
use Illuminate\Console\Command;

class ProcessB2bSpecificationOcrCommand extends Command
{
    protected $signature = 'b2b:process-specification-ocr {--limit=2 : Maximum batches per run}';

    protected $description = 'Process queued B2B PDF/image specification recognition with DeepSeek';

    public function handle(B2bSpecificationOcrQueue $queue): int
    {
        $stale = $queue->failStaleBatches();
        $limit = max(1, min(10, (int) $this->option('limit')));
        $batchIds = B2bSpecificationAttachment::query()
            ->where('ocr_status', 'queued')
            ->whereNotNull('ocr_batch_id')
            ->orderBy('ocr_requested_at')
            ->distinct()
            ->limit($limit)
            ->pluck('ocr_batch_id');
        $counts = ['completed' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($batchIds as $batchId) {
            $result = $queue->processBatch((string) $batchId);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }

        $this->line("batches={$batchIds->count()}");
        $this->line("completed={$counts['completed']}");
        $this->line("failed={$counts['failed']}");
        $this->line("skipped={$counts['skipped']}");
        $this->line("stale_failed={$stale}");

        return self::SUCCESS;
    }
}
