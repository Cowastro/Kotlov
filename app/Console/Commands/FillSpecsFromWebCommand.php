<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\WebProductSources;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Specs for cards that have no supplier page: the same internet search as
 * «Найти в интернете» in the КП form picks a product page of the model
 * (its numbers or Latin code must be in the address) and the source
 * enricher reads the specs from it. The dry run lists the chosen pages.
 *
 *   php artisan catalog:fill-specs-from-web --products=4981,4983            # dry run
 *   php artisan catalog:fill-specs-from-web --products=4981,4983 --apply
 */
class FillSpecsFromWebCommand extends Command
{
    protected $signature = 'catalog:fill-specs-from-web {--products= : Comma-separated product ids} {--apply : Write the specs}';

    protected $description = 'Find a product page on the internet and fill the specs of cards without a source';

    public function handle(WebProductSources $sources, ProductSourceEnricher $enricher): int
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->option('products'))));
        $stats = ['cards' => 0, 'page_found' => 0, 'no_page' => 0, 'specs_saved' => 0, 'images_saved' => 0, 'errors' => 0];

        foreach (Product::query()->with('brand:id,name')->whereIn('id', $ids)->get() as $product) {
            $stats['cards']++;
            $query = $product->brand && mb_stripos($product->name, $product->brand->name) === false
                ? $product->brand->name.' '.$product->name
                : $product->name;
            $url = $this->choose(array_keys($sources->search($query)), $product->name);
            if (! $url) {
                $stats['no_page']++;
                $this->line("audit.web_specs.card={$product->id}:{$product->slug}:none:none");

                continue;
            }
            $stats['page_found']++;
            $this->line(sprintf('audit.web_specs.card=%d:%s:%s:%s', $product->id, $product->slug,
                Str::slug((string) parse_url($url, PHP_URL_HOST)), Str::slug(Str::limit((string) parse_url($url, PHP_URL_PATH), 70, '')) ?: 'page'));
            if (! $this->option('apply')) {
                continue;
            }
            try {
                $result = $enricher->enrich($product, $url, [
                    'update_content' => false,
                    'update_images' => count(array_filter((array) $product->images)) === 0,
                ]);
                $stats['specs_saved'] += (int) ($result['attribute_values_saved'] ?? 0);
                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                if (! $product->content_source_url) {
                    $product->forceFill(['content_source_url' => $url])->save();
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("#{$product->id}: ".$e->getMessage());
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.web_specs.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** The first page whose address names the model: its numbers or Latin code («PM5», «3-4», «15»). */
    private function choose(array $urls, string $name): ?string
    {
        preg_match_all('/[A-Za-z]*\d+[A-Za-z0-9]*|[A-Z]{2,}/u', $name, $m);
        $codes = array_map('strtolower', array_filter($m[0], fn (string $t): bool => ! in_array(strtolower($t), ['unipump'], true)));
        foreach ($urls as $url) {
            $path = strtolower(urldecode((string) parse_url($url, PHP_URL_PATH)));
            if ($codes === [] || array_filter($codes, fn (string $code): bool => str_contains($path, $code)) !== []) {
                return $url;
            }
        }

        return null;
    }
}
