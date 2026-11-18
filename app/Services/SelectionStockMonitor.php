<?php

namespace App\Services;

use App\Http\Controllers\B2bSelectionDeskController;
use App\Models\CustomerSelection;
use App\Models\SelectionStockWatch;
use Illuminate\Support\Facades\DB;

class SelectionStockMonitor
{
    /** First enable establishes a baseline and never invents an arrival. */
    public function toggle(CustomerSelection $selection, bool $enabled): SelectionStockWatch
    {
        return DB::transaction(function () use ($selection, $enabled) {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            $watch = SelectionStockWatch::firstOrNew(['customer_selection_id' => $locked->id]);
            $watch->enabled = $enabled;
            if ($enabled) {
                $watch->baseline = app(SelectionAvailability::class)->check($locked, $locked->user);
                $watch->checked_at = now();
            }
            $watch->save();

            return $watch;
        });
    }

    /** A manual check can create the watch; an explicit opt-out is respected. */
    public function observe(CustomerSelection $selection, array $snapshot): void
    {
        if (! B2bSelectionDeskController::canUse($selection->user)) {
            return;
        }
        DB::transaction(function () use ($selection, $snapshot) {
            $watch = SelectionStockWatch::where('customer_selection_id', $selection->id)->lockForUpdate()->first();
            if (! $watch) {
                if (collect($snapshot)->sum('waiting') > 0) {
                    SelectionStockWatch::create([
                        'customer_selection_id' => $selection->id, 'baseline' => $snapshot, 'enabled' => true, 'checked_at' => now(),
                    ]);
                }

                return;
            }
            if (! $watch->enabled) {
                return;
            }
            $changed = [];
            foreach ($snapshot as $id => $current) {
                $previous = $watch->baseline[$id] ?? null;
                if (! $previous || ($previous['fingerprint'] ?? '') !== $current['fingerprint']
                    || $previous['waiting'] < 1 || $current['unknown'] || $current['ready'] <= $previous['ready']) {
                    continue;
                }
                $changed[$id] = [
                    'id' => (int) $id, 'name' => $selection->items[$id]['name'], 'sku' => $selection->items[$id]['sku'] ?? '',
                    'ready' => $current['ready'], 'newly_available' => $current['ready'] - $previous['ready'], 'waiting' => $current['waiting'],
                ];
            }
            $sequence = $watch->sequence + 1;
            if ($changed) {
                $watch->alerts()->create(['sequence' => $sequence, 'items' => $changed]);
            }
            // Alert and baseline commit together: retries cannot create duplicate notifications.
            $watch->update(['baseline' => $snapshot, 'sequence' => $sequence, 'checked_at' => now()]);
        });
    }

    public function scan(SelectionStockWatch $watch): void
    {
        DB::transaction(function () use ($watch) {
            // Use the same lock order as manual checks: selection, then watch.
            $selection = CustomerSelection::whereKey($watch->customer_selection_id)->lockForUpdate()->first();
            if (! $selection || ! B2bSelectionDeskController::canUse($selection->user)) {
                return;
            }
            $snapshot = app(SelectionAvailability::class)->check($selection, $selection->user);
            $this->observe($selection, $snapshot);
        });
    }
}
