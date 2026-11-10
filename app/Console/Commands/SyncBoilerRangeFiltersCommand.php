<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\Catalog\BoilerCatalog;
use App\Support\Catalog\BoilerSpecifications;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncBoilerRangeFiltersCommand extends Command
{
    protected $signature = 'catalog:sync-boiler-range-filters
        {--apply : Repair and create unambiguous range assignments}
        {--coverage : Report usable specifications for all four boiler categories}
        {--sample=10 : Number of public examples to print for each unresolved group}';

    protected $description = 'Audit and repair gas, solid-fuel and electric boiler power/area range filters from explicit catalogue facts.';

    public function handle(BoilerSpecifications $specifications): int
    {
        if ($this->option('coverage')) {
            return $this->coverage();
        }

        $apply = (bool) $this->option('apply');
        $sample = max(0, (int) $this->option('sample'));
        $totals = [
            'products' => 0,
            'resolved' => 0,
            'already_correct' => 0,
            'would_insert' => 0,
            'would_repair' => 0,
            'repair_multiple' => 0,
            'repair_wrong_attribute' => 0,
            'repair_wrong_option' => 0,
            'inserted' => 0,
            'repaired' => 0,
            'missing_numeric_fact' => 0,
            'conflicting_numeric_facts' => 0,
        ];

        foreach (BoilerSpecifications::AUDITED_RANGES as $catalogKey => $catalog) {
            $root = Category::query()->where('slug', $catalog['slug'])->first();
            if (! $root) {
                $this->warn("Категория {$catalog['slug']} не найдена.");

                continue;
            }

            $categoryIds = $this->categoryIds((int) $root->id);
            $products = Product::query()
                ->orderable()
                ->whereIn('category_id', $categoryIds)
                ->with(['allAttributeValues.attribute'])
                ->get();
            $totals['products'] += $products->count();

            foreach ($catalog['ranges'] as $kind => $definition) {
                $filter = $this->filterDefinition($categoryIds, (int) $root->id, $definition);
                if (! $filter) {
                    $this->warn("Фильтр {$catalogKey}.{$kind} не найден или не содержит всех диапазонов.");

                    continue;
                }

                $stats = [
                    'resolved' => 0,
                    'already_correct' => 0,
                    'would_insert' => 0,
                    'would_repair' => 0,
                    'repair_multiple' => 0,
                    'repair_wrong_attribute' => 0,
                    'repair_wrong_option' => 0,
                    'inserted' => 0,
                    'repaired' => 0,
                    'missing_numeric_fact' => 0,
                    'conflicting_numeric_facts' => 0,
                ];
                $unresolved = [];

                foreach ($products as $product) {
                    $resolution = $specifications->numericFact($product, $kind);
                    if ($resolution['status'] !== 'resolved') {
                        $stats[$resolution['status']]++;
                        if (count($unresolved) < $sample) {
                            $unresolved[] = [
                                $product->id,
                                $product->name,
                                $resolution['status'],
                            ];
                        }

                        continue;
                    }

                    $targetOptionId = $this->targetOptionId($resolution['value'], $definition['options'], $filter['options']);
                    if (! $targetOptionId) {
                        $stats['missing_numeric_fact']++;

                        continue;
                    }

                    $stats['resolved']++;
                    $existing = $product->allAttributeValues
                        ->whereIn('attribute_id', $filter['attribute_ids'])
                        ->whereNotNull('option_id');
                    $correct = $existing->count() === 1
                        && (int) $existing->first()->attribute_id === $filter['primary_attribute_id']
                        && (int) $existing->first()->option_id === $targetOptionId;

                    if ($correct) {
                        $stats['already_correct']++;

                        continue;
                    }

                    $action = $existing->isEmpty() ? 'insert' : 'repair';
                    $stats['would_'.$action]++;
                    if ($action === 'repair') {
                        if ($existing->count() > 1) {
                            $stats['repair_multiple']++;
                        } elseif ((int) $existing->first()->attribute_id !== $filter['primary_attribute_id']) {
                            $stats['repair_wrong_attribute']++;
                        } else {
                            $stats['repair_wrong_option']++;
                        }
                    }
                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($product, $filter, $targetOptionId): void {
                        ProductAttributeValue::query()
                            ->where('product_id', $product->id)
                            ->whereIn('attribute_id', $filter['attribute_ids'])
                            ->delete();
                        ProductAttributeValue::query()->create([
                            'product_id' => $product->id,
                            'attribute_id' => $filter['primary_attribute_id'],
                            'option_id' => $targetOptionId,
                            'value' => '',
                            'is_checked' => false,
                        ]);
                    });
                    $stats[$action === 'insert' ? 'inserted' : 'repaired']++;
                }

                foreach ($stats as $metric => $value) {
                    $totals[$metric] += $value;
                    $this->line("metric.filter.{$catalogKey}.{$kind}.{$metric}={$value}");
                }
                foreach ($unresolved as [$id, $name, $reason]) {
                    $this->line("sample.{$catalogKey}.{$kind}.{$reason}=#{$id} {$name}");
                }
            }
        }

        foreach ($totals as $metric => $value) {
            $this->line("metric.filter.total.{$metric}={$value}");
        }
        $this->info($apply ? 'Диапазонные фильтры синхронизированы.' : 'Аудит завершён; без --apply данные не изменены.');

        return self::SUCCESS;
    }

    /** @return Collection<int,int> */
    private function coverage(): int
    {
        $catalog = app(BoilerCatalog::class);
        $specifications = app(BoilerSpecifications::class);
        foreach (array_keys(array_diff_key(BoilerCatalog::NAMES, ['kotly' => true])) as $slug) {
            $root = Category::active()->where('slug', $slug)->first();
            if ($root === null) {
                continue;
            }

            $facts = $catalog->facts($slug, $this->categoryIds($root->id));
            $count = count($facts);
            $this->line("metric.filter.coverage.{$slug}.products={$count}");
            foreach (array_keys($specifications->dimensions($slug)) as $key) {
                $withFact = count(array_filter($facts, fn ($fact) => isset($fact['options'][$key])));
                $missing = $count - $withFact;
                $this->line("metric.filter.coverage.{$slug}.{$key}.known={$withFact}");
                $this->line("metric.filter.coverage.{$slug}.{$key}.missing={$missing}");
            }
        }

        return self::SUCCESS;
    }

    private function categoryIds(int $rootId): Collection
    {
        $ids = collect([$rootId]);
        $frontier = collect([$rootId]);
        while ($frontier->isNotEmpty()) {
            $children = Category::query()->whereIn('parent_id', $frontier)->pluck('id');
            $new = $children->diff($ids);
            if ($new->isEmpty()) {
                break;
            }
            $ids = $ids->merge($new);
            $frontier = $new;
        }

        return $ids->unique()->values();
    }

    /** @param Collection<int,int> $categoryIds @param array<string,mixed> $definition */
    private function filterDefinition(Collection $categoryIds, int $rootCategoryId, array $definition): ?array
    {
        $expectedName = $this->normalizeName($definition['attribute']);
        $attributes = Attribute::query()
            ->whereIn('category_id', $categoryIds)
            ->where('type', 'select')
            ->where('in_filter', true)
            ->with('options')
            ->get()
            ->filter(fn (Attribute $attribute): bool => $this->normalizeName($attribute->name) === $expectedName)
            ->values();
        if ($attributes->isEmpty()) {
            return null;
        }

        $primary = $attributes->firstWhere('category_id', $rootCategoryId) ?? $attributes->first();
        $options = [];
        foreach ($definition['options'] as [$label]) {
            $option = $primary->options->first(
                fn ($option): bool => $this->normalizeName($option->name) === $this->normalizeName($label)
            );
            if (! $option) {
                return null;
            }
            $options[$this->normalizeName($label)] = (int) $option->id;
        }

        return [
            'primary_attribute_id' => (int) $primary->id,
            'attribute_ids' => $attributes->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'options' => $options,
        ];
    }

    /** @param array<int,array{0:string,1:?float,2:?float}> $ranges @param array<string,int> $optionIds */
    private function targetOptionId(float $value, array $ranges, array $optionIds): ?int
    {
        foreach ($ranges as [$label, $min, $max]) {
            $matches = ($min === null && $value <= $max)
                || ($max === null && $value > $min)
                || ($min !== null && $max !== null && $value > $min && $value <= $max);
            if ($matches) {
                return $optionIds[$this->normalizeName($label)] ?? null;
            }
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '²'], ['е', '2'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
