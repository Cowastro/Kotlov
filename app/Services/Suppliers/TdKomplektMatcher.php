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

    public function __construct()
    {
        Product::with('brand:id,name')->where('is_archived', false)->select(['id', 'sku', 'name', 'brand_id', 'specs'])->chunkById(500, function ($products) {
            foreach ($products as $p) {
                $this->products[$p->id] = TdKomplektFeed::key($p->brand?->name ?? '');
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
        $reason = 'unmatched';
        foreach (['barcode' => 'barcode', 'vendor_code' => 'article', 'name' => 'name'] as $field => $type) {
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
            $ids = array_values(array_filter($index[$key] ?? [], function ($id) use ($row, $type) {
                $brand = $this->products[$id];
                $expected = TdKomplektFeed::key($row['brand']);

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

        if ($matches === [] && $unconfirmed !== []) {
            return ['product_id' => null, 'candidates' => $unconfirmed, 'reason' => 'brand_unconfirmed'];
        }

        return ['product_id' => count($matches) === 1 ? $matches[0] : null, 'candidates' => $matches, 'reason' => count($matches) > 1 ? 'ambiguous' : $reason];
    }
}
