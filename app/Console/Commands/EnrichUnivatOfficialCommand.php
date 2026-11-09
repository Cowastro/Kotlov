<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UNIVAT electric boilers (Econom, Standart, Standart OS, Comfort; 3–15 kW) from the official univat.by catalogue
 * and the boiler passport (ООО «Термокрафт»): the series gallery, 38 characteristics (incl. cable cross-section, relay
 * or contactor, protections), a full description and the catalogue filter values.
 * Data: resources/data/suppliers/univat-official-2026-11.json, photos: public/img/products/univat/{series}/.
 * Prices, stock and supplier links stay with supplier:sync-thermokraft.
 *
 *   php artisan catalog:enrich-univat-official            # dry run
 *   php artisan catalog:enrich-univat-official --apply
 */
class EnrichUnivatOfficialCommand extends Command
{
    protected $signature = 'catalog:enrich-univat-official {--apply : Write photos, characteristics, descriptions and filter values}';

    protected $description = 'Fill the UNIVAT electric boiler cards from the official univat.by catalogue and passport';

    public const DATA_FILE = 'data/suppliers/univat-official-2026-11.json';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $items = json_decode((string) file_get_contents(resource_path(self::DATA_FILE)), true, 512, JSON_THROW_ON_ERROR);
        $stats = ['items' => count($items), 'found' => 0, 'missing' => 0, 'updated' => 0, 'images' => 0, 'attribute_values' => 0];

        foreach ($items as $item) {
            foreach ($item['images'] as $image) {
                if (! is_file(public_path($image))) {
                    $this->error('Нет файла фото: '.$image);

                    return self::FAILURE;
                }
            }
        }

        $products = Product::query()->whereIn('slug', array_column($items, 'slug'))->get()->keyBy('slug');
        foreach ($items as $item) {
            /** @var Product|null $product */
            $product = $products->get($item['slug']);
            if (! $product) {
                $stats['missing']++;
                $this->line('audit.univat_official.missing='.$item['slug']);

                continue;
            }
            $stats['found']++;
            $hadImages = count($product->imagePaths());

            if ($apply) {
                DB::transaction(function () use ($product, $item, &$stats): void {
                    $product->forceFill([
                        'images' => $item['images'],
                        'specs' => array_map(fn (array $spec): array => ['key' => $spec[0], 'value' => $spec[1]], $item['specs']),
                        'short_description' => $item['short'],
                        'content' => $item['content'],
                        'meta_title' => $item['meta_title'],
                        'meta_description' => $item['meta_description'],
                        'content_source_url' => $item['source_url'],
                        'warranty' => '12 мес.',
                        'service_info' => [
                            ['key' => 'Гарантия', 'value' => '12 месяцев от производителя'],
                            ['key' => 'Производитель', 'value' => 'UNIVAT, ООО «Термокрафт», Беларусь'],
                            ['key' => 'Монтаж и запуск', 'value' => 'Специалистом с отметкой в паспорте котла'],
                        ],
                    ])->save();
                    $stats['attribute_values'] += $this->syncAttributes($product, $item);
                });
                $stats['updated']++;
            }
            $stats['images'] += $hadImages === 0 ? 1 : 0;
            $this->line(sprintf('audit.univat_official.card=%d:%s:%d:%d', $product->id, $item['slug'], $hadImages, count($item['images'])));
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.univat_official.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Catalogue filters of the card's category: numeric ones from the official data, the rest copied from the 9 kW
     * model of the same series (filled earlier), voltage by the model's phase.
     */
    private function syncAttributes(Product $product, array $item): int
    {
        $attributes = DB::table('attributes')->where('category_id', $product->category_id)->get(['id', 'name', 'type', 'suffix']);
        if ($attributes->isEmpty()) {
            return 0;
        }

        $donorSlug = 'univat-'.$item['series'].'-9-400';
        $donorId = $donorSlug !== $product->slug ? Product::query()->where('slug', $donorSlug)->value('id') : null;
        $donorValues = $donorId
            ? DB::table('product_attribute_values')->where('product_id', $donorId)->get()->keyBy('attribute_id')
            : collect();

        $numeric = [
            'мощность' => $item['power'],
            'площадь обогрева' => $item['area'],
            'отапливаемая площадь' => $item['area'],
            'кпд' => 99,
            'вес' => $item['weight'],
        ];

        $written = 0;
        foreach ($attributes as $attribute) {
            $name = mb_strtolower(trim($attribute->name));
            $row = null;
            $numberKey = collect(array_keys($numeric))->first(fn (string $key): bool => str_starts_with($name, $key));
            if ($numberKey !== null && $attribute->type === 'value') {
                $row = ['option_id' => null, 'is_checked' => null, 'value' => rtrim(rtrim(number_format((float) $numeric[$numberKey], 1, '.', ''), '0'), '.')];
            } elseif (str_contains($name, 'напряж') || str_contains($name, 'фаз')) {
                $row = $this->voltageRow($attribute, (bool) $item['single_phase']);
            } elseif ($donorValues->has($attribute->id)) {
                $donor = $donorValues->get($attribute->id);
                $row = ['option_id' => $donor->option_id, 'is_checked' => $donor->is_checked, 'value' => $donor->value];
            }
            if ($row === null) {
                continue;
            }

            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $product->id, 'attribute_id' => $attribute->id],
                [...$row, 'updated_at' => now(), 'created_at' => now()],
            );
            $written++;
        }

        return $written;
    }

    /** @return array{option_id: ?int, is_checked: ?int, value: ?string}|null */
    private function voltageRow(object $attribute, bool $singlePhase): ?array
    {
        if ($attribute->type === 'value') {
            return ['option_id' => null, 'is_checked' => null, 'value' => $singlePhase ? '230/400' : '400'];
        }
        $options = DB::table('attribute_options')->where('attribute_id', $attribute->id)->get(['id', 'name']);
        $match = $options->first(function (object $option) use ($singlePhase): bool {
            $name = mb_strtolower($option->name);

            return $singlePhase
                ? str_contains($name, 'одно') || str_contains($name, '220') || str_contains($name, '230')
                : str_contains($name, 'трехфаз') || str_contains($name, 'трёхфаз') || str_contains($name, '380') || str_contains($name, '400');
        });

        return $match ? ['option_id' => (int) $match->id, 'is_checked' => null, 'value' => null] : null;
    }
}
