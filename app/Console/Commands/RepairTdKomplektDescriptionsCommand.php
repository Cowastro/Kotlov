<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\TdKomplektFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RepairTdKomplektDescriptionsCommand extends Command
{
    protected $signature = 'supplier:repair-td-descriptions {--apply}';

    protected $description = 'Remove the identified TD catalogue appendix from generated descriptions, preserving editorial changes';

    public function handle(TdKomplektFeed $feed): int
    {
        $lock = Cache::lock('supplier:sync-td-komplekt', 1800);
        if (! $lock->get()) {
            $this->error('Обмен ТД Комплект уже запущен.');

            return self::FAILURE;
        }
        try {
            $counts = ['source_rows' => 0, 'products' => 0, 'fields' => 0, 'editorial_fields_preserved' => 0];
            $offers = SupplierProduct::with('product')->whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->whereNotNull('product_id')->get();
            foreach ($offers as $offer) {
                $raw = $offer->raw ?? [];
                if (! empty($raw['identity_review'])) {
                    continue;
                }
                if (! isset($raw['vendor_code']) || (int) $offer->product?->supplier_id !== (int) $offer->supplier_id) {
                    continue;
                }
                $old = $feed->catalogFields($raw, 3);
                $new = $feed->catalogFields($raw, 4);
                $keys = array_filter(['content', 'specs'], fn ($key) => $old[$key] !== $new[$key]);
                if (! $keys) {
                    continue;
                }
                $counts['source_rows']++;
                DB::transaction(function () use ($offer, $old, $new, $keys, &$counts) {
                    $product = Product::lockForUpdate()->findOrFail($offer->product_id);
                    $updates = [];
                    foreach ($keys as $key) {
                        if ($old[$key] === $product->$key) {
                            $updates[$key] = $new[$key];
                        } elseif ($new[$key] !== $product->$key) {
                            $counts['editorial_fields_preserved']++;
                        }
                    }
                    if (! $updates) {
                        return;
                    }
                    $counts['products']++;
                    $counts['fields'] += count($updates);
                    if (! $this->option('apply')) {
                        return;
                    }
                    Storage::disk('local')->put(TdKomplektFeed::DIRECTORY.'/description-backups/'.now()->format('Ymd-His-u').'-'.$product->id.'.json', json_encode(['product_id' => $product->id, 'offer_id' => $offer->id, 'before' => $product->only(array_keys($updates)), 'after' => $updates], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                    $product->update($updates);
                    if (isset($updates['specs'])) {
                        app(ProductSourceEnricher::class)->syncSpecsToAttributeValues($product);
                    }
                });
            }
            foreach ($counts as $key => $value) {
                $this->line('metric.td_descriptions.'.$key.'='.$value);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
