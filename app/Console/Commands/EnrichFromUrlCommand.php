<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

/**
 * Photos, specs and the cleaned description of one card from one page of the same product (teplodvor.by,
 * a maker's site): for cards made by hand or from 1C, which no supplier sync points at.
 *
 *   php artisan catalog:enrich-from-url 40168 https://www.teplodvor.by/shop/…/          # dry run: what was found
 *   php artisan catalog:enrich-from-url 40168 https://www.teplodvor.by/shop/…/ --apply
 */
class EnrichFromUrlCommand extends Command
{
    protected $signature = 'catalog:enrich-from-url
        {product : Card id}
        {url : Page of the same product}
        {--apply : Write photos, specs and description}';

    protected $description = 'Fill one card with photos, specs and description from a page of the same product';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $product = Product::query()->find((int) $this->argument('product'));
        $url = (string) $this->argument('url');
        if (! $product || ! preg_match('#^https://#', $url)) {
            $this->line('audit.enrich_from_url.skipped='.(int) $this->argument('product').':'.($product ? 'url' : 'no_card'));

            return self::FAILURE;
        }

        $parsed = $enricher->preview($url);
        $images = count((array) ($parsed['images'] ?? []));
        $specs = count($enricher->filterUsableSpecs((array) ($parsed['specs'] ?? [])));
        $this->line(sprintf('audit.enrich_from_url.found=%d:images%d:specs%d:text%d', $product->id, $images, $specs,
            mb_strlen(strip_tags((string) ($parsed['content'] ?? $parsed['description'] ?? '')))));
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $result = $enricher->enrichFromParsed($product, $url, $parsed, [
            'update_images' => true, 'replace_images' => true, 'update_specs' => true, 'replace_specs' => true,
            'update_content' => true, 'source_content' => true, 'update_service' => true,
            'update_documents' => true, 'update_video' => false,
        ]);
        $fresh = $product->fresh();
        $this->line(sprintf('audit.enrich_from_url.done=%d:images%d:specs%d:text%d', $product->id, count($fresh->imagePaths()),
            count((array) $fresh->specs), mb_strlen(strip_tags((string) $fresh->content))));

        return self::SUCCESS;
    }
}
