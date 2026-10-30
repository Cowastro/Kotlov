<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class SyncDealByBrandPricesCommand extends Command
{
    private const PAGE_SIZE = 100;

    private const SUPPORTED_SUPPLIERS = ['bania', 'prometall'];

    private ?string $supplierCode = null;

    protected $signature = 'deal:sync-brand-prices
        {--supplier= : Audit products linked to an allowed supplier code instead of the default brands}
        {--inspect-id= : Emit safe price and discount fields for one Deal.by product ID}
        {--apply : Update matched Deal.by product prices}';

    protected $description = 'Safely audit or synchronize selected KOTLOV.BY prices to Deal.by';

    public function handle(DealByClient $client): int
    {
        try {
            $this->supplierCode = $this->resolveSupplierCode();
            $localProducts = $this->targetProducts();
            $remoteProducts = $this->loadRemoteProducts($client);

            if (filled($this->option('inspect-id'))) {
                return $this->inspectRemoteProduct($remoteProducts, (int) $this->option('inspect-id'));
            }

            $plan = $this->buildPlan($localProducts, $remoteProducts);

            $this->metrics($localProducts, $remoteProducts, $plan);

            if ($this->supplierCode === null) {
                $localVezuviy = $localProducts->filter(
                    fn (Product $product): bool => $this->normalize((string) $product->brand?->name) === 'везувий'
                )->count();
                $localTatra = $localProducts->count() - $localVezuviy;
                if ($localVezuviy === 0 || $localTatra === 0) {
                    throw new RuntimeException('Безопасная остановка: в рабочей базе не найдена одна из целевых групп товаров.');
                }
            } elseif ($localProducts->isEmpty()) {
                throw new RuntimeException('Безопасная остановка: у поставщика нет активных товаров с ценой.');
            }

            if (! $this->option('apply')) {
                $this->info('Проверка завершена. Deal.by не изменялся.');

                return self::SUCCESS;
            }

            if ($plan['matched'] === 0) {
                throw new RuntimeException('Безопасная остановка: ни один целевой товар не связан с Deal.by.');
            }

            foreach ($plan['updates']->chunk(self::PAGE_SIZE) as $chunk) {
                $response = $client->editProducts($chunk->map(fn (array $item): array => [
                    'id' => $item['deal_id'],
                    'price' => $item['new_price'],
                ])->values()->all());

                if (filled($response['error'] ?? null) || collect($response['errors'] ?? [])->filter()->isNotEmpty()) {
                    throw new RuntimeException('Deal.by отклонил обновление цен.');
                }
            }

            $verification = $this->buildPlan($localProducts, $this->loadRemoteProducts($client));
            $remaining = $verification['updates']->count();

            $this->line('metric.deal_brand_prices.updated='.$plan['updates']->count());
            $this->line('metric.deal_brand_prices.remaining_mismatches='.$remaining);

            if ($remaining > 0) {
                throw new RuntimeException('Повторная проверка Deal.by обнаружила оставшиеся расхождения цен.');
            }

            $this->info($this->supplierCode === null
                ? 'Цены «Везувий» и котлов Tatra обновлены и проверены на Deal.by.'
                : 'Цены товаров поставщика обновлены и проверены на Deal.by.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Синхронизация цен Deal.by остановлена: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return Collection<int, Product> */
    private function targetProducts(): Collection
    {
        $query = Product::query()
            ->with(['brand:id,name', 'supplierProducts:id,product_id,supplier_id,supplier_article'])
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->where('price', '>', 0);

        if ($this->supplierCode !== null) {
            $query->whereHas('supplierProducts.supplier', function ($supplier): void {
                $supplier->where('code', $this->supplierCode)->where('is_active', true);
            });
        }

        $products = $query->get(['id', 'brand_id', 'name', 'sku', 'slug', 'price']);

        if ($this->supplierCode !== null) {
            return $products->values();
        }

        return $products
            ->filter(function (Product $product): bool {
                $brand = $this->normalize((string) $product->brand?->name);

                if ($brand === 'везувий') {
                    return true;
                }

                return in_array($brand, ['tatra', 'татра'], true);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $localProducts
     * @param  Collection<int, array<string, mixed>>  $remoteProducts
     * @return array{updates: Collection<int, array<string, mixed>>, matched: int, unchanged: int, unmatched: int, ambiguous: int, invalid_remote_price: int}
     */
    private function buildPlan(Collection $localProducts, Collection $remoteProducts): array
    {
        $localById = $localProducts->keyBy('id');
        // The shop keeps the manufacturer's article (ProMetall p0048) as its SKU: the supplier's article of the card.
        $supplierId = $this->supplierCode !== null
            ? (int) \Illuminate\Support\Facades\DB::table('suppliers')->where('code', $this->supplierCode)->value('id')
            : 0;
        $localByArticle = $supplierId > 0
            ? $localProducts->flatMap(fn (Product $product) => $product->supplierProducts
                ->where('supplier_id', $supplierId)
                ->map(fn ($offer) => [$this->normalizeSku((string) $offer->supplier_article), $product]))
                ->filter(fn (array $pair): bool => $pair[0] !== '')
                ->groupBy(fn (array $pair): string => $pair[0], true)
                ->map(fn ($pairs) => collect($pairs)->map(fn (array $pair) => $pair[1])->unique('id')->values())
            : collect();
        $localBySku = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeSku($product->sku) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->sku));
        $localBySlug = $localProducts
            ->filter(fn (Product $product): bool => filled($product->slug))
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->slug));
        $localByName = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeName($product->name) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeName($product->name));
        $localByTatraModel = $localProducts
            ->filter(fn (Product $product): bool => $this->isTatra($product))
            ->mapToGroups(fn (Product $product): array => [
                $this->tatraModelKey(
                    $product->name,
                    $product->slug,
                    $product->sku,
                    ...$product->supplierProducts->pluck('supplier_article')->all(),
                ) ?? '' => $product,
            ])
            ->forget('');
        $remoteSkuCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeSku($product['sku'] ?? null))
            ->filter()
            ->countBy();
        $remoteNameCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeName($product['name'] ?? null))
            ->filter()
            ->countBy();
        $remoteTatraModelCounts = $remoteProducts
            ->filter(fn (array $product): bool => $this->looksLikeTatra($product['name'] ?? null))
            ->map(fn (array $product): ?string => $this->tatraModelKey(
                $product['name'] ?? null,
                $product['sku'] ?? null,
            ))
            ->filter()
            ->countBy();

        $updates = collect();
        $matched = 0;
        $unchanged = 0;
        $ambiguous = 0;
        $invalidRemotePrice = 0;
        $matchedLocalIds = collect();

        foreach ($remoteProducts as $remote) {
            $local = null;
            $externalId = trim((string) ($remote['external_id'] ?? ''));
            $remoteSku = $this->normalizeSku($remote['sku'] ?? null);

            if (preg_match('/^kotlov-(\d+)$/i', $externalId, $match) === 1) {
                $local = $localById->get((int) $match[1]);
            }

            if (! $local && $remoteSku !== '' && ($remoteSkuCounts[$remoteSku] ?? 0) === 1) {
                $slugCandidates = $localBySlug->get($remoteSku, collect());
                $skuCandidates = $localBySku->get($remoteSku, collect());
                $articleCandidates = $localByArticle->get($remoteSku, collect());
                $candidates = $slugCandidates->concat($skuCandidates)->concat($articleCandidates)->unique('id')->values();

                if ($candidates->count() === 1) {
                    $local = $candidates->first();
                } elseif ($candidates->count() > 1) {
                    $ambiguous++;
                }
            }

            $remoteName = $this->normalizeName($remote['name'] ?? null);
            if (! $local && $remoteName !== '' && ($remoteNameCounts[$remoteName] ?? 0) === 1) {
                $nameCandidates = $localByName->get($remoteName, collect());
                if ($nameCandidates->count() === 1) {
                    $local = $nameCandidates->first();
                } elseif ($nameCandidates->count() > 1) {
                    $ambiguous++;
                }
            }

            if (! $local && $this->looksLikeTatra($remote['name'] ?? null)) {
                $model = $this->tatraModelKey($remote['name'] ?? null, $remote['sku'] ?? null);
                $modelCandidates = $model !== null ? $localByTatraModel->get($model, collect()) : collect();

                if ($model !== null && ($remoteTatraModelCounts[$model] ?? 0) === 1 && $modelCandidates->count() === 1) {
                    $local = $modelCandidates->first();
                } elseif ($modelCandidates->count() > 1) {
                    $ambiguous++;
                }
            }

            if (! $local) {
                continue;
            }

            $matched++;
            $matchedLocalIds->push($local->id);

            if (! is_numeric($remote['price'] ?? null)) {
                $invalidRemotePrice++;
                continue;
            }

            $remotePrice = round((float) $remote['price'], 2);
            $localPrice = round((float) $local->price, 2);
            if (abs($remotePrice - $localPrice) < 0.01) {
                $unchanged++;
                continue;
            }

            $group = $this->supplierCode
                ?? ($this->normalize((string) $local->brand?->name) === 'везувий' ? 'vezuviy' : 'tatra_boilers');
            $updates->push([
                'deal_id' => (int) ($remote['id'] ?? 0),
                'local_id' => (int) $local->id,
                'group' => $group,
                'old_price' => $remotePrice,
                'new_price' => $localPrice,
            ]);
        }

        if ($updates->contains(fn (array $item): bool => $item['deal_id'] <= 0)) {
            throw new RuntimeException('Deal.by вернул товар без корректного ID.');
        }

        return [
            'updates' => $updates,
            'matched' => $matched,
            'unchanged' => $unchanged,
            'unmatched' => $localProducts->pluck('id')->diff($matchedLocalIds->unique())->count(),
            'ambiguous' => $ambiguous,
            'invalid_remote_price' => $invalidRemotePrice,
        ];
    }

    /**
     * @param  Collection<int, Product>  $localProducts
     * @param  Collection<int, array<string, mixed>>  $remoteProducts
     * @param  array{updates: Collection<int, array<string, mixed>>, matched: int, unchanged: int, unmatched: int, ambiguous: int, invalid_remote_price: int}  $plan
     */
    private function metrics(Collection $localProducts, Collection $remoteProducts, array $plan): void
    {
        $this->line('metric.deal_brand_prices.remote_products='.$remoteProducts->count());
        if ($this->supplierCode !== null) {
            $this->line('metric.deal_brand_prices.local_supplier_'.$this->supplierCode.'='.$localProducts->count());
        }
        $this->line('metric.deal_brand_prices.local_vezuviy='.$localProducts->filter(
            fn (Product $product): bool => $this->normalize((string) $product->brand?->name) === 'везувий'
        )->count());
        $this->line('metric.deal_brand_prices.local_tatra_boilers='.$localProducts->filter(
            fn (Product $product): bool => in_array($this->normalize((string) $product->brand?->name), ['tatra', 'татра'], true)
        )->count());
        $this->line('metric.deal_brand_prices.matched='.$plan['matched']);
        $this->line('metric.deal_brand_prices.unchanged='.$plan['unchanged']);
        $this->line('metric.deal_brand_prices.price_mismatches='.$plan['updates']->count());
        $this->line('metric.deal_brand_prices.unmatched='.$plan['unmatched']);
        $this->line('metric.deal_brand_prices.ambiguous='.$plan['ambiguous']);
        $this->line('metric.deal_brand_prices.invalid_remote_price='.$plan['invalid_remote_price']);

        // ProMetall goods on the shop as they are named there: to link the rest by hand.
        if ($this->supplierCode === 'prometall') {
            foreach ($remoteProducts as $remote) {
                if (preg_match('/атмосфер|прометалл|prometall|эйфори|бахт|арктик/ui', (string) ($remote['name'] ?? ''))) {
                    $this->line(sprintf('audit.deal_brand_prices.remote=%d:%s:%s:%d:%s', (int) ($remote['id'] ?? 0),
                        \Illuminate\Support\Str::slug((string) ($remote['sku'] ?? '')) ?: 'none', \Illuminate\Support\Str::slug((string) ($remote['external_id'] ?? '')) ?: 'none',
                        (int) round((float) ($remote['price'] ?? 0) * 100), \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug((string) ($remote['name'] ?? '')), 80, '')));
                }
            }
        }
        foreach ($plan['updates'] as $item) {
            $this->line(sprintf(
                'audit.deal_brand_prices=%d:%d:%s:%d:%d',
                $item['deal_id'],
                $item['local_id'],
                $item['group'],
                (int) round($item['old_price'] * 100),
                (int) round($item['new_price'] * 100),
            ));
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function loadRemoteProducts(DealByClient $client): Collection
    {
        $products = collect();
        $lastId = null;

        for ($page = 1; $page <= 2000; $page++) {
            $payload = $client->listProducts(self::PAGE_SIZE, $lastId);
            $items = collect($payload['products'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values();
            $products->push(...$items);

            if ($items->count() < self::PAGE_SIZE) {
                return $products;
            }

            $nextLastId = (int) data_get($items->last(), 'id');
            if ($nextLastId <= 0 || $nextLastId === $lastId) {
                throw new RuntimeException('Deal.by вернул некорректный курсор пагинации товаров.');
            }
            $lastId = $nextLastId;
        }

        throw new RuntimeException('Превышен безопасный предел в 200 000 товаров Deal.by.');
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function normalizeSku(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function normalizeName(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value));
    }

    private function isTatra(Product $product): bool
    {
        return in_array($this->normalize((string) $product->brand?->name), ['tatra', 'татра'], true);
    }

    private function looksLikeTatra(mixed $value): bool
    {
        return preg_match('/(?:tatra|татра)/ui', (string) $value) === 1;
    }

    private function tatraModelKey(mixed ...$values): ?string
    {
        $original = mb_strtoupper(implode(' ', array_map(static fn (mixed $item): string => (string) $item, $values)));
        $value = $original;
        $value = strtr($value, [
            'А' => 'A', 'Б' => 'B', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
            'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'Т' => 'T',
        ]);
        $compact = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';

        if (preg_match('/KT(\d{2,3})(N(?:L|P)[12]?|N|TR)?/', $compact, $match) === 1) {
            $suffix = $match[2] ?? '';
            if (in_array($suffix, ['N', 'NL'], true) && preg_match('/(?:ЛЕВ|LEFT)/u', $original) === 1) {
                $suffix = 'NL1';
            } elseif (in_array($suffix, ['N', 'NP'], true) && preg_match('/(?:ПРАВ|RIGHT)/u', $original) === 1) {
                $suffix = 'NP1';
            }

            return 'KT'.$match[1].$suffix;
        }

        if (preg_match('/K(\d{2,3})TR/', $compact, $match) === 1) {
            return 'K'.$match[1].'TR';
        }

        if (preg_match('/TATRA.*?T(10|16|24|30|32|36)(T)?/', $compact, $match) === 1) {
            $model = 'T'.$match[1].($match[2] ?? '');
            if (preg_match('/(?:BAKOM?|TANK)(50|80|100)L?/', $compact, $tank) === 1) {
                $model .= 'TANK'.$tank[1];
            }

            return $model;
        }

        return null;
    }

    private function resolveSupplierCode(): ?string
    {
        $supplierCode = $this->normalize((string) $this->option('supplier'));
        if ($supplierCode === '') {
            return null;
        }

        if (! in_array($supplierCode, self::SUPPORTED_SUPPLIERS, true)) {
            throw new RuntimeException('Безопасная остановка: этот код поставщика не разрешён для сверки Deal.by.');
        }

        return $supplierCode;
    }

    /** @param Collection<int, array<string, mixed>> $remoteProducts */
    private function inspectRemoteProduct(Collection $remoteProducts, int $dealId): int
    {
        if ($dealId <= 0) {
            throw new RuntimeException('--inspect-id должен быть положительным ID товара Deal.by.');
        }

        $product = $remoteProducts->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $dealId);
        $this->line('metric.deal_brand_prices.inspect_found='.($product === null ? '0' : '1'));

        if ($product === null) {
            return self::FAILURE;
        }

        foreach ($this->priceFields($product) as $path => $value) {
            $this->line(sprintf(
                'audit.deal_brand_prices.field=%d:%s:%d',
                $dealId,
                $path,
                (int) round($value * 100),
            ));
        }

        return self::SUCCESS;
    }

    /** @return array<string, float> */
    private function priceFields(array $payload, string $prefix = ''): array
    {
        $fields = [];

        foreach ($payload as $key => $value) {
            $segment = preg_replace('/[^a-z0-9_-]+/i', '_', (string) $key) ?: 'field';
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                $fields += $this->priceFields($value, $path);
                continue;
            }

            if (preg_match('/(?:price|discount|cost|sale)/i', (string) $key) === 1 && is_numeric($value)) {
                $fields[$path] = (float) $value;
            }
        }

        return $fields;
    }
}
