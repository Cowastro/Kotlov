<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Integrations\OneCBridge\SupplierOrderTaskHandler;
use Illuminate\Support\Arr;

/** Local, read-only overview: channels are independent and never represented by one readiness flag. */
class OneCIntegrationOverview
{
    public function snapshot(): array
    {
        $files = IntegrationSource::query()->where('driver', 'commerceml')->orderBy('name')
            ->get(['id', 'name', 'is_active', 'settings'])->map(function (IntegrationSource $source): array {
                $query = $source->exchangeRuns()->where('direction', 'inbound')->where('operation', 'catalog');
                $latest = (clone $query)->latest('started_at')->first(['status', 'started_at', 'finished_at']);
                $success = (clone $query)->where('status', 'success')->latest('started_at')->first(['finished_at']);
                $status = match (true) {
                    ! $source->is_active => 'disabled',
                    $latest?->status === 'failed' => 'failed',
                    $latest?->status === 'running' => 'running',
                    ! $success?->finished_at => 'unknown',
                    $success->finished_at->lt(now()->subMinutes(max($source->staleAfterMinutes(), $source->catalogIntervalMinutes() * 2))) => 'stale',
                    default => 'healthy',
                };

                return ['name' => $source->name, 'status' => $status, 'last_success' => $success?->finished_at];
            })->all();

        $items = OneCItem::query()->where('is_folder', false)->where('deletion_mark', false)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN product_id IS NOT NULL OR site_link_count > 0 THEN 1 ELSE 0 END) as linked, SUM(CASE WHEN link_conflict = 1 THEN 1 ELSE 0 END) as conflicts, MAX(synced_at) as catalog_at, MAX(stock_synced_at) as stock_at')->first();
        $agent = app(OneCAgentTasks::class)->health();

        return [
            'files' => $files,
            'agent' => Arr::only($agent, ['seen_at', 'online', 'outdated', 'queued', 'failed', 'stuck']),
            'items' => ['total' => (int) $items->total, 'linked' => (int) $items->linked, 'conflicts' => (int) $items->conflicts,
                'catalog_at' => $items->catalog_at, 'stock_at' => $items->stock_at],
            'tasks' => OneCAgentTask::query()->whereIn('type', [OrderTaskHandler::TYPE, DocumentStatusTaskHandler::TYPE, SupplierOrderTaskHandler::TYPE])->latest('id')->limit(6)->get(['id', 'title', 'status', 'created_at', 'finished_at'])->toArray(),
        ];
    }
}
