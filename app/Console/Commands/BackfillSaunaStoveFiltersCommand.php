<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\SaunaStoveFilterNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillSaunaStoveFiltersCommand extends Command
{
    protected $signature = 'catalog:backfill-sauna-stove-filters
        {--apply : Persist inferred filter values; the default is a dry run}
        {--sample=12 : Number of unresolved products to show}';

    protected $description = 'Safely link explicit sauna-stove characteristics to catalog filters';

    public function handle(SaunaStoveFilterNormalizer $normalizer): int
    {
        $category = Category::query()
            ->whereIn('slug', ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'])
            ->first();

        if (! $category) {
            $this->error('Sauna stove category was not found.');

            return self::FAILURE;
        }

        $targets = $this->targets((int) $category->id);
        if (collect($targets)->contains(fn ($target) => $target === null)) {
            $this->error('Required filter attributes or options were not found.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['brand:id,name', 'allAttributeValues.attribute:id,name,type', 'allAttributeValues.option:id,name'])
            ->orderBy('id')
            ->get();

        $stats = [
            'products' => $products->count(),
            'volume_added' => 0,
            'door_added' => 0,
            'remote_added' => 0,
            'unchanged' => 0,
        ];
        $unresolved = collect();
        $writes = collect();

        foreach ($products as $product) {
            $attributes = $product->allAttributeValues
                ->filter(fn ($row) => $row->attribute && ! in_array((int) $row->attribute_id, [
                    $targets['volume']['attribute']->id,
                    $targets['door']['attribute']->id,
                    $targets['remote']['attribute']->id,
                ], true))
                ->mapWithKeys(fn ($row) => [
                    $row->attribute->name => $row->option?->name ?? ($row->is_checked ? 'Да' : (string) $row->value),
                ])
                ->all();

            $facts = $normalizer->extract(
                $product->name,
                $product->specs ?: [],
                $attributes,
                $product->short_description,
                $product->content,
            );
            $facts = $normalizer->withVerifiedModelFacts(
                $product->brand?->name ?? '',
                $product->name,
                $facts,
            );

            $productWrites = collect();
            $volumeRange = $normalizer->volumeRange($facts['volume']);
            if ($volumeRange !== null) {
                $this->queueMissing($product, $targets['volume'], $volumeRange, 'volume_added', $stats, $productWrites);
            }
            if ($facts['door'] !== null) {
                $this->queueMissing($product, $targets['door'], $facts['door'] ? 'со стеклом' : 'без стекла', 'door_added', $stats, $productWrites);
            }
            if ($facts['remote_firebox'] !== null) {
                $this->queueMissing($product, $targets['remote'], $facts['remote_firebox'] ? 'да' : 'нет', 'remote_added', $stats, $productWrites);
            }

            if ($productWrites->isEmpty()) {
                $stats['unchanged']++;
            } else {
                $writes->push(...$productWrites);
            }

            $hasVolume = $product->allAttributeValues->contains('attribute_id', $targets['volume']['attribute']->id)
                || $productWrites->contains(fn ($write) => $write['attribute_id'] === $targets['volume']['attribute']->id);
            if (! $hasVolume) {
                $unresolved->push([
                    'id' => $product->id,
                    'brand' => $product->brand?->name ?? '—',
                    'name' => $product->name,
                ]);
            }
        }

        if ($this->option('apply') && $writes->isNotEmpty()) {
            DB::transaction(function () use ($writes) {
                foreach ($writes as $write) {
                    ProductAttributeValue::query()->firstOrCreate(
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
        $this->line('Products still missing an explicit steam-room volume: '.$unresolved->count());

        if ($unresolved->isNotEmpty()) {
            $this->table(['ID', 'Brand', 'Product'], $unresolved->take(max(0, (int) $this->option('sample')))->values());
        }

        return self::SUCCESS;
    }

    private function queueMissing(
        Product $product,
        array $target,
        string $optionName,
        string $stat,
        array &$stats,
        Collection $writes,
    ): void {
        if ($product->allAttributeValues->contains('attribute_id', $target['attribute']->id)) {
            return;
        }

        $option = $target['options']->get($this->normalize($optionName));
        if (! $option) {
            return;
        }

        $writes->push([
            'product_id' => $product->id,
            'attribute_id' => $target['attribute']->id,
            'option_id' => $option->id,
        ]);
        $stats[$stat]++;
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
            'volume' => $this->target($attributes->get($this->normalize('Максимальный объем парилки (m3)'))),
            'door' => $this->target($attributes->get($this->normalize('Дверца'))),
            'remote' => $this->target($attributes->get($this->normalize('Выносная топка'))),
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
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('ё', 'е', $value)) ?? ''));
    }
}
