<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Goods 1C has in stock without a price in the exchange (the price type the site receives): the list for
 * the accountant to set wholesale and retail prices or to ask for the cost — with the site card and its
 * retail price, as safe lines.
 */
class ListPricelessOneCStockCommand extends Command
{
    protected $signature = 'integration:priceless-stock {--source=onec : Integration source code}';

    protected $description = 'List 1C goods in stock that come without a price';

    public function handle(): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.priceless_stock.source=0');

            return self::FAILURE;
        }

        $rows = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where('stock_quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->orderBy('name')->get()
            ->filter(fn (IntegrationProduct $row): bool => (float) $row->normalizedPriceByn() <= 0)
            ->values();
        $cards = Product::query()->whereIn('id', $rows->pluck('product_id')->filter())->get(['id', 'price', 'is_active'])->keyBy('id');

        $stats = ['rows' => $rows->count(), 'with_card' => 0, 'card_without_retail' => 0, 'without_card' => 0];
        foreach ($rows as $row) {
            $card = $row->product_id ? $cards->get($row->product_id) : null;
            $stats[$card ? 'with_card' : 'without_card']++;
            $stats['card_without_retail'] += (int) ($card && (float) $card->price <= 0);
            $this->line(sprintf('audit.priceless_stock.row=%s:%s:%d:%d:%s:%d',
                Str::slug((string) $row->external_code) ?: 'none',
                Str::slug(Str::limit((string) $row->name, 80, '')) ?: 'none',
                (int) round((float) $row->stock_quantity),
                (int) ($card?->id ?? 0),
                number_format((float) ($card?->price ?? 0), 2, '.', ''),
                (int) (bool) $card?->is_active));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.priceless_stock.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
