<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StoveHeatingAreaFromVolumeConverter;
use App\Services\StoveHeatingAreaNormalizer;
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
        StoveHeatingAreaNormalizer $areaNormalizer,
        StoveHeatingAreaFromVolumeConverter $areaConverter,
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

            $serviceInfo = is_array($product->service_info) ? $product->service_info : [];
            $derivedArea = $areaConverter->convert($evidence['volume']);
            [$updatedSpecs, $derivedArea] = $this->withDerivedAreaSpec(
                $updatedSpecs,
                $derivedArea,
                $serviceInfo,
                $areaNormalizer,
                $areaConverter,
            );

            $plans->push(compact('product', 'evidence', 'updatedSpecs', 'range', 'derivedArea'));
        }

        $this->table(
            ['ID', 'Product', 'Volume', 'Area at 2.5 m', 'Official source'],
            $plans->map(fn (array $plan) => [
                $plan['product']->id,
                $plan['product']->name,
                $plan['evidence']['volume'],
                $plan['derivedArea'] !== null ? $areaConverter->label($plan['derivedArea']) : 'kept explicit area',
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

                    if ($plan['derivedArea'] !== null) {
                        $serviceInfo['derived_heating_area'] = [
                            'value' => $plan['derivedArea'],
                            'ceiling_height_m' => StoveHeatingAreaFromVolumeConverter::STANDARD_CEILING_HEIGHT,
                            'formula' => 'maximum official volume / ceiling height',
                            'source_url' => $plan['evidence']['source_url'],
                            'verified_at' => now()->toDateString(),
                        ];
                    }

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
        $this->line('The m² filter uses maximum official volume / 2.5 m; an explicit manufacturer area always has priority.');

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

    /**
     * Refresh an area previously derived by this command while preserving a
     * manufacturer-published or manually corrected explicit area.
     *
     * @param  array<int|string, mixed>  $specs
     * @param  array<string, mixed>  $serviceInfo
     * @return array{0: array<int|string, mixed>, 1: float|null}
     */
    private function withDerivedAreaSpec(
        array $specs,
        ?float $newArea,
        array $serviceInfo,
        StoveHeatingAreaNormalizer $normalizer,
        StoveHeatingAreaFromVolumeConverter $converter,
    ): array {
        if ($newArea === null) {
            return [$specs, null];
        }

        $previousArea = data_get($serviceInfo, 'derived_heating_area.value');
        $previousLabel = is_numeric($previousArea) ? $converter->label((float) $previousArea) : null;

        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key']) && $normalizer->isAreaKey((string) $spec['key'])) {
                if ($previousLabel !== null && trim((string) ($spec['value'] ?? '')) === $previousLabel) {
                    $specs[$key]['value'] = $converter->label($newArea);

                    return [$specs, $newArea];
                }

                return [$specs, null];
            }

            if (is_string($key) && $normalizer->isAreaKey($key)) {
                if ($previousLabel !== null && trim((string) $spec) === $previousLabel) {
                    $specs[$key] = $converter->label($newArea);

                    return [$specs, $newArea];
                }

                return [$specs, null];
            }
        }

        $specs[] = [
            'key' => 'Площадь отапливаемого помещения',
            'value' => $converter->label($newArea),
        ];

        return [$specs, $newArea];
    }
}
