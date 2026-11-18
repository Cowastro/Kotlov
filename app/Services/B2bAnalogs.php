<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What to offer instead of an item that is out of stock or on order, in any section of the catalog: an item of the
 * same category that does the same job — the same power, volume, size — of another series, brand or supplier.
 *
 * Must match: the category; for chimney parts also the element and the diameter. Main values written in the name
 * (кВт, л, Вт, м³/ч, бар) may differ by 30 % at most. The closest first: equal attribute values of the cards
 * (marked «in filter»), steel, thickness, angle; then what is in stock, then the nearest price.
 */
class B2bAnalogs
{
    private const LIMIT = 8;

    private const POOL = 2000;

    /** Units that define what an item is for: another value is another item. */
    private const MAIN_UNITS = ['квт' => 'кВт', 'вт' => 'Вт', 'л' => 'л', 'м3/ч' => 'м³/ч', 'бар' => 'бар', 'м2' => 'м²', 'секц' => 'секций'];

    /** Attributes of the cards that, like the units, say what the item is for: power, volume, area, head, sections. */
    private const MAIN_ATTRIBUTES = '/мощност|объ[её]м|площад|напор|секци/u';

    /** Words that name the same chimney element. */
    private const TYPE_SYNONYMS = ['колено' => 'отвод', 'шибер-задвижка' => 'шибер', 'задвижка' => 'шибер', 'сборник' => 'конденсатоотвод'];

    public function __construct(private ChimneyDiameterQuickFilter $names, private B2bCharacteristicFilters $filters) {}

    /** @return list<array{product: Product, score: int, matches: list<string>, summary: ?array}> */
    public function for(Product $product, ?User $user, StaffCatalogWorkspace $workspace): array
    {
        $candidates = Product::query()->active()->notArchived()->whereKeyNot($product->id)
            ->whereIn('category_id', $this->categories($product))->with(['brand:id,name', 'category:id,slug'])
            ->limit(self::POOL)->get(['id', 'name', 'slug', 'sku', 'price', 'brand_id', 'category_id', 'specs']);
        $attributes = $this->attributes($candidates->pluck('id')->push($product->id));
        $source = $this->traits($product, $attributes->get($product->id, []));

        $rows = $candidates->map(function (Product $candidate) use ($source, $attributes): ?array {
            $traits = $this->traits($candidate, $attributes->get($candidate->id, []));

            return $this->compare($source, $traits) + ['product' => $candidate];
        })->filter(fn (array $row): bool => $row['score'] !== null)->values();

        $procurement = StaffCatalogWorkspace::canViewProcurement($user);
        $rows = $rows->sortByDesc('score')->take(self::LIMIT * 3)->map(function (array $row) use ($procurement, $workspace, $user): array {
            $row['summary'] = $procurement ? $workspace->searchSummary($row['product'], $user) : null;

            return $row;
        });
        // Stock weighs like a close characteristic, not more: a weaker item in stock does not beat a close one.
        $rank = fn (array $row): int => $row['score'] + match ($row['summary']['stock_state'] ?? null) {
            'known' => 3, 'available' => 2, 'empty' => -2, default => 0
        };
        $price = (float) $product->price;

        return $rows->sort(fn (array $a, array $b): int => [-$rank($a), abs((float) $a['product']->price - $price)]
            <=> [-$rank($b), abs((float) $b['product']->price - $price)])
            ->take(self::LIMIT)->values()->all();
    }

    /** @return array{score: ?int, matches: list<string>} null score: not an analog */
    public function compare(array $source, array $traits): array
    {
        $none = ['score' => null, 'matches' => []];
        // A chimney part: the same element of the same diameter.
        if ($source['mono'] !== null || $source['pair'] !== null) {
            if ($traits['type'] !== $source['type'] || $traits['mono'] !== $source['mono'] || $traits['pair'] !== $source['pair']) {
                return $none;
            }
        }
        $score = 0;
        $matches = [];
        foreach ($source['units'] as $unit => $value) {
            if (! isset($traits['units'][$unit])) {
                // Its size is not known: below the items of the same size.
                $score -= 2;

                continue;
            }
            $other = $traits['units'][$unit];
            $difference = abs($other - $value) / max($value, $other, 0.0001);
            if ($difference > 0.3) {
                return $none;
            }
            $score += $difference < 0.001 ? 4 : 1;
            $matches[] = self::MAIN_UNITS[$unit].($difference < 0.001 ? '' : ' ≈');
        }
        foreach (['steel' => ['сталь', 3], 'thickness' => ['толщина', 2], 'angle' => ['угол', 3], 'coating' => ['покрытие', 1], 'series' => ['серия', 1]] as $trait => [$label, $weight]) {
            if ($source[$trait] === null || $traits[$trait] === null) {
                continue;
            }
            if ($source[$trait] === $traits[$trait]) {
                $score += $weight;
                $matches[] = $label;
            } else {
                $score -= $trait === 'angle' ? 3 : 1;
            }
        }
        foreach ($source['attributes'] as $name => $value) {
            $main = preg_match(self::MAIN_ATTRIBUTES, $name) === 1;
            if (! isset($traits['attributes'][$name])) {
                $score -= $main ? 2 : 0;

                continue;
            }
            $other = $traits['attributes'][$name];
            [$number, $otherNumber] = [$this->number($value), $this->number($other)];
            if ($number !== null && $otherNumber !== null) {
                // «100», «100 л», «0,1 м3» of a volume are numbers, not words.
                $difference = abs($otherNumber - $number) / max($number, $otherNumber, 0.0001);
                if ($main && $difference > 0.3) {
                    return $none;
                }
                $score += $difference < 0.001 ? ($main ? 4 : 2) : ($difference <= 0.3 ? 1 : -1);
                if ($difference <= 0.3) {
                    $matches[] = $name.($difference < 0.001 ? '' : ' ≈');
                }

                continue;
            }
            if ($other === $value) {
                $score += 2;
                $matches[] = $name;
            } else {
                $score -= 1;
            }
        }

        return ['score' => $score, 'matches' => array_values(array_unique($matches))];
    }

