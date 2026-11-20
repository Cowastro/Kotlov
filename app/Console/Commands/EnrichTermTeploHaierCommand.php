<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Suppliers\HaierTechnicalCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EnrichTermTeploHaierCommand extends Command
{
    protected $signature = 'supplier:termteplo-haier-specs {--limit=30} {--apply}';

    protected $description = 'Дополнить карточки характеристиками Haier по точному артикулу производителя';

    public function handle(HaierTechnicalCatalog $catalog): int
    {
        $offers = SupplierProduct::whereHas('supplier', fn ($q) => $q->where('code', 'termteplosistem'))
            ->whereNotNull('product_id')->orderBy('id')->get();
        if ($offers->isEmpty()) {
            return self::SUCCESS;
        }
        $index = $catalog->index();
        $processed = 0;
        foreach ($offers as $offer) {
            $article = $offer->supplier_article;
            if (! isset($index[$article]) || ($offer->raw['haier_specs_product_id'] ?? null) == $offer->product_id) {
                continue;
            }
            if (++$processed > max(1, (int) $this->option('limit'))) {
                break;
            }
            $url = 'https://haierproff.ru'.$index[$article];
            $specs = $catalog->specifications($article, Http::timeout(30)->retry(2, 500)->get($url)->throw()->body());
            $this->line($article.' — '.count($specs).' характеристик');
            if ($specs && $this->option('apply')) {
                DB::transaction(function () use ($offer, $specs, $url) {
                    $fresh = SupplierProduct::lockForUpdate()->findOrFail($offer->id);
                    if ($fresh->product_id != $offer->product_id) {
                        return;
                    }
                    $card = Product::lockForUpdate()->findOrFail($fresh->product_id);
                    $card->update(['specs' => HaierTechnicalCatalog::append($card->specs ?? [], $specs)]);
                    $fresh->update(['source_url' => $url, 'raw' => array_merge($fresh->raw ?? [], ['haier_specs_product_id' => $card->id])]);
                });
            }
        }

        return self::SUCCESS;
    }
}
