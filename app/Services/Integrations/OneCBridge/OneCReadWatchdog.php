<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use DomainException;
use Illuminate\Support\Facades\Cache;

/** Restore due read-only work when an online agent finds an empty queue, even if cron stopped. */
class OneCReadWatchdog
{
    public function enqueueDue(): void
    {
        if (! config('onec.read_watchdog_enabled') || ! IntegrationSource::where('code', 'onec')->where('is_active', true)->exists()) {
            return;
        }
        if (! OneCReadWindow::isOpen()) {
            return;
        }
        Cache::lock('onec-read-watchdog', 20)->get(function (): void {
            foreach (['stock', 'prices', 'documents'] as $kind) {
                $query = OneCAgentTask::query()->where('type', $kind === 'documents' ? DocumentStatusTaskHandler::TYPE : ODataReadTaskHandler::TYPE);
                if ($kind !== 'documents') {
                    $query->where('payload->'.$kind, true);
                }
                if ((clone $query)->whereIn('status', OneCAgentTask::ACTIVE)->exists()
                    || (clone $query)->where('created_at', '>', now()->subMinutes(30))->exists()
                    || ! Cache::add('onec-read-watchdog-attempt-'.$kind, true, now()->addMinutes(5))) {
                    continue;
                }
                try {
                    match ($kind) {
                        'stock' => app(OneCStock::class)->enqueue(null),
                        'prices' => app(OneCPrices::class)->enqueue(null),
                        default => app(DocumentStatusTaskHandler::class)->enqueueCheck(null),
                    };
                } catch (DomainException $exception) {
                    report($exception);
                }
            }
        });
    }
}
