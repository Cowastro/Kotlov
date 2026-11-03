<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Read only: the cards and the rows of our 1C base whose name has every word of a query, to find an item under
 * another wording («шибер 115» for «Шибер ШМ(М)-Р 430-0.8 D115»). Each query is words joined by «+».
 *
 *   php artisan catalog:search-names шибер+115 титан+8 вент
 */
class SearchNamesCommand extends Command
{
    protected $signature = 'catalog:search-names {queries* : Words joined by +} {--limit=15 : Rows per query and kind}';

    protected $description = 'Find cards and 1C rows whose names have all the words of a query';

    public function handle(): int
    {
        $cards = Product::query()->get(['id', 'name', 'slug', 'brand_id', 'is_archived', 'price']);
        $oneC = IntegrationProduct::query()->where('integration_source_id', IntegrationSource::query()->where('code', 'onec')->value('id'))
            ->get(['id', 'name', 'external_sku', 'product_id', 'stock_quantity', 'price']);
        foreach ((array) $this->argument('queries') as $n => $query) {
            $words = array_values(array_filter(explode('+', mb_strtolower((string) $query))));
            $has = fn (string $text) => collect($words)->every(fn (string $word) => str_contains(mb_strtolower(str_replace('ё', 'е', $text)), $word));
            foreach ($cards->filter(fn (Product $card) => $has((string) $card->name))->take((int) $this->option('limit')) as $card) {
                $this->line(sprintf('audit.search_names.card=%d:%d~%s~b%d~arch%d~%.2f', $n + 1, $card->id, $card->slug, (int) $card->brand_id, (int) $card->is_archived, (float) $card->price));
            }
            foreach ($oneC->filter(fn (IntegrationProduct $item) => $has($item->name.' '.$item->external_sku))->take((int) $this->option('limit')) as $item) {
                $this->line(sprintf('audit.search_names.onec=%d:%d~%s~p%d~stock%d~%.2f', $n + 1, $item->id, Str::slug(Str::limit((string) $item->name, 90, '')),
                    (int) $item->product_id, (int) ((float) $item->stock_quantity > 0), (float) $item->price));
            }
        }

        return self::SUCCESS;
    }
}
