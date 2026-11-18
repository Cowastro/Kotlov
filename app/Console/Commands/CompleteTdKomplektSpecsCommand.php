<?php

namespace App\Console\Commands;

use App\Models\SupplierProduct;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektSpecCompletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompleteTdKomplektSpecsCommand extends Command
{
    protected $signature = 'supplier:complete-td-specs {--apply} {--product-id=}';

    protected $description = 'Preview or add missing explicit TD specifications to exactly linked external cards; preserve filled values';

    public function handle(TdKomplektSpecCompletion $completion): int
    {
        $lock = Cache::lock('supplier:sync-td-komplekt', 1800);
        if (! $lock->get()) {
            $this->error('Обмен ТД Комплект уже запущен.');

            return self::FAILURE;
        }
        try {
            $items = [];
            $offers = SupplierProduct::with('product')->whereHas('supplier', fn ($q) => $q->where('code', 'td_komplekt'))->where('match_status', 'matched')->whereNotNull('product_id');
            if ($this->option('product-id')) {
                $offers->where('product_id', $this->option('product-id'));
            }
            foreach ($offers->get() as $offer) {
                $raw = $offer->raw ?? [];
                if (! $offer->product || $offer->product->is_archived || (int) $offer->product->supplier_id === (int) $offer->supplier_id || ! empty($raw['identity_review']) || ! isset($raw['vendor_code'], $raw['brand']) || ($raw['identity_vendor_code'] ?? $raw['vendor_code']) !== $raw['vendor_code'] || ($raw['identity_brand'] ?? $raw['brand']) !== $raw['brand']) {
                    continue;
                }
                $items[] = ['offer_id' => $offer->id, 'article' => $raw['vendor_code']] + $completion->complete($offer->product, $raw, (bool) $this->option('apply'));
            }
            $report = ['apply' => (bool) $this->option('apply'), 'generated_at' => now()->toIso8601String(), 'items' => $items];
            $path = TdKomplektFeed::DIRECTORY.'/spec-reports/'.now()->format('Ymd-His-u').'.json';
            Storage::disk('local')->put($path, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->line('products_checked='.count($items));
            foreach (['added', 'conflicts', 'skipped'] as $key) {
                $this->line($key.'='.array_sum(array_map(fn ($item) => count($item[$key]), $items)));
            }
            $this->line('products_with_additions='.count(array_filter($items, fn ($item) => $item['added'] !== [])));
            $this->line('private_report='.$path);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
