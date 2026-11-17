<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\TdKomplektPumpCategories;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReconcileWesterDuplicatesCommand extends Command
{
    protected $signature = 'catalog:reconcile-wester-duplicates {--apply : Archive the verified duplicate cards}';

    protected $description = 'Resolve the 12 verified Wester duplicate pairs blocking TD Komplekt offers';

    // Public catalogue audit, 17 November 2026. Keep the populated, supplier-linked card.
    public const PAIRS = [
        30815 => [30814, 'WRV8'], 30817 => [30816, 'WRV12'],
        30820 => [30818, 'WRV18'], 30821 => [30819, 'WRV24'],
        30823 => [30822, 'WRV35'], 30825 => [30824, 'WRV50'],
        30827 => [30826, 'WRV80'], 30837 => [30836, 'WAV50'],
        30839 => [30838, 'WAV80'], 30841 => [30840, 'WAV100'],
        30843 => [30842, 'WAV150'], 30967 => [30966, 'WAO100'],
    ];

    public function handle(): int
    {
        try {
            DB::transaction(function (): void {
                $pairs = [];
                $backup = [];
                foreach (self::PAIRS as $keepId => [$duplicateId, $model]) {
                    $cards = Product::with(['brand', 'category'])->whereIn('id', [$keepId, $duplicateId])->lockForUpdate()->get()->keyBy('id');
                    $keep = $cards->get($keepId);
                    $duplicate = $cards->get($duplicateId);
                    if (! $keep || ! $duplicate) {
                        throw new RuntimeException("Missing Wester pair {$model}");
                    }
                    foreach ([$keep, $duplicate] as $card) {
                        if (mb_strtoupper($card->brand?->name ?? '') !== 'WESTER'
                            || $card->category?->slug !== 'membrannye-baki'
                            || TdKomplektPumpCategories::tankModelKey($card->name) !== $model) {
                            throw new RuntimeException("Changed identity for Wester {$model}");
                        }
                    }
                    if ($keep->is_archived || $keep->sku !== $duplicate->sku) {
                        throw new RuntimeException("Changed canonical card for Wester {$model}");
                    }
                    if ($duplicate->is_archived) {
                        continue;
                    }
                    // A new ownership/link on the formerly bare card requires another review.
                    if (DB::table('supplier_products')->where('product_id', $duplicateId)->exists()
                        || DB::table('integration_products')->where('product_id', $duplicateId)->exists()) {
                        throw new RuntimeException("New supplier links on duplicate Wester {$model}");
                    }
                    $pairs[] = "{$keepId},{$duplicateId}";
                    $backup[$model] = [$keep->getAttributes(), $duplicate->getAttributes()];
                    $this->line("{$model}: {$duplicateId} -> {$keepId}");
                }
                if ($pairs === []) {
                    $this->info('All 12 Wester pairs already reconciled.');

                    return;
                }
                if ($this->option('apply')) {
                    if (! Storage::disk('local')->put('suppliers/td-komplekt/wester-backup-'.now()->format('Ymd-His-u').'.json', json_encode($backup, JSON_THROW_ON_ERROR))) {
                        throw new RuntimeException('Could not save Wester backup');
                    }
                }
                $status = $this->call('catalog:merge-duplicate-cards', [
                    'pairs' => $pairs, '--keep' => array_keys(self::PAIRS), '--apply' => (bool) $this->option('apply'),
                ]);
                if ($status !== self::SUCCESS) {
                    throw new RuntimeException('Wester merge failed');
                }
                if ($this->option('apply')) {
                    app(SupplierProductAvailabilityService::class)->refreshMany(array_keys(self::PAIRS));
                }
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
