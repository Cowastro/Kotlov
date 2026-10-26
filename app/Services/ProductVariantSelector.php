<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

class ProductVariantSelector
{
    public function __construct(private readonly MembraneTankVolumeNormalizer $volumeNormalizer) {}

    /**
     * Build a conservative storefront selector for products that are the same
     * model and differ only by tank capacity.
     */
    public function forProduct(Product $product): ?array
    {
        if ($product->variant_selector_excluded) {
            return null;
        }

        return $this->build($product, false);
    }

    /** Same grouping as the storefront, including disabled/excluded cards for audit. */
    public function forAdmin(Product $product): ?array
    {
        return $this->build($product, true);
    }

    private function build(Product $product, bool $forAdmin): ?array
    {
        if ($this->isTisStandard($product)) {
            return $this->buildTisStandard($product, $forAdmin);
        }

        if ($this->isPackagedFluid($product)) {
            return $this->buildPackagedFluid($product, $forAdmin);
        }

        if (in_array($product->category?->slug, self::CHIMNEY_CATEGORIES, true) && self::chimneyDiameter((string) $product->name) !== null) {
            return $this->buildChimneyDiameter($product, $forAdmin);
        }

        if ($product->category?->slug !== 'membrannye-baki') {
            return null;
        }

        $product->loadMissing('brand', 'allAttributeValues.attribute', 'allAttributeValues.option');
        $currentVolume = $this->volume($product);
        $familyKey = $currentVolume === null ? null : $this->familyKey($product, $currentVolume);

        if ($familyKey === null) {
            return null;
        }

        $variants = Product::query()
            ->where('category_id', $product->category_id)
            ->where('brand_id', $product->brand_id)
            ->when(! $forAdmin, fn ($query) => $query
                ->where('variant_selector_excluded', false)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('price', '>', 0))
            ->withSupplierChannels()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'allAttributeValues.attribute', 'allAttributeValues.option'])
            ->get()
            ->map(function (Product $candidate) use ($familyKey, $product): ?array {
                $volume = $this->volume($candidate);

                if ($volume === null || $this->familyKey($candidate, $volume) !== $familyKey) {
                    return null;
                }

                return [
                    'product' => $candidate,
                    'value' => $volume,
                    'label' => $volume.' л',
                    'url' => url('/'.$candidate->category->slug.'/'.$candidate->slug),
                    'availability_status' => $candidate->publicAvailabilityStatus(),
                    'availability_label' => $candidate->publicAvailabilityLabel(),
                    'is_current' => $candidate->is($product),
                    'is_excluded' => (bool) $candidate->variant_selector_excluded,
                ];
            })
            ->filter()
            ->sortBy('value')
            ->unique('value')
            ->values();

        if ($variants->count() < 2) {
            return null;
        }

        return [
            'type' => 'volume',
            'title' => 'Выберите объём:',
            'variants' => $variants,
        ];
    }

    private function isTisStandard(Product $product): bool
    {
        return in_array(data_get($product->specs, 'Серия'), [
            'ТиС «Стандарт 30»',
            'ТиС «Стандарт 50»',
        ], true)
            && filled(data_get($product->specs, 'Тип элемента'))
            && filled(data_get($product->specs, 'Диаметр'));
    }

    private function buildTisStandard(Product $product, bool $forAdmin): ?array
    {
        $product->loadMissing('brand');
        $series = trim((string) data_get($product->specs, 'Серия'));
        $execution = trim((string) data_get($product->specs, 'Исполнение'));
        $element = trim((string) data_get($product->specs, 'Тип элемента'));

        if ($series === '' || $execution === '' || $element === '') {
            return null;
        }

        $articlePrefixes = $execution === 'Моно'
            ? ['TIS30-%', 'TIS50-%']
            : [$series === 'ТиС «Стандарт 30»' ? 'TIS30-%' : 'TIS50-%'];

        $variants = Product::query()
            ->where('brand_id', $product->brand_id)
            ->where(function ($query) use ($articlePrefixes): void {
                foreach ($articlePrefixes as $index => $prefix) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('sku', 'like', $prefix)
                        ->orWhereHas('supplierProducts', fn ($supplierProducts) => $supplierProducts
                            ->where('supplier_article', 'like', $prefix));
                }
            })
            ->when(! $forAdmin, fn ($query) => $query
                ->where('variant_selector_excluded', false)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('price', '>', 0))
            ->withSupplierChannels()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->get()
            ->map(function (Product $candidate) use ($series, $execution, $element, $product): ?array {
                $specs = $candidate->specs ?: [];
                $candidateSeries = trim((string) ($specs['Серия'] ?? ''));
                $seriesMatches = $execution === 'Моно'
                    ? in_array($candidateSeries, ['ТиС «Стандарт 30»', 'ТиС «Стандарт 50»'], true)
                    : $candidateSeries === $series;

                if (! $seriesMatches
                    || trim((string) ($specs['Исполнение'] ?? '')) !== $execution
                    || trim((string) ($specs['Тип элемента'] ?? '')) !== $element) {
                    return null;
                }

                $diameter = trim((string) ($specs['Диаметр'] ?? ''));
                if ($diameter === '') {
                    return null;
                }

                return [
                    'product' => $candidate,
                    'value' => $diameter,
                    'sort' => collect(explode('/', $diameter))->map(fn ($value) => (int) $value)->all(),
                    'label' => 'Ø'.$diameter.' мм',
                    'url' => url('/'.$candidate->category->slug.'/'.$candidate->slug),
                    'availability_status' => $candidate->publicAvailabilityStatus(),
                    'availability_label' => $candidate->publicAvailabilityLabel(),
                    'is_current' => $candidate->is($product),
                    'is_excluded' => (bool) $candidate->variant_selector_excluded,
                ];
            })
            ->filter()
            ->sortBy('sort')
            ->unique('value')
            ->values();

        if ($variants->count() < 2) {
            return null;
        }

        return [
            'type' => 'diameter',
            'title' => 'Выберите диаметр:',
            'variants' => $variants,
        ];
    }

    /** Chimney part categories where one element comes in a row of diameters. */
    private const CHIMNEY_CATEGORIES = [
        'truby-mono', 'truby-sendvich', 'troyniki-mono', 'troyniki-sendvich', 'kolena-mono', 'kolena-sendvich',
        'perehody-adaptery-dymohod', 'kondensatootvody', 'zaglushki-dymohod', 'zonty-deflektory', 'shibery-dymohod',
        'krepleniya-dymohod', 'dymohody-nerzhaveyushchie', 'prochie-dymohod',
    ];

    /**
     * «D150», «Ø150», «ф150», «D150/210», «D 130-150»: the diameter(s) of a chimney part, as a label.
     *
     * @return array{label:string, sort:array<int,int>, numbers:array<int,string>}|null
     */
    public static function chimneyDiameter(string $name): ?array
    {
        if (! preg_match('/(?:(?<![\pL])[DdДдØøФф]|диаметр)\s*\.?\s*(\d{2,3})(?:\s*[\/\-–]\s*(\d{2,3}))?(?!\d)/u', $name, $m)) {
            return null;
        }
        $numbers = array_values(array_filter([$m[1], $m[2] ?? null]));

        return ['label' => 'Ø'.implode('/', $numbers), 'sort' => array_map('intval', $numbers), 'numbers' => $numbers];
    }

    /**
     * The element without its diameter: «Труба L1000 ТМ-Р 430-0.8 D150» and «Труба … Моно ТМ-Р L1000 430-0.8 Ø150»
     * give one family, any other number (length, steel, thickness, angle) or the paint keeps families apart.
     */
    public static function chimneyFamily(string $name): ?string
    {
        $diameter = self::chimneyDiameter($name);
        $signature = \App\Console\Commands\LinkOneCByParametersCommand::signature($name);
        if ($diameter === null || $signature === '') {
            return null;
        }
        $parts = explode('|', $signature);
        foreach ($diameter['numbers'] as $number) {
            $index = array_search($number, $parts, true);
            if ($index !== false && $index > 0) {
                unset($parts[$index]);
            }
        }
        $painted = preg_match('/mc\s*black|ч[её]рн/iu', $name) ? 'painted' : 'bare';

        return implode('|', $parts).'|'.$painted;
    }

    private function buildChimneyDiameter(Product $product, bool $forAdmin): ?array
    {
        $family = self::chimneyFamily((string) $product->name);
        if ($family === null) {
            return null;
        }

        // One maker: the same brand, or «Теплов и Сухов / ТИС» in both names (old and new cards differ in brand records).
        $tis = fn (string $name) => (bool) preg_match('/теплов|(?<!\pL)тис(?!\pL)/iu', $name);
        $variants = Product::query()
            ->where('category_id', $product->category_id)
            ->when(! $forAdmin, fn ($query) => $query
                ->where('variant_selector_excluded', false)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('price', '>', 0))
            ->withSupplierChannels()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->get()
            ->map(function (Product $candidate) use ($family, $product, $tis): ?array {
                $sameMaker = $tis((string) $product->name)
                    ? $tis((string) $candidate->name)
                    : (int) $candidate->brand_id === (int) $product->brand_id && ! $tis((string) $candidate->name);
                $diameter = self::chimneyDiameter((string) $candidate->name);
                if (! $sameMaker || $diameter === null || self::chimneyFamily((string) $candidate->name) !== $family) {
                    return null;
                }

                return [
                    'product' => $candidate,
                    'value' => $diameter['label'],
                    'sort' => $diameter['sort'],
                    'label' => $diameter['label'],
                    'url' => url('/'.$candidate->category->slug.'/'.$candidate->slug),
                    'availability_status' => $candidate->publicAvailabilityStatus(),
                    'availability_label' => $candidate->publicAvailabilityLabel(),
                    'is_current' => $candidate->is($product),
                    'is_excluded' => (bool) $candidate->variant_selector_excluded,
                ];
            })
            ->filter()
            // The open card first among equal diameters, so it is the one that stays.
            ->sortBy(fn (array $variant) => [$variant['sort'], $variant['is_current'] ? 0 : 1])
            ->unique('value')
            ->values();

        if ($variants->count() < 2 || ! $variants->contains('is_current', true)) {
            return null;
        }

        return [
            'type' => 'diameter',
            'title' => 'Выберите диаметр:',
            'variants' => $variants,
        ];
    }

    /**
     * Heat-transfer fluids sold in several canister sizes of one exact model
     * (e.g. «ZUBR -30» 10 кг / 20 кг) carry «Модель» and «Фасовка» specs.
     */
    private function isPackagedFluid(Product $product): bool
    {
        return $product->category?->slug === 'teplonositeli'
            && $this->specValue($product, 'Модель') !== ''
            && $this->packageKilograms($this->specValue($product, 'Фасовка')) !== null;
    }

    private function buildPackagedFluid(Product $product, bool $forAdmin): ?array
    {
        $model = $this->specValue($product, 'Модель');

        $variants = Product::query()
            ->where('category_id', $product->category_id)
            ->where('brand_id', $product->brand_id)
            ->when(! $forAdmin, fn ($query) => $query
                ->where('variant_selector_excluded', false)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('price', '>', 0))
            ->withSupplierChannels()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->get()
            ->map(function (Product $candidate) use ($model, $product): ?array {
                if ($this->specValue($candidate, 'Модель') !== $model) {
                    return null;
                }

                $kilograms = $this->packageKilograms($this->specValue($candidate, 'Фасовка'));
                if ($kilograms === null) {
                    return null;
                }

                return [
                    'product' => $candidate,
                    'value' => $kilograms,
                    'label' => rtrim(rtrim(number_format($kilograms, 1, ',', ''), '0'), ',').' кг',
                    'url' => url('/'.$candidate->category->slug.'/'.$candidate->slug),
                    'availability_status' => $candidate->publicAvailabilityStatus(),
                    'availability_label' => $candidate->publicAvailabilityLabel(),
                    'is_current' => $candidate->is($product),
                    'is_excluded' => (bool) $candidate->variant_selector_excluded,
                ];
            })
            ->filter()
            ->sortBy('value')
            ->unique('value')
            ->values();

        if ($variants->count() < 2) {
            return null;
        }

        return [
            'type' => 'package',
            'title' => 'Выберите фасовку:',
            'variants' => $variants,
        ];
    }

    private function specValue(Product $product, string $key): string
    {
        foreach ($product->specs ?: [] as $specKey => $spec) {
            if (is_array($spec) && trim((string) ($spec['key'] ?? '')) === $key) {
                return trim((string) ($spec['value'] ?? ''));
            }
            if (is_string($specKey) && trim($specKey) === $key && is_scalar($spec)) {
                return trim((string) $spec);
            }
        }

        return '';
    }

    private function packageKilograms(string $value): ?float
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*кг(?!\pL)/u', $value, $match)) {
            return null;
        }

        $kilograms = (float) str_replace(',', '.', $match[1]);

        return $kilograms > 0 ? $kilograms : null;
    }

    public function familyKey(Product $product, int $volume): ?string
    {
        $name = mb_strtolower(html_entity_decode(strip_tags((string) $product->name)));
        $name = str_replace('ё', 'е', $name);

        $brand = mb_strtolower(trim((string) $product->brand?->name));
        if ($brand !== '') {
            $name = preg_replace('/(?<![\pL\pN])'.preg_quote($brand, '/').'(?![\pL\pN])/ui', ' ', $name);
        }

        $quotedVolume = preg_quote((string) $volume, '/');
        $name = preg_replace('/(?<![\d.,\/])'.$quotedVolume.'(?:[.,]0+)?\s*(?:л(?:итр(?:а|ов)?)?|l)\b/ui', ' ', $name);
        $name = preg_replace('/(?<![\d.,\/])'.$quotedVolume.'(?![\d.,\/])/u', ' ', $name);
        $name = preg_replace('/\b(?:объем|объём)\s*:?[ ]*(?:л(?:итр(?:а|ов)?)?)?\b/ui', ' ', $name);
        $name = preg_replace('/[^\pL\pN]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return mb_strlen($name) >= 4 ? $name : null;
    }

    private function volume(Product $product): ?int
    {
        /** @var Collection<int, ProductAttributeValue> $values */
        $values = $product->allAttributeValues;
        $attributes = $values
            ->filter(fn (ProductAttributeValue $row) => $row->attribute !== null)
            ->mapWithKeys(fn (ProductAttributeValue $row) => [
                $row->attribute->name => $row->option?->name ?? $row->value,
            ])
            ->all();

        return $this->volumeNormalizer->extract($product->name, $product->specs ?: [], $attributes);
    }
}
