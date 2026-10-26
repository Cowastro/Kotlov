<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts the Maitek cards into the catalogue filters that already exist and gives the
 * INNOWARM heaters their specs from the title.
 *
 * 1. INNOWARM cards without specs: series, power, voltage, length, colour, mounting
 *    and thread are read from titles like «HP015 ТЭН для полотенцесушителя 300W/230V/L:350mm, Хром».
 * 2. Every Maitek card in a category with select filters gets the options it lacks:
 *    «Производитель» (an option is added for a new brand), «Мощность» and «Обогреваемая
 *    площадь» — either ranges («до 6 кВт», «6-12 кВт», «свыше 24 кВт») or exact values
 *    («1.5»), read from the options themselves. Values a card already has stay.
 *
 *   php artisan catalog:maitek-filters            # dry run
 *   php artisan catalog:maitek-filters --apply
 */
class ApplyMaitekCatalogFiltersCommand extends Command
{
    protected $signature = 'catalog:maitek-filters {--apply : Write specs and filter values}';

    protected $description = 'Fill INNOWARM specs from titles and put Maitek cards into the catalogue filters';

    /** @var array<string,int> options created (or planned in a dry run) in this run */
    private array $planned = [];

    /** @var array<string,int> */
    private array $stats = ['cards' => 0, 'specs_parsed' => 0, 'specs_unknown' => 0, 'brand_set' => 0, 'power_set' => 0, 'area_set' => 0, 'options_created' => 0, 'no_filter' => 0];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        // The command object may be reused between runs (tests, Artisan::call).
        $this->planned = [];
        $this->stats = array_fill_keys(array_keys($this->stats), 0);
        $supplierId = DB::table('suppliers')->where('code', 'maitek-group')->value('id');
        $products = Product::query()
            ->whereIn('id', DB::table('supplier_products')->where('supplier_id', $supplierId)->whereNotNull('product_id')->select('product_id'))
            ->where('is_archived', false)
            ->with('brand:id,name,slug')
            ->get();

        foreach ($products as $product) {
            $this->stats['cards']++;
            if ($product->brand?->slug === 'innowarm' && empty($product->specs)) {
                $specs = self::innowarmSpecs((string) $product->name);
                if ($specs === []) {
                    $this->stats['specs_unknown']++;
                    $this->line("audit.maitek_filters.unknown={$product->id}:{$product->slug}");
                } else {
                    $this->stats['specs_parsed']++;
                    if ($apply) {
                        $product->forceFill(['specs' => collect($specs)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all()])->save();
                    }
                }
            }
            $this->fillFilters($product, $apply);
        }

