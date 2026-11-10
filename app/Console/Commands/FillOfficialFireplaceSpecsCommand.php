<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Catalog\FireplaceSpecifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FillOfficialFireplaceSpecsCommand extends Command
{
    protected $signature = 'catalog:fill-official-fireplace-specs
        {--apply : Save the verified missing values}';

    protected $description = 'Fill missing public fireplace specs from the reviewed manufacturer evidence';

    public function handle(): int
    {
        $evidence = require database_path('data/fireplace-official-specs.php');
        $apply = (bool) $this->option('apply');

        $counts = DB::transaction(function () use ($evidence, $apply): array {
            $query = Product::query()
                ->orderable()
                ->whereHas('category', fn ($query) => $query->where('slug', 'pechi-kaminy'))
                ->whereIn('slug', array_keys($evidence))
                ->with('attributeValues')
                ->orderBy('id');
            if ($apply) {
                $query->lockForUpdate();
            }

            $products = $query->get()->keyBy('slug');
            $counts = ['products' => 0, 'fields' => 0, 'already_present' => 0, 'unmatched' => 0, 'complete_after' => 0];

            foreach ($evidence as $slug => [$source, $values]) {
                $product = $products->get($slug);
                if ($product === null) {
                    $counts['unmatched']++;
                    continue;
                }

                $current = FireplaceSpecifications::card($product);
                $missing = array_diff_key($values, $current);
                $counts['already_present'] += count($values) - count($missing);
                if ($missing === []) {
                    continue;
                }

                $additions = [];
                foreach ($missing as $label => $value) {
                    $additions[] = ['key' => $label, 'value' => $value, 'source_url' => $source];
                }

                $specs = [...$additions, ...($product->specs ?? [])];
                $product->specs = $specs;
                $updated = FireplaceSpecifications::card($product);
                if (array_intersect_key($updated, $current) !== $current
                    || array_diff_assoc($missing, $updated) !== []) {
                    throw new RuntimeException("Fireplace specifications would not render as reviewed: {$slug}");
                }

                if ($apply) {
                    $product->save();
                }
                $counts['products']++;
                $counts['fields'] += count($missing);
                $counts['complete_after'] += (int) (count($updated) === 3);
            }

            return $counts;
        });

        foreach ($counts as $metric => $count) {
            $this->line("metric.fireplace_specs.{$metric}={$count}");
        }

        return self::SUCCESS;
    }
}
