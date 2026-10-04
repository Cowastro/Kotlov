<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\ElectricSaunaHeaterFilterNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillElectricSaunaHeaterFiltersCommand extends Command
{
    protected $signature = 'catalog:backfill-electric-sauna-heater-filters
        {--apply : Persist normalized filter values; the default is a dry run}
        {--audit-inventory : Show why products from the category are excluded from the public catalog}
        {--sample=12 : Number of unresolved products to show}';

    protected $description = 'Safely normalize explicit power, steam-room volume and stone-weight filters for electric sauna heaters';

    public function handle(ElectricSaunaHeaterFilterNormalizer $normalizer): int
    {
        $category = Category::query()->where('slug', 'elektrokamenki')->first();
        if (! $category) {
            $this->error('Electric sauna heater category was not found.');

            return self::FAILURE;
        }

        $targets = $this->targets((int) $category->id);
        if (collect($targets)->contains(fn ($target) => $target === null)) {
            $this->error('Required filter attributes or options were not found. Run the catalog migration first.');

            return self::FAILURE;
        }

        if ($this->option('audit-inventory')) {
            $this->auditInventory((int) $category->id);
        }

        $products = Product::query()
            ->orderable()
            ->where('category_id', $category->id)
            ->with(['brand:id,name', 'allAttributeValues.attribute:id,name,type', 'allAttributeValues.option:id,name'])
            ->orderBy('id')
            ->get();

        $stats = [
            'products' => $products->count(),
            'power_added' => 0,
            'power_repaired' => 0,
            'volume_added' => 0,
            'volume_repaired' => 0,
            'stones_added' => 0,
            'stones_repaired' => 0,
            'unchanged' => 0,
        ];
        $writes = collect();
        $unresolved = collect();

        foreach ($products as $product) {
            $targetIds = collect($targets)->pluck('attribute.id')->map(fn ($id) => (int) $id)->all();
            $attributes = $product->allAttributeValues
                ->filter(fn ($row) => $row->attribute && ! in_array((int) $row->attribute_id, $targetIds, true))
                ->mapWithKeys(fn ($row) => [
                    $row->attribute->name => $row->option?->name ?? ($row->is_checked ? 'Да' : (string) $row->value),
                ])
                ->all();

            $facts = $normalizer->extract($product->specs ?: [], $attributes);
            $facts = $normalizer->withVerifiedModelFacts(
                $product->brand?->name ?? '',
                $product->name,
                $facts,
            );
            $productWrites = collect();

            $this->queueValue($product, $targets['power'], $normalizer->powerRange($facts['power']), 'power', $stats, $productWrites);
            $this->queueValue($product, $targets['volume'], $normalizer->volumeRange($facts['volume']), 'volume', $stats, $productWrites);
            $this->queueValue($product, $targets['stones'], $normalizer->stonesRange($facts['stones']), 'stones', $stats, $productWrites);

            if ($productWrites->isEmpty()) {
                $stats['unchanged']++;
            } else {
                $writes->push(...$productWrites);
            }

            $missing = collect(['power', 'volume'])
                ->filter(function (string $key) use ($product, $targets, $productWrites) {
                    return ! $product->allAttributeValues->contains('attribute_id', $targets[$key]['attribute']->id)
                        && ! $productWrites->contains(fn ($write) => $write['attribute_id'] === $targets[$key]['attribute']->id);
                })
                ->values()
                ->all();

            if ($missing !== []) {
                $unresolved->push([
                    'id' => $product->id,
                    'brand' => $product->brand?->name ?? '—',
                    'missing' => implode(', ', $missing),
                    'name' => $product->name,
                ]);
            }
        }

        if ($this->option('apply') && $writes->isNotEmpty()) {
            DB::transaction(function () use ($writes) {
                foreach ($writes as $write) {
                    ProductAttributeValue::query()->updateOrCreate(
                        [
                            'product_id' => $write['product_id'],
                            'attribute_id' => $write['attribute_id'],
                        ],
                        [
                            'option_id' => $write['option_id'],
                            'is_checked' => false,
                            'value' => null,
                        ]
                    );
                }
            });
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());
        $this->line('Mode: '.($this->option('apply') ? 'APPLY' : 'DRY RUN'));
        $this->line('Queued writes: '.$writes->count());
        $this->line('Products still missing explicit power or steam-room volume: '.$unresolved->count());

        if ($unresolved->isNotEmpty()) {
            $this->table(['ID', 'Brand', 'Missing', 'Product'], $unresolved->take(max(0, (int) $this->option('sample')))->values());
        }

        return self::SUCCESS;
    }

    private function auditInventory(int $categoryId): void
    {
        $base = Product::query()->where('category_id', $categoryId);
        $metrics = [
            ['all products in category', (clone $base)->count()],
            ['active', (clone $base)->where('is_active', true)->count()],
            ['archived', (clone $base)->where('is_archived', true)->count()],
            ['price greater than zero', (clone $base)->where('price', '>', 0)->count()],
            ['availability: check', (clone $base)->where('availability_status', Product::AVAILABILITY_CHECK)->count()],
            ['availability: in stock', (clone $base)->where('availability_status', Product::AVAILABILITY_IN_STOCK)->where('in_stock', true)->count()],
            ['availability: out of stock', (clone $base)->where('availability_status', Product::AVAILABILITY_OUT_OF_STOCK)->count()],
            ['public/orderable', (clone $base)->orderable()->count()],
        ];

        $this->newLine();
        $this->info('Electric sauna heater inventory audit (read-only)');
        $this->table(['Metric', 'Count'], $metrics);

        $excluded = (clone $base)
            ->where(function ($query) {
                $query->where('is_active', false)
                    ->orWhere('is_archived', true)
                    ->orWhere('price', '<=', 0)
                    ->orWhere(function ($availability) {
                        $availability->where('availability_status', '!=', Product::AVAILABILITY_CHECK)
                            ->where(function ($stock) {
                                $stock->where('availability_status', '!=', Product::AVAILABILITY_IN_STOCK)
                                    ->orWhere('in_stock', false);
                            });
                    });
            })
            ->with('brand:id,name')
            ->orderBy('id')
            ->limit(max(0, (int) $this->option('sample')))
            ->get();

        if ($excluded->isNotEmpty()) {
            $this->line('Sample excluded products:');
            $this->table(
                ['ID', 'Brand', 'Active', 'Archived', 'Price', 'In stock', 'Availability', 'Product'],
                $excluded->map(fn (Product $product) => [
                    $product->id,
                    $product->brand?->name ?? '—',
                    $product->is_active ? 'yes' : 'no',
                    $product->is_archived ? 'yes' : 'no',
                    $product->price,
                    $product->in_stock ? 'yes' : 'no',
                    $product->availability_status,
                    $product->name,
                ])->all(),
            );
        }

        $this->newLine();
    }

    private function queueValue(
        Product $product,
        array $target,
        ?string $optionName,
        string $metric,
        array &$stats,
        Collection $writes,
    ): void {
        if ($optionName === null) {
            return;
        }

        $option = $target['options']->get($this->normalize($optionName));
        if (! $option) {
            return;
        }

        $current = $product->allAttributeValues->firstWhere('attribute_id', $target['attribute']->id);
        if ($current && (int) $current->option_id === (int) $option->id) {
            return;
        }

        $writes->push([
            'product_id' => $product->id,
            'attribute_id' => $target['attribute']->id,
            'option_id' => $option->id,
        ]);
        $stats[$metric.'_'.($current ? 'repaired' : 'added')]++;
    }

    private function targets(int $categoryId): array
    {
        $attributes = Attribute::query()
            ->where('category_id', $categoryId)
            ->where('type', 'select')
            ->with('options')
            ->get()
            ->keyBy(fn (Attribute $attribute) => $this->normalize($attribute->name));

        return [
            'power' => $this->target($attributes->get($this->normalize('Мощность (кВт)'))),
            'volume' => $this->target($attributes->get($this->normalize('Максимальный объем парилки (m3)'))),
            'stones' => $this->target($attributes->get($this->normalize('Масса камней (кг)'))),
        ];
    }

    private function target(?Attribute $attribute): ?array
    {
        if (! $attribute) {
            return null;
        }

        return [
            'attribute' => $attribute,
            'options' => $attribute->options->keyBy(fn (AttributeOption $option) => $this->normalize($option->name)),
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace(['ё', '–', '—'], ['е', '—', '—'], $value)) ?? ''));
    }
}
