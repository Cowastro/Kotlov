<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Console\Command;

class ListSbgUnmatchedProductsCommand extends Command
{
    protected $signature = 'integration:list-sbg-unmatched
        {--limit=10 : Number of current queue rows to print (1-50)}';

    protected $description = 'Read-only list of the current positive-stock SanBusinessGroup 1C unmatched queue.';

    public function handle(): int
    {
        $limit = min(50, max(1, (int) $this->option('limit')));
        $source = IntegrationSource::query()
            ->where(function ($query): void {
                $query->where('code', 'onec')
                    ->orWhere('name', '1С СанБизнесГруп');
            })
            ->orderByRaw("CASE WHEN code = 'onec' THEN 0 ELSE 1 END")
            ->first();

        if (! $source) {
            $this->error('source_found=0');

            return self::FAILURE;
        }

        $queue = IntegrationProduct::query()
            ->whereBelongsTo($source, 'source')
            ->inStock()
            ->where('match_status', 'unmatched');

        $this->line('source_found=1');
        $this->line('source_id='.$source->id);
        $this->line('source_code='.$this->singleLine($source->code));
        $this->line('metric.sbg_unmatched.count='.(clone $queue)->count());

        $items = (clone $queue)
            ->with(['integrationCategory:id,path', 'targetCategory:id,name,slug'])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($items->values() as $index => $item) {
            $this->line('item.'.($index + 1).'='.json_encode([
                'id' => $item->id,
                'external_id' => $this->singleLine($item->external_id),
                'external_code' => $this->singleLine($item->external_code),
                'external_sku' => $this->singleLine($item->external_sku),
                'barcode' => $this->singleLine($item->barcode),
                'name' => $this->singleLine($item->name),
                'price_byn' => $item->normalizedPriceByn(),
                'stock_quantity' => (float) $item->stock_quantity,
                'last_seen_at' => $item->last_seen_at?->toIso8601String(),
                'source_category' => $this->singleLine($item->integrationCategory?->path),
                'target_category' => $this->singleLine($item->targetCategory?->slug),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    private function singleLine(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