    public function traits(Product $product, array $attributes = []): array
    {
        $name = (string) $product->name;
        $first = mb_strtolower((string) (preg_split('/[\s,]+/u', trim($name))[0] ?? ''));
        $coated = preg_match('/MC\s*Black|чёрн|черн|эмал/ui', $name) === 1;

        return [
            'type' => self::TYPE_SYNONYMS[$first] ?? $first,
            'mono' => $this->names->monoDiameter($name),
            'pair' => $this->names->diameterPair($name),
            'steel' => $this->names->steelGrade($name, (array) ($product->specs ?? [])),
            'thickness' => $this->filters->thickness($name),
            'angle' => $this->filters->angle($name),
            'coating' => $this->names->monoDiameter($name) !== null || $this->names->diameterPair($name) !== null ? ($coated ? 'black' : 'bare') : null,
            'series' => preg_match('/Стандарт\s*(\d+)/u', $name, $match) === 1 ? 'standard'.$match[1] : null,
            'units' => $this->units($name),
            'attributes' => $attributes,
        ];
    }

    /** «Котёл 25 кВт», «Бойлер 100 л», «2000 Вт»: the first value of each main unit. @return array<string, float> */
    public function units(string $name): array
    {
        $units = [];
        preg_match_all('/(?<![\d.,\/])(\d+(?:[.,]\d+)?)\s*(квт|kw|вт|w|л|l|м3\/ч|м³\/ч|бар|bar|м2|м²|секц[\p{L}.]*)(?![\p{L}\d])/iu', $name, $found, PREG_SET_ORDER);
        foreach ($found as [, $number, $unit]) {
            $unit = str_starts_with(mb_strtolower($unit), 'секц') ? 'секц' : $unit;
            $unit = match (mb_strtolower($unit)) {
                'kw' => 'квт', 'w' => 'вт', 'l' => 'л', 'м³/ч' => 'м3/ч', 'bar' => 'бар', 'м²' => 'м2', default => mb_strtolower($unit)
            };
            $units[$unit] ??= (float) str_replace(',', '.', $number);
        }

        return array_filter($units, fn (float $value): bool => $value > 0);
    }

    /** «100», «100 л», «2,5 кВт» → the number; a value with two numbers («250/310») or none is a word. */
    private function number(string $value): ?float
    {
        if (preg_match_all('/\d+(?:[.,]\d+)?/u', $value, $found) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $found[0][0]);
    }

    /** Select attributes «in filter» of the cards: normalized name → normalized value. @return Collection<int, array<string, string>> */
    private function attributes(Collection $ids): Collection
    {
        return ProductAttributeValue::query()->whereIn('product_attribute_values.product_id', $ids)
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->leftJoin('attribute_options', 'attribute_options.id', '=', 'product_attribute_values.option_id')
            // Filters, and what the card shows: power or volume is often a number, not an option.
            ->where(fn ($query) => $query->where('attributes.in_filter', true)->orWhere('attributes.in_product', true))
            ->whereIn('attributes.type', ['select', 'value'])
            ->get(['product_attribute_values.product_id', 'attributes.name as attribute', 'attribute_options.name as option', 'product_attribute_values.value'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows): array => $rows->mapWithKeys(fn ($row) => [$this->normal((string) $row->attribute) => $this->normal((string) ($row->option ?? $row->value))])
                ->reject(fn (string $value, string $name) => $name === '' || $value === '' || $value === '—' || preg_match('/^(производитель|бренд|торговая марка|страна.*|гарантия.*)$/u', $name) === 1)->all());
    }

    /** The category of the item; with few items there, its sister categories too. @return list<int> */
    private function categories(Product $product): array
    {
        $ids = [(int) $product->category_id];
        $parent = (int) (Category::query()->whereKey($product->category_id)->value('parent_id') ?? 0);
        if ($parent > 0 && Product::query()->active()->notArchived()->where('category_id', $product->category_id)->count() < 20) {
            $ids = array_merge($ids, Category::query()->where('parent_id', $parent)->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }

    private function normal(string $value): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? ''), ' ,');
    }
}