        foreach ($this->stats as $key => $value) {
            $this->line("metric.maitek_filters.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /** @return array<string,string> spec name => value with unit */
    public static function innowarmSpecs(string $name): array
    {
        $specs = [];
        if (preg_match('/\b(HP0\d\d)\b/u', $name, $m)) {
            $specs['Серия'] = $m[1];
        }
        if (($kw = self::powerKw($name)) !== null) {
            $specs['Мощность'] = $kw < 1 ? round($kw * 1000).' Вт' : self::number($kw).' кВт';
        }
        if (preg_match('/(\d{3})\s*(?:V\b|В(?!т))/u', $name, $m)) {
            $specs['Напряжение'] = $m[1].' В';
        }
        if (preg_match('/L\s*[:=]\s*(\d+)/u', $name, $m)) {
            $specs['Длина'] = $m[1].' мм';
        }
        if (preg_match('/,\s*(Белый|Хром|Черный)\s*$/u', $name, $m)) {
            $specs['Цвет'] = $m[1];
        }
        foreach (['полотенцесушителя' => 'Полотенцесушитель', 'бойлера косвенного нагрева' => 'Бойлер косвенного нагрева', 'теплоаккумулятора' => 'Теплоаккумулятор, буферная ёмкость'] as $needle => $use) {
            if (mb_stripos($name, $needle) !== false) {
                $specs['Назначение'] = $use;
            }
        }
        if (preg_match('/(фланец|гайка)/u', $name, $m)) {
            $specs['Крепление'] = Str::ucfirst($m[1]);
        }
        if (preg_match('/гайка латунная\s+([\d ,\/]+)"/u', $name, $m)) {
            $specs['Резьба крепления'] = str_replace(',', ' ', trim($m[1])).'"';
        }
        if (preg_match('/\b(гнутый|прямой)\b/u', $name, $m)) {
            $specs['Форма'] = Str::ucfirst($m[1]);
        }
        if (preg_match('/под анод\s*[МM](\d+)/u', $name, $m)) {
            $specs['Посадка под анод'] = 'M'.$m[1];
        }
        if (mb_stripos($name, 'нерж') !== false) {
            $specs['Материал'] = 'Нержавеющая сталь';
        }
        if (preg_match('/АНОД\s+(\d+)\s*[ХXх×]\s*(\d+)\s*[МM](\d+)/u', $name, $m)) {
            $specs += ['Материал' => 'Магний', 'Диаметр' => $m[1].' мм', 'Длина' => $m[2].' мм', 'Резьба' => 'M'.$m[3]];
        }

        return $specs;
    }

    /** Power in kW from «300W», «3000 Вт», «1,5 кВт», «ЭВПМ-7.5», «ТЭНБ-П-4.8», «BE 9.5 кВт». */
    public static function powerKw(string $text): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*кВт/u', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(\d+)\s*(?:W\b|Вт)/u', $text, $m)) {
            return (int) $m[1] / 1000;
        }
        if (preg_match('/(?:ЭВПМ|ТЭНБ-П)-(\d+(?:[.,]\d+)?)/u', $text, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function fillFilters(Product $product, bool $apply): void
    {
        $filters = DB::table('attributes')->where('category_id', $product->category_id)->where('in_filter', true)->where('type', 'select')->get(['id', 'name']);
        if ($filters->isEmpty()) {
            $this->stats['no_filter']++;

            return;
        }
        $specs = collect($product->specs ?? [])->mapWithKeys(fn ($spec) => [mb_strtolower((string) ($spec['key'] ?? '')) => (string) ($spec['value'] ?? '')]);
        $power = self::powerKw((string) ($specs['мощность обогрева'] ?? $specs['мощность'] ?? '')) ?? self::powerKw((string) $product->name);
        $area = preg_match('/(\d+(?:[.,]\d+)?)/u', (string) ($specs['площадь обогрева'] ?? ''), $m) ? (float) str_replace(',', '.', $m[1]) : null;
        $has = DB::table('product_attribute_values')->where('product_id', $product->id)->pluck('attribute_id')->flip();

        foreach ($filters as $filter) {
            if ($has->has($filter->id)) {
                continue;
            }
            $name = mb_strtolower((string) $filter->name);
            [$value, $stat] = match (true) {
                str_starts_with($name, 'производитель') => [$product->brand?->name, 'brand_set'],
                str_starts_with($name, 'мощность') => [$power, 'power_set'],
                str_contains($name, 'площадь') => [$area, 'area_set'],
                default => [null, null],
            };
            if ($value === null || $value === '') {
                continue;
            }
            $optionId = is_string($value) ? $this->brandOption((int) $filter->id, $value, $apply) : $this->numberOption((int) $filter->id, (float) $value, $apply);
            if ($optionId === null) {
                continue;
            }
            $this->stats[$stat]++;
            $this->line(sprintf('audit.maitek_filters.set=%d:%s:%d:%d', $product->id, $stat, $filter->id, $optionId));
            if ($apply) {
                DB::table('product_attribute_values')->insert([
                    'product_id' => $product->id, 'attribute_id' => $filter->id, 'option_id' => $optionId,
                    'is_checked' => null, 'value' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function brandOption(int $attributeId, string $brand, bool $apply): ?int
    {
        $key = Str::slug($brand);
        $option = $this->filterOptions($attributeId)->first(fn ($option) => Str::slug((string) $option->name) === $key);

        return $option ? (int) $option->id : $this->createOption($attributeId, $brand, $apply);
    }

    /** Range options («до 6 кВт», «6-12 кВт», «свыше 24 кВт») or exact values («1.5»). */
    private function numberOption(int $attributeId, float $value, bool $apply): ?int
    {
        $options = $this->filterOptions($attributeId);
        foreach ($options as $option) {
            // «до 90 м2»: the unit's «2» is not a bound.
            $label = preg_replace('/\s*(?:м2|м²|m2|кв\.?\s*м)/u', '', mb_strtolower(str_replace(',', '.', (string) $option->name))) ?? '';
            preg_match_all('/\d+(?:\.\d+)?/', $label, $numbers);
            $numbers = array_map('floatval', $numbers[0]);
            $fits = match (true) {
                $numbers === [] => false,
                str_contains($label, 'до') && count($numbers) === 1 => $value <= $numbers[0],
                str_contains($label, 'свыше') || str_contains($label, 'более') || str_contains($label, 'от') && count($numbers) === 1 => $value > $numbers[0],
                count($numbers) >= 2 => $value > $numbers[0] && $value <= $numbers[1],
                default => abs($value - $numbers[0]) < 0.001,
            };
            if ($fits) {
                return (int) $option->id;
            }
        }
        // Exact-value lists get the missing value in the same style («1.5» or «1,5»).
        $isRangeList = $options->contains(fn ($option) => preg_match('/до|свыше|более|-|—/u', (string) $option->name) === 1);
        if ($isRangeList || $options->isEmpty()) {
            return null;
        }
        $label = number_format($value, 1, '.', '');
        if ($options->contains(fn ($option) => str_contains((string) $option->name, ','))) {
            $label = str_replace('.', ',', $label);
        }

        return $this->createOption($attributeId, $label, $apply);
    }

    /** @return Collection<int,object> */
    private function filterOptions(int $attributeId): Collection
    {
        return DB::table('attribute_options')->where('attribute_id', $attributeId)->orderBy('sort_order')->get(['id', 'name']);
    }

    private function createOption(int $attributeId, string $name, bool $apply): ?int
    {
        // A dry run must not count one new option once per card.
        $key = $attributeId.'|'.$name;
        if (isset($this->planned[$key])) {
            return $this->planned[$key];
        }
        $this->stats['options_created']++;
        $this->line(sprintf('audit.maitek_filters.option=%d:%s', $attributeId, Str::slug(str_replace(['.', ','], '-', $name))));
        if (! $apply) {
            return $this->planned[$key] = -1;
        }

        return $this->planned[$key] = (int) DB::table('attribute_options')->insertGetId([
            'attribute_id' => $attributeId,
            'name' => $name,
            'sort_order' => (int) DB::table('attribute_options')->where('attribute_id', $attributeId)->max('sort_order') + 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
