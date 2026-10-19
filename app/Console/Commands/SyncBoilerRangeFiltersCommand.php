<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncBoilerRangeFiltersCommand extends Command
{
    protected $signature = 'catalog:sync-boiler-range-filters
        {--apply : Repair and create unambiguous range assignments}
        {--sample=10 : Number of public examples to print for each unresolved group}';

    protected $description = 'Audit and repair gas, solid-fuel and electric boiler power/area range filters from explicit catalogue facts.';

    /** @var array<string,array<string,mixed>> */
    private const CATALOGS = [
        'gas' => [
            'slug' => 'gazovye',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 150 м²', null, 150],
                        ['150–250 м²', 150, 250],
                        ['250–350 м²', 250, 350],
                        ['свыше 350 м²', 350, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 12 кВт', null, 12],
                        ['12–24 кВт', 12, 24],
                        ['24–35 кВт', 24, 35],
                        ['35–50 кВт', 35, 50],
                        ['свыше 50 кВт', 50, null],
                    ],
                ],
            ],
        ],
        'solid' => [
            'slug' => 'tverdotoplivnye',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 150 м²', null, 150],
                        ['150–300 м²', 150, 300],
                        ['300–500 м²', 300, 500],
                        ['свыше 500 м²', 500, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 20 кВт', null, 20],
                        ['20–50 кВт', 20, 50],
                        ['50–100 кВт', 50, 100],
                        ['100–200 кВт', 100, 200],
                        ['свыше 200 кВт', 200, null],
                    ],
                ],
            ],
        ],
        'electric' => [
            'slug' => 'elektricheskie',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 90 м²', null, 90],
                        ['90–180 м²', 90, 180],
                        ['свыше 180 м²', 180, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 6 кВт', null, 6],
                        ['6–12 кВт', 6, 12],
                        ['12–24 кВт', 12, 24],
                        ['свыше 24 кВт', 24, null],
                    ],
                ],
            ],
        ],
    ];

    public function handle(): int
    {
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

        foreach (self::CATALOGS as $catalogKey => $catalog) {
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
                    $resolution = $this->resolveNumericFact($product, $kind);
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

    /** @return array{status:string,value:?float,source:?string} */
    private function resolveNumericFact(Product $product, string $kind): array
    {
        $candidates = collect();
        foreach ($product->allAttributeValues as $value) {
            if ($value->option_id || ! $value->attribute || blank($value->value)) {
                continue;
            }
            $score = $this->factKeyScore($value->attribute->name, $kind);
            if ($score > 0 && ($numeric = $this->numericValue((string) $value->value, $kind)) !== null) {
                $candidates->push(['score' => 300 + $score, 'value' => $numeric, 'source' => 'attribute']);
            }
        }
        foreach ($product->specs ?: [] as $specKey => $spec) {
            if (is_array($spec)) {
                $key = (string) ($spec['key'] ?? $spec['name'] ?? '');
                $value = (string) ($spec['value'] ?? '');
            } elseif (is_string($specKey) && (is_string($spec) || is_numeric($spec))) {
                // Часть старых карточек хранит характеристики как
                // {"Мощность":"35 кВт"}, а не списком key/value.
                $key = $specKey;
                $value = (string) $spec;
            } else {
                continue;
            }
            $score = $this->factKeyScore($key, $kind);
            if ($score > 0 && ($numeric = $this->numericValue($value, $kind)) !== null) {
                $candidates->push(['score' => 200 + $score, 'value' => $numeric, 'source' => 'spec']);
            }
        }

        if ($numeric = $this->numericFromName($product->name, $kind)) {
            $candidates->push(['score' => 100, 'value' => $numeric, 'source' => 'name']);
        }
        if ($candidates->isEmpty()) {
            return ['status' => 'missing_numeric_fact', 'value' => null, 'source' => null];
        }

        $topScore = (int) $candidates->max('score');
        $top = $candidates->where('score', $topScore);
        $values = $top->pluck('value')->map(fn ($value): float => round((float) $value, 3))->unique()->values();
        if ($values->count() !== 1) {
            return ['status' => 'conflicting_numeric_facts', 'value' => null, 'source' => null];
        }

        return [
            'status' => 'resolved',
            'value' => (float) $values->first(),
            'source' => (string) $top->first()['source'],
        ];
    }

    private function factKeyScore(string $key, string $kind): int
    {
        $key = $this->normalizeName($key);
        if ($kind === 'area') {
            if (! str_contains($key, 'площад') || (! str_contains($key, 'отоп') && ! str_contains($key, 'отапл') && ! str_contains($key, 'обогрев'))) {
                return 0;
            }

            return str_contains($key, 'помещ') ? 30 : 20;
        }
        if (! str_contains($key, 'мощност') || str_contains($key, 'площад')) {
            return 0;
        }
        foreach (['гвс', 'электричес', 'потребляем', 'насос', 'вентилятор', 'горелк', 'ступен'] as $excluded) {
            if (str_contains($key, $excluded)) {
                return 0;
            }
        }

        return match (true) {
            str_contains($key, 'номиналь') => 40,
            $key === 'мощность', $key === 'мощность квт', str_contains($key, 'мощность котла') => 35,
            str_contains($key, 'тепловая') => 30,
            default => 10,
        };
    }

    private function numericValue(string $raw, string $kind): ?float
    {
        $raw = str_replace(',', '.', trim($raw));
        $unitPattern = $kind === 'power' ? '(?:квт|kw)' : '(?:м²|м2|m2|кв\.?\s*м)';
        preg_match_all('/(\d+(?:\.\d+)?)\s*'.$unitPattern.'/ui', $raw, $withUnit);
        $numbers = array_map('floatval', $withUnit[1] ?? []);
        if ($numbers === []) {
            preg_match_all('/\d+(?:\.\d+)?/', $raw, $plain);
            $numbers = array_map('floatval', $plain[0] ?? []);
        }
        if ($numbers === []) {
            return null;
        }

        $value = max($numbers);
        $limit = $kind === 'power' ? 5000 : 100000;

        return $value > 0 && $value <= $limit ? $value : null;
    }

    private function numericFromName(string $name, string $kind): ?float
    {
        $pattern = $kind === 'power'
            ? '/(\d+(?:[.,]\d+)?)\s*(?:квт|kw)\b/ui'
            : '/(?:до\s*)?(\d+(?:[.,]\d+)?)\s*(?:м²|м2|m2|кв\.?\s*м)\b/ui';
        preg_match_all($pattern, $name, $matches);
        if (($matches[1] ?? []) === []) {
            return null;
        }

        return max(array_map(fn ($value): float => (float) str_replace(',', '.', $value), $matches[1]));
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
