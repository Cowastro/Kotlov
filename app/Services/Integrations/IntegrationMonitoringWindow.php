<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class IntegrationMonitoringWindow
{
    public function ordersStartAt(): ?CarbonInterface
    {
        return IntegrationSource::query()
            ->where('is_active', true)
            ->get()
            ->map(function (IntegrationSource $source): ?CarbonInterface {
                $configured = data_get($source->settings, 'monitor_orders_from');

                if (filled($configured)) {
                    try {
                        return Carbon::parse((string) $configured);
                    } catch (\Throwable) {
                        // Fall back to the moment this integration source was created.
                    }
                }

                return $source->created_at;
            })
            ->filter()
            ->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();
    }
}
