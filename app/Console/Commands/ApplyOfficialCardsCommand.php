<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills cards from a prepared official dataset (resources/data/official/{dataset}.json): manufacturer photos,
 * characteristics, description, short description and meta, plus the numeric catalogue filters (power, heated area,
 * weight) when the card's category has them. An item without photos keeps the card's gallery; manually uploaded
 * photos (manual-fix) are always kept after the official ones. Prices and stock are not touched.
 *
 *   php artisan catalog:apply-official-cards sten-2026-11            # dry run
 *   php artisan catalog:apply-official-cards sten-2026-11 --apply
 */
class ApplyOfficialCardsCommand extends Command
{
    protected $signature = 'catalog:apply-official-cards {dataset : File name in resources/data/official without .json} {--apply : Write the data}';

    protected $description = 'Fill product cards from an official manufacturer dataset';

    public function handle(): int
    {
        $dataset = (string) $this->argument('dataset');
        if (! preg_match('/^[a-z0-9-]+$/', $dataset) || ! is_file($path = resource_path("data/official/{$dataset}.json"))) {
            $this->error('Набор данных не найден.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $items = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $stats = ['items' => count($items), 'found' => 0, 'missing' => 0, 'updated' => 0, 'with_photos' => 0, 'attribute_values' => 0];

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
            $product = $products->get($item['slug']);
            if (! $product) {
                $stats['missing']++;
                $this->line('audit.official_cards.missing='.$item['slug']);

                continue;
            }
            $stats['found']++;
            $stats['with_photos'] += $item['images'] !== [] ? 1 : 0;
            $before = count($product->imagePaths());
            $images = $item['images'] !== []
                ? array_values(array_unique([...$item['images'], ...array_filter($product->imagePaths(), fn (string $p): bool => str_contains($p, 'manual'))]))
                : $product->imagePaths();

            if ($apply) {
                DB::transaction(function () use ($product, $item, $images, &$stats): void {
                    $product->forceFill([
                        'images' => $images,
                        'specs' => array_map(fn (array $spec): array => ['key' => $spec[0], 'value' => $spec[1]], $item['specs']),
                        'short_description' => $item['short'],
                        'content' => $item['content'],
                        'meta_title' => $item['meta_title'],
                        'meta_description' => $item['meta_description'],
                        'content_source_url' => $item['source_url'],
                    ])->save();
                    $stats['attribute_values'] += $this->syncNumericFilters($product, $item['specs']);
                });
                $stats['updated']++;
            }
            $this->line(sprintf('audit.official_cards.card=%d:%s:%d:%d', $product->id, $item['slug'], $before, count($images)));
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.official_cards.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /** Power (kW), heated area (m²), weight (kg) into the category's numeric filters of the same name. */
    private function syncNumericFilters(Product $product, array $specs): int
    {
        $find = function (array $prefixes) use ($specs): ?string {
            foreach ($specs as [$key, $value]) {
                $key = mb_strtolower($key);
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix) && preg_match('/\d+(?:[.,]\d+)?/', (string) $value, $m)) {
                        $number = (float) str_replace(',', '.', $m[0]);
                        if (preg_match('/(^|[^к])вт/u', $key.' '.mb_strtolower(trim((string) $value))) === 1) {
                            $number /= 1000;
                        }

                        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
                    }
                }
            }

            return null;
        };
        $values = [
            'мощность' => $find(['номинальная мощность', 'номинальная коммутируемая мощность', 'мощность, квт', 'мощность']),
            'площадь обогрева' => $find(['отапливаемая площадь']),
            'вес' => $find(['масса', 'вес']),
        ];

        $written = 0;
        foreach (DB::table('attributes')->where('category_id', $product->category_id)->where('type', 'value')->get(['id', 'name', 'suffix']) as $attribute) {
            $name = mb_strtolower(trim($attribute->name));
            $key = collect(array_keys($values))->first(fn (string $k): bool => str_starts_with($name, $k));
            if ($key === null || $values[$key] === null) {
                continue;
            }
            $value = $values[$key];
            if ($key === 'мощность' && mb_strtolower(trim((string) $attribute->suffix)) === 'вт') {
                $value = (string) round((float) $value * 1000);
            }
            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $product->id, 'attribute_id' => $attribute->id],
                ['option_id' => null, 'is_checked' => null, 'value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
            $written++;
        }

        return $written;
    }
}
