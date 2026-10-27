<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Support\Suppliers\EcokaminChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EcokaminWarehouseStockImporter
{
    /** @return array{matched:int,unmatched:int,zeroed:int,unmatched_items:list<array{article:string,name:string}>} */
    public function importPayload(array $payload): array
    {
        // EcoKamin is supplied by СанБизнесГруп (code «sanbusinessgroup»).
        $supplierId = EcokaminChannel::supplierId();
        $supplier = $supplierId ? Supplier::query()->find($supplierId) : null;
        $brand = Brand::query()
            ->where('slug', 'ecokamin')
            ->orWhere('name', 'like', '%ЭкоКамин%')
            ->first();

        if (! $supplier || ! $brand) {
            throw new \RuntimeException('Не найден бренд ЭкоКамин или поставщик СанБизнесГруп.');
        }

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->with('supplierProducts:id,product_id,supplier_article,supplier_name')
            ->get();

        [$articleIndex, $nameIndex] = $this->indexes($products);
        $warehouseCode = (string) ($payload['warehouse_code'] ?? 'ecokamin-moscow');
        $warehouseName = (string) ($payload['warehouse_name'] ?? 'Склад Москва');
        $deliveryDays = max(0, (int) ($payload['delivery_days'] ?? 7));
        $asOfDate = $payload['as_of_date'] ?? null;
        $sourceFile = $payload['source_file'] ?? null;
        $matchedProductIds = [];
        $unmatched = [];

        DB::transaction(function () use (
            $payload, $supplier, $articleIndex, $nameIndex, $warehouseCode,
            $warehouseName, $deliveryDays, $asOfDate, $sourceFile,
            &$matchedProductIds, &$unmatched
        ): void {
            // Rows written for a retired duplicate supplier record must not double the stock.
            ProductWarehouseStock::query()
                ->where('warehouse_code', $warehouseCode)
                ->where('supplier_id', '!=', $supplier->id)
                ->delete();

            foreach ((array) ($payload['items'] ?? []) as $item) {
                $article = trim((string) ($item['article'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                $product = $this->uniqueMatch($articleIndex, $this->key($article))
                    ?? $this->uniqueMatch($nameIndex, $this->key($name));

                if (! $product) {
                    $unmatched[] = ['article' => $article, 'name' => $name];
                    continue;
                }

                $matchedProductIds[] = (int) $product->id;
                ProductWarehouseStock::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'warehouse_code' => $warehouseCode,
                    ],
                    [
                        'warehouse_name' => $warehouseName,
                        'external_article' => $article ?: null,
                        'source_product_name' => $name ?: null,
                        'total_quantity' => max(0, (float) ($item['total'] ?? 0)),
                        'reserved_quantity' => max(0, (float) ($item['reserved'] ?? 0)),
                        'shipping_quantity' => max(0, (float) ($item['shipping'] ?? 0)),
                        'available_quantity' => max(0, (float) ($item['available'] ?? 0)),
                        'delivery_days' => $deliveryDays,
                        'as_of_date' => $asOfDate,
                        'source_file' => $sourceFile,
                        'last_synced_at' => now(),
                        'raw' => $item,
                    ],
                );
            }
        });

        $matchedProductIds = array_values(array_unique($matchedProductIds));
        $zeroed = 0;
        if ($matchedProductIds !== []) {
            $zeroed = ProductWarehouseStock::query()
                ->where('supplier_id', $supplier->id)
                ->where('warehouse_code', $warehouseCode)
                ->whereNotIn('product_id', $matchedProductIds)
                ->update([
                    'total_quantity' => 0,
                    'reserved_quantity' => 0,
                    'shipping_quantity' => 0,
                    'available_quantity' => 0,
                    'as_of_date' => $asOfDate,
                    'source_file' => $sourceFile,
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        app(EcokaminB2bOfferPublisher::class)->sync();

        return [
            'matched' => count($matchedProductIds),
            'unmatched' => count($unmatched),
            'zeroed' => $zeroed,
            'unmatched_items' => $unmatched,
        ];
    }

    public function importJson(string $path): array
    {
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->importPayload($payload);
    }

    /** @return array{0:array<string,Collection<int,Product>>,1:array<string,Collection<int,Product>>} */
    private function indexes(Collection $products): array
    {
        $articles = [];
        $names = [];

        foreach ($products as $product) {
            foreach ([$product->sku, ...$product->supplierProducts->pluck('supplier_article')->all()] as $article) {
                if ($key = $this->key($article)) {
                    $articles[$key] = collect($articles[$key] ?? [])->push($product)->unique('id')->values();
                }
            }
            foreach ([$product->name, ...$product->supplierProducts->pluck('supplier_name')->all()] as $name) {
                if ($key = $this->key($name)) {
                    $names[$key] = collect($names[$key] ?? [])->push($product)->unique('id')->values();
                }
            }
        }

        return [$articles, $names];
    }

    private function uniqueMatch(array $index, string $key): ?Product
    {
        $matches = $key !== '' ? ($index[$key] ?? collect()) : collect();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function key(mixed $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = strtr($value, [
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T',
            'Х' => 'X', 'У' => 'Y',
        ]);

        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($value)) ?? '';
    }
}
