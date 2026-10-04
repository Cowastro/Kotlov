<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StoveHeatingVolumeNormalizer;
use App\Services\StoveOfficialHeatingVolumeCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyOfficialStoveHeatingVolumesCommand extends Command
{
    private const CATEGORY_SLUGS = [
        'pechi-kaminy',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
    ];

    protected $signature = 'catalog:apply-official-stove-heating-volumes
        {--apply : Persist manufacturer-published room volumes; the default is a dry run}';

    protected $description = 'Add explicit stove room-volume facts verified in official manufacturer sources';

    public function handle(
        StoveOfficialHeatingVolumeCatalog $catalog,
        StoveHeatingVolumeNormalizer $normalizer,
    ): int {
        $apply = (bool) $this->option('apply');
        $entries = $catalog->entries();
        $products = Product::query()
            ->whereIn('slug', array_keys($entries))
            ->with('category:id,slug')
            ->get()
            ->keyBy('slug');

        $plans = collect();
        $missing = [];
        $skipped = [];

        foreach ($entries as $slug => $evidence) {
            /** @var Product|null $product */
            $product = $products->get($slug);
            if (! $product) {
                $missing[] = $slug;
                continue;
            }

            if (! in_array($product->category?->slug, self::CATEGORY_SLUGS, true)
                || ! $product->is_active
                || $product->is_archived) {
                $skipped[] = [$product->id, $product->name, 'not an active heating stove'];
                continue;
            }

            $updatedSpecs = $this->withVolumeSpec($product->specs ?: [], $evidence['volume'], $normalizer);
            $range = $normalizer->detect($updatedSpecs);
            if ($range === null) {
                $skipped[] = [$product->id, $product->name, 'conflicting room-volume facts'];
                continue;
            }

            $plans->push(compact('product', 'evidence', 'updatedSpecs', 'range'));
        }

        $this->table(
            ['ID', 'Product', 'Volume', 'Filter range', 'Official source'],
            $plans->map(fn (array $plan) => [
                $plan['product']->id,
                $plan['product']->name,
                $plan['evidence']['volume'],
                $plan['range'],
                $plan['evidence']['source_url'],
            ])->all()
        );

        if ($skipped !== []) {
            $this->warn('Skipped products:');
            $this->table(['ID', 'Product', 'Reason'], $skipped);
        }
        foreach ($missing as $slug) {
            $this->warn("Missing product: {$slug}");
        }

        if ($apply) {
            DB::transaction(function () use ($plans) {
                foreach ($plans as $plan) {
                    /** @var Product $product */
                    $product = $plan['product'];
                    $serviceInfo = is_array($product->service_info) ? $product->service_info : [];
                    $serviceInfo['official_heating_volume'] = [
                        'value' => $plan['evidence']['volume'],
                        'source_url' => $plan['evidence']['source_url'],
                        'source_label' => $plan['evidence']['source_label'],
                        'verified_at' => now()->toDateString(),
                    ];

                    $product->update([
                        'specs' => $plan['updatedSpecs'],
                        'service_info' => $serviceInfo,
                    ]);
                }
            });
        }

        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line(sprintf(
            'Evidence entries: %d; eligible products: %d; skipped: %d; missing: %d.',
            count($entries),
            $plans->count(),
            count($skipped),
            count($missing),
        ));
        $this->line('Official m³ values remain separate from heated-area m² filters; no conversion was made.');

        return ($missing === [] && $skipped === []) ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<int|string, mixed> $specs */
    private function withVolumeSpec(array $specs, string $value, StoveHeatingVolumeNormalizer $normalizer): array
    {
        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key']) && $normalizer->isVolumeKey((string) $spec['key'])) {
                $specs[$key]['value'] = $value;
                return $specs;
            }

            if (is_string($key) && $normalizer->isVolumeKey($key)) {
                $specs[$key] = $value;
                return $specs;
            }
        }

        $specs[] = ['key' => 'Объём отапливаемого помещения', 'value' => $value];

        return $specs;
    }
}
