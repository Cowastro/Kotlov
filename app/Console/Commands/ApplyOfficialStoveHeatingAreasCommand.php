<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StoveHeatingAreaNormalizer;
use App\Services\StoveOfficialHeatingAreaCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyOfficialStoveHeatingAreasCommand extends Command
{
    protected $signature = 'catalog:apply-official-stove-heating-areas
        {--apply : Persist manufacturer-published heated areas; the default is a dry run}';

    protected $description = 'Add explicit stove heated areas verified in official manufacturer sources';

    public function handle(
        StoveOfficialHeatingAreaCatalog $catalog,
        StoveHeatingAreaNormalizer $normalizer,
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

            if ($product->category?->slug !== 'pechi-kaminy' || ! $product->is_active || $product->is_archived) {
                $skipped[] = [$product->id, $product->name, 'not an active stove product'];

                continue;
            }

            $value = $evidence['area'].' м²';
            $updatedSpecs = $this->withAreaSpec($product->specs ?: [], $value, $normalizer);
            $currentRange = $normalizer->detect($product->specs ?: []);
            $newRange = $normalizer->detect($updatedSpecs);

            if ($newRange === null) {
                $skipped[] = [$product->id, $product->name, 'conflicting area facts'];

                continue;
            }

            $plans->push(compact('product', 'evidence', 'value', 'updatedSpecs', 'currentRange', 'newRange'));
        }

        $this->table(
            ['ID', 'Product', 'Area', 'Range', 'Action', 'Official source'],
            $plans->map(fn (array $plan) => [
                $plan['product']->id,
                $plan['product']->name,
                $plan['value'],
                $plan['newRange'],
                $plan['currentRange'] === $plan['newRange'] ? 'verify provenance' : 'add fact',
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
                    $serviceInfo['official_heating_area'] = [
                        'value' => $plan['value'],
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
        $this->line('Only explicit manufacturer-published m² values were used; no kW or m³ conversion was made.');

        return ($missing === [] && $skipped === []) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int|string, mixed>  $specs
     * @return array<int|string, mixed>
     */
    private function withAreaSpec(array $specs, string $value, StoveHeatingAreaNormalizer $normalizer): array
    {
        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key']) && $normalizer->isAreaKey((string) $spec['key'])) {
                $specs[$key]['value'] = $value;

                return $specs;
            }

            if (is_string($key) && $normalizer->isAreaKey($key)) {
                $specs[$key] = $value;

                return $specs;
            }
        }

        $specs[] = [
            'key' => 'Площадь отапливаемого помещения',
            'value' => $value,
        ];

        return $specs;
    }
}
