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
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
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
