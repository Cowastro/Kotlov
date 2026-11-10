<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Catalog\FireplaceSpecifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FillOfficialStoveAreasCommand extends Command
{
    protected $signature = 'catalog:fill-official-stove-areas
        {--apply : Save the verified manufacturer areas}';

    protected $description = 'Fill missing measured stove areas from exact manufacturer model references';

    public function handle(): int
    {
        $evidence = require database_path('data/stove-official-areas.php');
        $apply = (bool) $this->option('apply');

        $counts = DB::transaction(function () use ($evidence, $apply): array {
            $query = Product::query()->orderable()->whereIn('slug', array_keys($evidence))
                ->with(['category', 'attributeValues'])->orderBy('id');
            if ($apply) {
                $query->lockForUpdate();
            }

            $products = $query->get()->keyBy('slug');
            $counts = ['filled' => 0, 'estimates_replaced' => 0, 'already_present' => 0, 'unmatched' => 0];
            $label = 'Площадь обогрева';

            foreach ($evidence as $slug => [$category, $value, $source]) {
                $product = $products->get($slug);
                if ($product?->category?->slug !== $category) {
                    $counts['unmatched']++;
                    continue;
                }

                $current = FireplaceSpecifications::heatingFact($product);
                if (($current['label'] ?? null) === $label) {
                    $counts['already_present']++;
                    continue;
                }

                $product->specs = [
                    ['key' => $label, 'value' => $value, 'source_url' => $source],
                    ...($product->specs ?? []),
                ];

                if (FireplaceSpecifications::heatingFact($product) !== ['label' => $label, 'value' => $value, 'is_area' => true]) {
                    throw new RuntimeException("Stove area would not render as reviewed: {$slug}");
                }

                if ($apply) {
                    $product->save();
                }
                $counts['filled']++;
                $counts['estimates_replaced'] += (int) ($current['is_area'] ?? false);
            }

            return $counts;
        });

        foreach ($counts as $metric => $count) {
            $this->line("metric.stove_areas.{$metric}={$count}");
        }

        return self::SUCCESS;
    }
}
