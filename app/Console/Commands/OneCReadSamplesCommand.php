<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCReferences;
use DomainException;
use Illuminate\Console\Command;

/** Queues a read-only look at how 1C keeps something (OneCReferences::SAMPLE_SETS), e.g. «purchase». */
class OneCReadSamplesCommand extends Command
{
    protected $signature = 'onec:read-samples {set : purchase or order_states}';

    protected $description = '«Мост 1С»: queue a read-only read of 1C samples';

    public function handle(OneCReferences $refs): int
    {
        try {
            $task = $refs->enqueueSamples((string) $this->argument('set'), null);
            $this->line('metric.onec_read_samples.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_read_samples.queued=0');
        }

        return self::SUCCESS;
    }
}
