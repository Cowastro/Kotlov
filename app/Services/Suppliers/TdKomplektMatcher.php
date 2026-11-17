<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use App\Models\SupplierProduct;

class TdKomplektMatcher
{
    private array $articles = [];

    private array $barcodes = [];

    private array $names = [];

    private array $products = [];

    private array $tankModels = [];

    private array $categories = [];

    private array $titles = [];

    /** Manufacturer rebranding: https://www.alcadrain.com/company/news/1578-alcaplast-is-changing-its-logo-and-company-name-after-24-years */
    public static function brandKey(string $brand): string
    {
        $key = TdKomplektFeed::key($brand);

        return in_array($key, ['ALCA', 'ALCAPLAST', 'ALCADRAIN'], true) ? 'ALCA' : $key;
    }

    public function __construct()
    {
        Product::with(['brand:id,name', 'category:id,slug'])->where('is_archived', false)->select(['id', 'sku', 'name', 'brand_id', 'category_id', 'specs'])->chunkById(500, function ($products) {
            foreach ($products as $p) {
                $this->products[$p->id] = self::brandKey($p->brand?->name ?? '');
                $this->categories[$p->id] = $p->category?->slug;
                $this->titles[$p->id] = $p->name;
                if ($this->products[$p->id] === 'WESTER' && ($model = TdKomplektPumpCategories::tankModelKey($p->name))) {
                    $this->index($this->tankModels, $model, $p->id);
                }
                $this->index($this->articles, $p->sku, $p->id);
                $this->index($this->names, $p->name, $p->id);
                foreach ($p->specs ?? [] as $spec) {
                    $key = mb_strtolower(trim($spec['key'] ?? ''));
                    $value = (string) ($spec['value'] ?? '');
                    if (in_array($key, ['артикул производителя', 'заводской артикул', 'артикул', 'код производителя'], true)) {
                        $this->index($this->articles, $value, $p->id);
                    }
                    if (in_array($key, ['штрихкод', 'штрих-код', 'ean', 'ean13'], true) && preg_match('/^\d{8,14}$/D', $value)) {
                        $this->index($this->barcodes, $value, $p->id);
                    }
                }
            }
        });
        foreach (SupplierProduct::whereNotNull('product_id')->select(['product_id', 'supplier_article'])->cursor() as $offer) {
            if (isset($this->products[$offer->product_id])) {
                $this->index($this->articles, $offer->supplier_article, $offer->product_id);
            }
        }
    }

    private function index(array &$index, ?string $value, int $id): void
    {
        $key = TdKomplektFeed::key($value ?? '');
        if ($key !== '') {
            $index[$key][$id] = $id;
        }
    }

    public function match(array $row): array
    {
        $matches = [];
        $unconfirmed = [];
        $variants = [];
        $reason = 'unmatched';
        foreach (['barcode' => 'barcode', 'vendor_code' => 'article', 'name' => 'name'] as $field => $type) {
            if ($type === 'barcode' && ($row['_duplicate_barcode'] ?? false)) {
                continue;
            }
            if ($type === 'name' && ($row['_duplicate_name'] ?? false)) {
                continue;
            }
            $key = TdKomplektFeed::key($row[$field] ?? '');
            if ($key === '' || ($type === 'article' && mb_strlen($key) < 4)) {
                continue;
            }
            $index = match ($type) {
                'barcode' => $this->barcodes,'article' => $this->articles,default => $this->names
            };
            $ids = array_values(array_filter($index[$key] ?? [], function ($id) use ($row, $type, &$variants) {
                $brand = $this->products[$id];
                $expected = self::brandKey($row['brand']);
                if ($brand === 'ALCA' && $expected === 'ALCA' && $type === 'article') {
                    $article = preg_quote(trim($row['vendor_code']), '/');
                    if (preg_match('/(?<![\pL\pN-])'.$article.'-[\pL\pN]/iu', $this->titles[$id])
                        && ! preg_match('/(?<![\pL\pN-])'.$article.'(?![\pL\pN-])/iu', $this->titles[$id])) {
                        $variants[] = $id;

                        return false;
                    }
                }

                // A branded manufacturer's article must not join a different brand.
                return $expected !== '' && ($brand === $expected || ($brand === '' && $type === 'name' && str_contains(TdKomplektFeed::key($row['name']), $expected)));
            }));
            if ($ids === [] && ($index[$key] ?? []) !== []) {
                $unconfirmed = array_values(array_unique([...$unconfirmed, ...array_values($index[$key])]));
            }
            if ($ids !== []) {
                if ($matches !== [] && array_diff($ids, $matches) !== [] && array_intersect($ids, $matches) === []) {
                    return ['product_id' => null, 'candidates' => array_values(array_unique([...$matches, ...$ids])), 'reason' => 'conflicting_identifiers'];
                }
                $matches = $matches === [] ? $ids : array_values(array_intersect($matches, $ids));
                $reason = 'exact_'.$type;
            }
        }

        if (array_intersect($matches, $variants) !== []) {
            return ['product_id' => null, 'candidates' => $variants, 'reason' => 'variant_unconfirmed'];
        }
        if ($matches === [] && TdKomplektPumpCategories::applies($row)
            && TdKomplektFeed::key($row['brand']) === 'WESTER'
            && TdKomplektPumpCategories::category($row) === 'membrannye-baki'
            && ($model = TdKomplektPumpCategories::tankModelKey($row['name'], $row['vendor_code'] ?? ''))
            && ($ids = array_values($this->tankModels[$model] ?? [])) !== []) {
            return ['product_id' => count($ids) === 1 ? $ids[0] : null, 'candidates' => $ids, 'reason' => count($ids) === 1 ? 'exact_manufacturer_model' : 'ambiguous'];
        }
        if ($matches === [] && $unconfirmed !== []) {
            if ($variants !== []) {
                return ['product_id' => null, 'candidates' => $variants, 'reason' => 'variant_unconfirmed'];
            }
            $expected = self::brandKey($row['brand']);
            $category = (new TdKomplektFeed)->category($row);
            $distinct = $expected !== '' && $category !== null;
            foreach ($unconfirmed as $id) {
                $distinct = $distinct && ($this->products[$id] ?? '') !== '' && $this->products[$id] !== $expected
                    && ($this->categories[$id] ?? null) !== null && $this->categories[$id] !== $category;
            }
            if ($distinct) {
                return ['product_id' => null, 'candidates' => $unconfirmed, 'reason' => 'foreign_brand_article'];
            }

            return ['product_id' => null, 'candidates' => $unconfirmed, 'reason' => 'brand_unconfirmed'];
        }

        return ['product_id' => count($matches) === 1 ? $matches[0] : null, 'candidates' => $matches, 'reason' => count($matches) > 1 ? 'ambiguous' : $reason];
    }
}
