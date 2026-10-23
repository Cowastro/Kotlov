<?php

namespace App\Services\PriceList;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping;
use App\Services\Pricing\KarinaPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateReviewedKarinaProducts
{
    public const CREATED_VIA = 'karina_issue_34';

    public function run(array $report, bool $apply = false): array
    {
        if (($report['schema'] ?? '') !== 'karina-audit-v1'
            || ($report['source_currency'] ?? '') !== 'RUB'
            || ($report['currency_confirmed'] ?? false) !== true
            || ($report['applied'] ?? true) !== false) {
            throw new RuntimeException('A confirmed read-only RUB audit is required.');
        }

        $source = (string) ($report['workbook']['sha256'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $source)) {
            throw new RuntimeException('Workbook hash missing.');
        }

        return DB::transaction(function () use ($report, $apply, $source): array {
            $supplier = Supplier::query()->where('code', 'karina')->lockForUpdate()->first();
            if (! $supplier || $supplier->currency !== 'RUB' || ! $supplier->is_active) {
                throw new RuntimeException('The reviewed KARINA supplier is required.');
            }

            $filename = 'karina-reviewed-'.$source.'.xlsx';
            $import = SupplierPriceImport::query()
                ->where('supplier_id', $supplier->id)
                ->where('filename', $filename)
                ->lockForUpdate()
                ->first();
            if (! $import || $import->status !== 'done') {
                throw new RuntimeException('The reviewed KARINA price import is required.');
            }

            $candidates = collect($report['creation_candidates'] ?? [])
                ->filter(fn (array $row): bool => ($row['eligible_for_hidden_creation_review'] ?? false) === true)
                ->values();
            if ($candidates->isEmpty()) {
                $created = Product::query()->where('created_via', self::CREATED_VIA)->lockForUpdate()->count();

                return ['already_applied' => $created > 0, 'created' => 0, 'existing_batch_products' => $created];
            }

            $candidateArticles = $candidates->pluck('article')->all();
            $existingBatch = SupplierProductMapping::query()
                ->where('supplier_code', 'karina')
                ->whereIn('supplier_article', $candidateArticles)
                ->whereHas('product', fn ($query) => $query->where('created_via', self::CREATED_VIA))
                ->lockForUpdate()
                ->count();
            if ($existingBatch === $candidates->count()) {
                return ['already_applied' => true, 'created' => 0, 'existing_batch_products' => $existingBatch];
            }
            if ($existingBatch > 0) {
                throw new RuntimeException('Partial KARINA creation batch requires manual review.');
            }

            if ($candidates->pluck('article')->unique()->count() !== $candidates->count()
                || $candidates->pluck('name')->map(fn ($name) => mb_strtolower(trim((string) $name)))->unique()->count() !== $candidates->count()) {
                throw new RuntimeException('Creation plan contains duplicate articles or names.');
            }

            $itemsByArticle = collect($report['items'] ?? [])->keyBy('article');
            $templateProducts = collect($report['products'] ?? []);
            $brandIds = $templateProducts->pluck('brand_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $stoveCategoryIds = $templateProducts->filter(
                fn (array $row): bool => str_starts_with((string) $row['name'], 'Электрическая печь')
            )->pluck('category_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $accessoryCategoryIds = $templateProducts->reject(
                fn (array $row): bool => str_starts_with((string) $row['name'], 'Электрическая печь')
            )->pluck('category_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            if ($brandIds->count() !== 1 || $stoveCategoryIds->count() !== 1 || $accessoryCategoryIds->count() !== 1) {
                throw new RuntimeException('KARINA brand or category templates are ambiguous.');
            }
            $brandId = $brandIds->first();
            $stoveCategoryId = $stoveCategoryIds->first();
            $accessoryCategoryId = $accessoryCategoryIds->first();

            $articles = $candidates->pluck('article')->all();
            $names = $candidates->pluck('name')->all();
            if (Product::query()->whereIn('name', $names)->orWhereIn('sku', $articles)->lockForUpdate()->exists()) {
                throw new RuntimeException('Catalog changed: a planned product already exists.');
            }
            if (SupplierProduct::query()->whereIn('supplier_article', $articles)->whereNotNull('product_id')->lockForUpdate()->exists()
                || SupplierProductMapping::query()->whereIn('supplier_article', $articles)->whereNotNull('product_id')->lockForUpdate()->exists()) {
                throw new RuntimeException('Supplier references changed: a planned article is already linked.');
            }

            $priceItems = SupplierPriceItem::query()
                ->where('supplier_id', $supplier->id)
                ->where('import_id', $import->id)
                ->whereIn('article', $articles)
                ->lockForUpdate()
                ->get()
                ->keyBy('article');
            if ($priceItems->count() !== $candidates->count()) {
                throw new RuntimeException('Imported offer set differs from the reviewed creation plan.');
            }

            $rate = KarinaPricing::rateEvidence([
                'Cur_Abbreviation' => 'RUB',
                'Cur_OfficialRate' => $report['rate']['official_rate'],
                'Cur_Scale' => $report['rate']['scale'],
                'Date' => $report['rate']['date'],
            ], now()->format('Y-m-d'))['byn_per_rub'];

            $selected = [];
            foreach ($candidates as $candidate) {
                $article = (string) $candidate['article'];
                $sourceItem = $itemsByArticle->get($article);
                $priceItem = $priceItems->get($article);
                if (! $sourceItem || ! $priceItem || $priceItem->product_id
                    || $priceItem->match_status !== 'unmatched'
                    || $priceItem->name !== $candidate['name']) {
                    throw new RuntimeException('Imported offer changed after the creation audit.');
                }
                $quote = KarinaPricing::quote((float) $sourceItem['retail_rub'], $rate);
                if (abs($quote['retail_byn'] - (float) $candidate['retail_byn']) > 0.001
                    || abs($quote['purchase_byn'] - (float) $candidate['purchase_byn']) > 0.001
                    || abs((float) $priceItem->price - $quote['purchase_rub']) > 0.001) {
                    throw new RuntimeException('Price changed after the creation audit.');
                }
                $isStove = str_starts_with((string) $candidate['name'], 'Электрическая печь');
                $selected[] = compact('candidate', 'priceItem', 'quote', 'isStove');
            }

            $result = [
                'already_applied' => false,
                'planned' => count($selected),
                'stoves' => count(array_filter($selected, fn ($row) => $row['isStove'])),
                'accessories' => count(array_filter($selected, fn ($row) => ! $row['isStove'])),
                'created' => 0,
            ];
            if (! $apply) {
                return $result;
            }

            foreach ($selected as $row) {
                $candidate = $row['candidate'];
                $quote = $row['quote'];
                $name = trim((string) $candidate['name']);
                $product = Product::query()->create([
                    'category_id' => $row['isStove'] ? $stoveCategoryId : $accessoryCategoryId,
                    'brand_id' => $brandId,
                    'supplier_id' => $supplier->id,
                    'name' => $name,
                    'slug' => 'new-product-'.Str::uuid(),
                    'h1' => $name,
                    'sku' => null,
                    'price' => $quote['retail_byn'],
                    'price_old' => null,
                    'manual_purchase_price_byn' => $quote['purchase_byn'],
                    'manual_price_updated_at' => now(),
                    'currency' => 'BYN',
                    'images' => [],
                    'specs' => [],
                    'unit' => 'шт',
                    'is_active' => false,
                    'is_archived' => false,
                    'in_stock' => false,
                    'availability_status' => Product::AVAILABILITY_CHECK,
                    'stock_qty' => null,
                    'requires_price_confirmation' => true,
                    'created_via' => self::CREATED_VIA,
                    'meta_title' => Str::limit($name.' — купить в Беларуси | KOTLOV', 255, ''),
                    'meta_description' => Str::limit('Купить '.$name.' в KOTLOV. Актуальная цена, характеристики и доставка по Беларуси.', 255, ''),
                ]);
                $sku = $this->uniqueSku($product->id);
                $product->forceFill(['sku' => $sku, 'slug' => $this->uniqueSlug($name, $product->id)])->save();

                $raw = is_array($row['priceItem']->raw) ? $row['priceItem']->raw : [];
                $raw['karina'] = is_array($raw['karina'] ?? null) ? $raw['karina'] : [];
                $raw['karina']['reviewed'] = true;
                $raw['karina']['created_product'] = true;
                $row['priceItem']->forceFill([
                    'raw' => $raw,
                    'product_id' => $product->id,
                    'product_sku' => $sku,
                    'match_status' => 'matched',
                    'match_method' => 'manual',
                ])->save();

                SupplierProductMapping::updateOrCreate(
                    ['supplier_code' => 'karina', 'supplier_article' => $candidate['article']],
                    ['product_id' => $product->id, 'product_sku' => $sku, 'supplier_name' => $name,
                        'confidence' => 'manual', 'is_active' => true,
                        'notes' => 'Issue #34 reviewed new hidden product']
                );
                SupplierProduct::updateOrCreate(
                    ['supplier_id' => $supplier->id, 'supplier_article' => $candidate['article']],
                    ['product_id' => $product->id, 'product_sku' => $sku, 'supplier_name' => $name,
                        'price' => $quote['purchase_rub'], 'currency' => 'RUB', 'currency_rate' => $rate,
                        'price_byn' => $quote['purchase_byn'], 'raw' => $raw,
                        'in_stock' => false, 'stock_status' => 'unknown', 'stock_quantity' => null,
                        'match_status' => 'matched', 'match_confidence' => 'manual_price_list',
                        'last_synced_at' => now()]
                );
                $result['created']++;
            }

            $matched = SupplierPriceItem::query()->where('import_id', $import->id)->where('match_status', 'matched')->count();
            $unmatched = SupplierPriceItem::query()->where('import_id', $import->id)->where('match_status', 'unmatched')->count();
            $import->forceFill([
                'matched' => $matched,
                'unmatched' => $unmatched,
                'updated' => $matched,
                'skipped' => $unmatched,
            ])->save();

            return $result + ['supplier_id' => $supplier->id, 'import_id' => $import->id,
                'matched_after' => $matched, 'unmatched_after' => $unmatched];
        });
    }

    private function uniqueSku(int $productId): string
    {
        $number = $productId;
        do {
            $sku = sprintf('KOTLOV-%06d', $number++);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name, int $productId): string
    {
        $base = Str::slug($name) ?: 'product-'.$productId;
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->whereKeyNot($productId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
