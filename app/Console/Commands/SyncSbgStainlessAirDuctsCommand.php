<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Integrations\IntegrationManualMatchRecorder;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncSbgStainlessAirDuctsCommand extends Command
{
    protected $signature = 'catalog:sync-sbg-stainless-air-ducts
        {--apply : Create/update cards and link exact 1C rows; default is dry-run}';

    protected $description = 'Sync the complete SBG stainless corrugated air-duct range as running-metre products';

    private const CATEGORY_SLUG = 'gibkie-vozduhovody';

    /** @var array<int,array{article:string,price:float,url:string}> */
    private const RANGE = [
        80 => ['article' => 'ВНС80', 'price' => 50.00, 'url' => 'https://sbg.by/p11022770-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        100 => ['article' => 'ВНС100', 'price' => 50.00, 'url' => 'https://sbg.by/p237177281-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        110 => ['article' => 'ВНС110', 'price' => 55.00, 'url' => 'https://sbg.by/p237177282-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        120 => ['article' => 'ВНС120', 'price' => 59.00, 'url' => 'https://sbg.by/p237177283-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        125 => ['article' => 'ВНС125', 'price' => 61.00, 'url' => 'https://sbg.by/p237177284-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        130 => ['article' => 'ВНС130', 'price' => 63.00, 'url' => 'https://sbg.by/p237177285-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        135 => ['article' => 'ВНС135', 'price' => 67.00, 'url' => 'https://sbg.by/p237177286-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        140 => ['article' => 'ВНС140', 'price' => 70.00, 'url' => 'https://sbg.by/p237177287-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        150 => ['article' => 'ВНС150', 'price' => 73.00, 'url' => 'https://sbg.by/p237177288-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
        200 => ['article' => 'ВНС200', 'price' => 93.00, 'url' => 'https://sbg.by/p237177289-vozduhovod-gofrirovannyj-nerzhaveyuschej.html'],
    ];

    public function handle(
        ProductSourceEnricher $enricher,
        IntegrationManualMatchRecorder $recorder,
        SupplierProductAvailabilityService $availability,
    ): int {
        $apply = (bool) $this->option('apply');
        $category = Category::query()->where('slug', self::CATEGORY_SLUG)->first();
        $source = IntegrationSource::query()->where('code', 'onec')->first();

        if (! $category || ! $source) {
            $this->error(! $category ? 'Category gibkie-vozduhovody is missing.' : 'Integration source onec is missing.');

            return self::FAILURE;
        }

        $items = IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->where(function ($query): void {
                $query->where('name', 'like', '%воздуховод%')
                    ->orWhere('name', 'like', '%Воздуховод%');
            })
            ->get();
        $products = Product::query()
            ->where(function ($query): void {
                $query->where('name', 'like', '%нержав%')
                    ->orWhere('name', 'like', '%нерж.%');
            })
            ->where('name', 'like', '%воздуховод%')
            ->get();

        $stats = ['sizes' => count(self::RANGE), 'ready' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'supplier_routes' => 0, 'deferred_links' => 0, 'skipped' => 0];

        foreach (self::RANGE as $diameter => $definition) {
            try {
                $offer = $enricher->preview($definition['url']);
            } catch (\Throwable) {
                $offer = null;
            }
            $matchingItems = $items->filter(fn (IntegrationProduct $item): bool => $this->isStainlessDiameter($item->name, $diameter))->values();
            $matchingProducts = $products->filter(fn (Product $product): bool => $this->isStainlessDiameter($product->name, $diameter))->values();
            $linkedProduct = $matchingItems->pluck('product_id')->filter()->unique();

            if (! $offer || $matchingProducts->count() > 1 || $linkedProduct->count() > 1) {
                $stats['skipped']++;
                $reason = ! $offer ? 'missing_sbg_offer' : 'ambiguous_local_identity';
                $this->line("audit.sbg_stainless_duct.skipped={$diameter}:{$reason}");
                $this->line(sprintf(
                    'audit.sbg_stainless_duct.identity=%d:%d:%d:%d',
                    $diameter,
                    $matchingItems->count(),
                    $matchingProducts->count(),
                    $linkedProduct->count(),
                ));

                continue;
            }

            $product = $linkedProduct->count() === 1
                ? Product::query()->find((int) $linkedProduct->first())
                : $matchingProducts->first();
            $item = $product
                ? $matchingItems->firstWhere('product_id', $product->id)
                : $this->pickExactIntegrationRow($matchingItems);
            if (! $item && $matchingItems->isNotEmpty()) {
                $stats['deferred_links']++;
            }
            $stats['ready']++;
            $this->line(sprintf(
                'audit.sbg_stainless_duct.ready=%d:%s:%.2f:%s:%s',
                $diameter,
                Str::lower($definition['article']),
                $definition['price'],
                $item?->external_code ?: 'no-1c-row',
                $product?->sku ?: 'new-card',
            ));

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($diameter, $definition, $offer, $category, $source, $item, &$product, $recorder, &$stats): void {
                $name = "Воздуховод гофрированный из нержавеющей стали Ø{$diameter} мм";
                $data = [
                    'category_id' => $category->id,
                    'supplier_id' => $source->supplier_id,
                    'name' => $name,
                    'h1' => $name,
                    'price' => $definition['price'],
                    'currency' => 'BYN',
                    'unit' => 'пог. м',
                    'short_description' => "Гибкий гофрированный воздуховод из нержавеющей стали диаметром {$diameter} мм. Цена указана за 1 погонный метр.",
                    'content' => ($offer['description'] ?? '') ?: "<p><strong>{$name}</strong> применяется в системах вентиляции. Цена указана за 1 погонный метр.</p>",
                    'content_source_url' => $definition['url'],
                    'specs' => [
                        ['key' => 'Тип', 'value' => 'Гибкий гофрированный воздуховод'],
                        ['key' => 'Материал', 'value' => 'Нержавеющая сталь'],
                        ['key' => 'Диаметр', 'value' => "{$diameter} мм"],
                        ['key' => 'Единица продажи', 'value' => '1 пог. м'],
                    ],
                    'meta_title' => Str::limit("{$name} — купить в Беларуси", 60, ''),
                    'meta_description' => Str::limit("{$name}. Розничная цена за погонный метр, наличие и доставка по Беларуси.", 175, ''),
                    'is_active' => true,
                    'is_archived' => false,
                ];

                if (! $product) {
                    $product = Product::query()->create($data + [
                        'slug' => 'new-product-'.Str::uuid(),
                        'sku' => null,
                        'in_stock' => false,
                        'availability_status' => Product::AVAILABILITY_CHECK,
                    ]);
                    $product->update([
                        'sku' => $this->uniqueSku($product->id),
                        'slug' => $this->uniqueSlug($name, $product->id),
                    ]);
                    $stats['created']++;
                } else {
                    $product->update($data);
                    $stats['updated']++;
                }

                if ($item && (int) $item->product_id !== (int) $product->id) {
                    $item->update([
                        'product_id' => $product->id,
                        'target_category_id' => $category->id,
                        'match_status' => 'matched',
                        'match_method' => 'manual_created',
                        'match_confidence' => 1,
                        'candidates' => null,
                        'matched_at' => now(),
                    ]);
                    $recorder->record($item->fresh());
                    $stats['linked']++;
                }

                if (! $item && $source->supplier_id) {
                    SupplierProduct::query()->updateOrCreate([
                        'supplier_id' => $source->supplier_id,
                        'supplier_article_normalized' => Str::upper($definition['article']),
                    ], [
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'supplier_article' => $definition['article'],
                        'supplier_article_compact' => Str::upper($definition['article']),
                        'supplier_name' => $name,
                        'source_url' => $definition['url'],
                        'price' => null,
                        'price_byn' => null,
                        'currency' => 'BYN',
                        'currency_rate' => 1,
                        'in_stock' => false,
                        'stock_quantity' => null,
                        'stock_status' => 'preorder',
                        'stock_text' => 'Под заказ',
                        'match_status' => 'matched',
                        'match_confidence' => 'manual_price_list',
                        'raw' => ['retail_price' => $definition['price'], 'unit' => 'пог. м'],
                        'last_synced_at' => now(),
                    ]);
                    $stats['supplier_routes']++;
                }
            });

            if (($offer['images'] ?? []) !== []) {
                $enricher->enrichFromParsed($product->fresh(), $definition['url'], $offer, [
                    'update_images' => true,
                    'replace_images' => true,
                    'update_specs' => false,
                    'update_service' => false,
                    'update_documents' => false,
                    'update_video' => false,
                    'update_content' => false,
                    'fallback_remote_images' => true,
                ]);
            }
            $availability->refresh($product->id);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.sbg_stainless_duct.{$key}={$value}");
        }

        return $stats['skipped'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isStainlessDiameter(string $name, int $diameter): bool
    {
        $normalized = Str::lower($name);

        return Str::contains($normalized, ['нерж', 'нержав'])
            && (bool) preg_match('/(?<!\d)'.preg_quote((string) $diameter, '/').'(?!\d)/u', $normalized);
    }

    private function pickExactIntegrationRow(\Illuminate\Support\Collection $items): ?IntegrationProduct
    {
        if ($items->count() === 1) {
            return $items->first();
        }

        $positive = $items->filter(fn (IntegrationProduct $item): bool => (float) $item->stock_quantity > 0)->values();
        if ($positive->count() === 1) {
            return $positive->first();
        }

        $threeMetre = $items->filter(function (IntegrationProduct $item): bool {
            $name = preg_replace('/\s+/u', '', Str::lower($item->name));

            return Str::contains($name, ['3мнерж', '3м(нерж']);
        })->values();

        return $threeMetre->count() === 1 ? $threeMetre->first() : null;
    }

    private function uniqueSku(int $productId): string
    {
        $number = $productId;
        do {
            $sku = sprintf('KOTLOV-%06d', $number++);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name, int $productId): string
    {
        $base = Str::slug($name) ?: 'product-'.$productId;
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->whereKeyNot($productId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
