<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncTeplovSukhovRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-teplov-sukhov-retail
                            {--apply : Apply verified retail prices and save supplier article links}';

    protected $description = 'Sync only retail prices from the approved Teplov i Sukhov price list. Ambiguous rows are never changed.';

    private const PRICE_FILE = 'imports/sbg-teplov-sukhov-2026-08-10.json';

    public function handle(): int
    {
        $data = json_decode((string) file_get_contents(storage_path('app/' . self::PRICE_FILE)), true);

        if (! is_array($data) || ! is_array($data['rows'] ?? null)) {
            $this->error('Не удалось прочитать подготовленный прайс Теплов и Сухов.');
            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', 'teplov')->first();
        $brand = Brand::query()->where('name', 'Теплов и Сухов')->first();

        if (! $supplier || ! $brand) {
            $this->error('Не найден поставщик teplov или бренд «Теплов и Сухов».');
            return self::FAILURE;
        }

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'price']);

        $apply = (bool) $this->option('apply');
        $stats = ['matched' => 0, 'changed' => 0, 'unchanged' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'conflict' => 0, 'price_list_conflict' => 0];
        $details = [];
        $claimedProducts = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->get(['product_id', 'supplier_article'])
            ->groupBy('product_id')
            ->map(fn ($links) => $links->pluck('supplier_article')->unique()->values()->all())
            ->all();

        foreach ($this->uniqueRows($data['rows']) as $row) {
            if (($row['_price_list_conflict'] ?? false) === true) {
                $stats['price_list_conflict']++;
                $details[] = [$row['sku'], Str::limit((string) $row['name'], 48), 'conflicting duplicate in price list'];
                continue;
            }

            $article = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $retail = $this->price($row['retail'] ?? null);

            if ($article === '' || $name === '' || $retail === null) {
                $stats['unmatched']++;
                $details[] = [$article ?: '—', Str::limit($name, 48), 'invalid'];
                continue;
            }

            $existing = SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('supplier_article', $article)
                ->first();

            if ($existing?->product_id) {
                $candidate = $products->firstWhere('id', $existing->product_id);
                $matches = $candidate ? $this->findMatches(collect([$candidate]), $name) : collect();
            } else {
                $matches = $this->findMatches($products, $name);
            }

            if ($matches->count() === 0) {
                if ($apply && $existing) {
                    // A stale link must not make a future price overwrite a different item.
                    $existing->delete();
                    $claimedProducts[(int) $existing->product_id] = array_values(array_diff($claimedProducts[(int) $existing->product_id] ?? [], [$article]));
                }
                $stats['unmatched']++;
                $details[] = [$article, Str::limit($name, 48), 'not matched'];
                continue;
            }

            if ($matches->count() > 1) {
                $stats['ambiguous']++;
                $details[] = [$article, Str::limit($name, 48), 'ambiguous: ' . $matches->pluck('id')->implode(',')];
                continue;
            }

            /** @var Product $product */
            $product = $matches->first();

            $otherArticles = array_values(array_diff($claimedProducts[(int) $product->id] ?? [], [$article]));
            if ($otherArticles !== []) {
                $stats['conflict']++;
                $details[] = [$article, Str::limit($name, 48), 'product already linked to: ' . implode(',', $otherArticles)];
                continue;
            }

            if ($existing && $existing->product_id && (int) $existing->product_id !== (int) $product->id) {
                $stats['conflict']++;
                $details[] = [$article, Str::limit($name, 48), 'supplier link conflict'];
                continue;
            }

            $stats['matched']++;
            $changed = abs((float) $product->price - $retail) >= 0.005;
            $stats[$changed ? 'changed' : 'unchanged']++;
            $details[] = [$article, Str::limit($name, 48), sprintf('#%d %s: %.2f → %.2f%s', $product->id, Str::limit($product->name, 36), (float) $product->price, $retail, $changed ? '' : ' (same)')];

            if (! $apply) {
                continue;
            }

            if ($changed) {
                $product->price = $retail;
                $product->save();
            }

            SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'supplier_article' => $article],
                [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'supplier_name' => $name,
                    'price' => $retail,
                    'price_byn' => $retail,
                    'currency' => 'BYN',
                    'match_status' => 'matched',
                    'match_confidence' => 'verified_name_dimensions',
                    'raw' => ['price_date' => $data['price_date'] ?? null, 'wholesale' => $row['wholesale'] ?? null],
                    'last_synced_at' => now(),
                ]
            );
            $claimedProducts[(int) $product->id] = [$article];
        }

        $this->info(sprintf('Прайс %s: %s. Режим: %s.', $data['price_date'] ?? '—', $data['price_column'] ?? '—', $apply ? 'APPLY' : 'DRY-RUN'));
        $this->table(['Показатель', 'Количество'], [
            ['Строк в прайсе', count($data['rows'])],
            ['Безопасно сопоставлено', $stats['matched']],
            [$apply ? 'Цен обновлено' : 'Цен будет обновлено', $stats['changed']],
            ['Без изменения', $stats['unchanged']],
            ['Неоднозначных — пропущено', $stats['ambiguous']],
            ['Не найдено — пропущено', $stats['unmatched']],
            ['Конфликтов связи — пропущено', $stats['conflict']],
            ['Конфликтов внутри прайса — пропущено', $stats['price_list_conflict']],
        ]);
        $this->table(['Артикул поставщика', 'Прайс', 'Результат'], array_slice($details, 0, 30));

        return self::SUCCESS;
    }

    private function findMatches($products, string $supplierName)
    {
        $needleWords = $this->words($supplierName);
        $needleNumbers = $this->numbers($supplierName);

        return $products->filter(function (Product $product) use ($needleWords, $needleNumbers) {
            $productWords = $this->words($product->name);
            $productNumbers = $this->numbers($product->name);

            $needleTypes = $this->productTypes($needleWords);
            $productTypes = $this->productTypes($productWords);
            if ($needleTypes !== [] && $needleTypes !== $productTypes) {
                return false;
            }

            // Material, thickness and every diameter from the supplier name must match exactly.
            if (array_diff($needleNumbers, $productNumbers) !== []) {
                return false;
            }

            $discriminators = ['базальт', 'керамоволокно', 'black', 'медь'];
            $needleDiscriminators = array_values(array_intersect($discriminators, $needleWords));
            if (array_diff($needleDiscriminators, $productWords) !== []) {
                return false;
            }

            $hits = count(array_intersect($needleWords, $productWords));
            // Supplier names and historical card names use different terminology
            // (for example, "адаптер котла" vs "адаптер моно"). Exact material,
            // thickness and all diameters are the primary identity; one shared
            // product-type word is enough only after those numbers match.
            $required = count($needleNumbers) >= 2 ? 1 : max(2, min(4, count($needleWords) - 1));

            return $hits >= $required;
        })->values();
    }

    private function words(string $value): array
    {
        $value = $this->normalise($value);
        $ignored = ['теплов', 'сухов', 'котла', 'м', 'мм', 'из', 'для', 'и'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', $value) ?: [],
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, $ignored, true) && ! preg_match('/^\d/', $word)
        )));
    }

    private function productTypes(array $words): array
    {
        $types = ['адаптер', 'переход', 'дефлектор', 'заглушка', 'труба', 'тройник', 'ревизия', 'конденсатосборник', 'хомут', 'кронштейн', 'шибер', 'зонт', 'врезка', 'опора', 'разделка', 'проход', 'моно', 'термо'];

        return array_values(array_intersect($types, $words));
    }

    private function numbers(string $value): array
    {
        $value = $this->normalise($value);
        preg_match_all('/(?<!\p{L})\d+(?:\.\d+)?/u', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace([',', 'ø', 'ф'], ['.', 'd', 'd'], $value);
        $value = preg_replace('/\bd(?=\d)/u', 'd ', $value) ?? $value;
        $value = preg_replace('/\b(?:теплов\s+и\s+сухов|тиc|тис)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/адаптер\s*[-–]?\s*переход/u', 'адаптер переход', $value) ?? $value;
        $value = preg_replace('/\b0?\.(\d)\b/u', '0.$1', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}.]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function price(mixed $value): ?float
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function uniqueRows(array $rows): array
    {
        $grouped = collect($rows)->filter(fn ($row) => trim((string) ($row['sku'] ?? '')) !== '')->groupBy('sku');

        return $grouped->map(function ($sameArticle) {
            $variants = $sameArticle
                ->map(fn ($row) => trim((string) ($row['name'] ?? '')) . '|' . trim((string) ($row['retail'] ?? '')))
                ->unique();

            $row = $sameArticle->first();
            if ($variants->count() > 1) {
                $row['_price_list_conflict'] = true;
            }

            return $row;
        })->values()->all();
    }
}
