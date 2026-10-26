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

    protected $signature = 'deal:sync-brand-prices
        {--apply : Update matched Deal.by product prices}';

    protected $description = 'Safely synchronize Vezuviy and Tatra boiler prices from KOTLOV.BY to Deal.by';

    public function handle(DealByClient $client): int
    {
        try {
            $localProducts = $this->targetProducts();
            $remoteProducts = $this->loadRemoteProducts($client);
            $plan = $this->buildPlan($localProducts, $remoteProducts);

            $this->metrics($localProducts, $remoteProducts, $plan);

            $localVezuviy = $localProducts->filter(
                fn (Product $product): bool => $this->normalize((string) $product->brand?->name) === 'везувий'
            )->count();
            $localTatra = $localProducts->count() - $localVezuviy;
            if ($localVezuviy === 0 || $localTatra === 0) {
                throw new RuntimeException('Безопасная остановка: в рабочей базе не найдена одна из целевых групп товаров.');
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

            $this->info('Цены «Везувий» и котлов Tatra обновлены и проверены на Deal.by.');

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
        return Product::query()
            ->with(['brand:id,name'])
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->where('price', '>', 0)
            ->get(['id', 'brand_id', 'name', 'sku', 'slug', 'price'])
            ->filter(function (Product $product): bool {
                $brand = $this->normalize((string) $product->brand?->name);

                if ($brand === 'везувий') {
                    return true;
                }

                return in_array($brand, ['tatra', 'татра'], true)
                    && preg_match('/(кот[её]л|boiler)/ui', (string) $product->name) === 1;
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
        $localBySku = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeSku($product->sku) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->sku));
        $localBySlug = $localProducts
            ->filter(fn (Product $product): bool => filled($product->slug))
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->slug));
        $localByName = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeName($product->name) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeName($product->name));
        $remoteSkuCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeSku($product['sku'] ?? null))
            ->filter()
            ->countBy();
        $remoteNameCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeName($product['name'] ?? null))
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
                $candidates = $slugCandidates->concat($skuCandidates)->unique('id')->values();

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

            $group = $this->normalize((string) $local->brand?->name) === 'везувий' ? 'vezuviy' : 'tatra_boilers';
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
}
