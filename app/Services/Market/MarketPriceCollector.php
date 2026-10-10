<?php

namespace App\Services\Market;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;
use Throwable;

class MarketPriceCollector
{
    public function __construct(
        private readonly MarketPriceCollectionManager $manager,
        private readonly MarketPriceAdapterRegistry $adapters,
    ) {}

    public function collect(MarketPriceSource $source, string $trigger = 'scheduled'): MarketPriceCollectionRun
    {
        $run = $this->manager->start($source, $trigger);
        if ($run->status !== 'running') {
            return $run;
        }

        $adapter = $this->adapters->resolve($source->adapter_key);
        if ($adapter === null) {
            $this->manager->fail($run, 'adapter_not_found', 'Для источника не выбран поддерживаемый адаптер.');

            return $this->manager->finish($run);
        }

        try {
            $adapter->collect($source, $run);
        } catch (Throwable $exception) {
            report($exception);
            $this->manager->fail($run, 'adapter_failed', $exception->getMessage());
        }

        return $this->manager->finish($run);
    }

    /** @return array<int, MarketPriceCollectionRun> */
    public function collectDue(): array
    {
        $runs = [];
        MarketPriceSource::query()
            ->where('is_active', true)
            ->where('collection_authorized', true)
            ->whereIn('collection_method', ['api', 'scrape'])
            ->whereNotNull('adapter_key')
            ->where(fn ($query) => $query->whereNull('next_collection_at')->orWhere('next_collection_at', '<=', now()))
            ->orderBy('id')
            ->each(function (MarketPriceSource $source) use (&$runs): void {
                $runs[] = $this->collect($source);
            });

        return $runs;
    }
}
