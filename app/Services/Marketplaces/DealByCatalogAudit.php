<?php

namespace App\Services\Marketplaces;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DealByCatalogAudit
{
    private const PAGE_SIZE = 100;

    public function __construct(private readonly DealByClient $client) {}

    /** @return array{summary: array<string, int>, report_path: string} */
    public function run(): array
    {
        $remoteProducts = $this->loadRemoteProducts();
        $localProducts = Product::query()
            ->select(['id', 'sku', 'slug', 'name', 'price', 'is_active', 'is_archived'])
            ->get();

        $localById = $localProducts->keyBy('id');
        $localBySlug = $localProducts
            ->filter(fn (Product $product): bool => filled($product->slug))
            ->groupBy(fn (Product $product): string => mb_strtolower(trim((string) $product->slug)));
        $localBySku = $localProducts
            ->filter(fn (Product $product): bool => $this->normalizeSku($product->sku) !== '')
            ->groupBy(fn (Product $product): string => $this->normalizeSku($product->sku));
        $remoteSkuCounts = $remoteProducts
            ->map(fn (array $product): string => $this->normalizeSku($product['sku'] ?? null))
            ->filter()
            ->countBy();

        $summary = [
            'remote_products' => $remoteProducts->count(),
            'local_products' => $localProducts->count(),
            'remote_with_external_id' => 0,
            'matched_by_external_id' => 0,
            'matched_by_slug' => 0,
            'matched_by_sku' => 0,
            'ambiguous_local_sku' => 0,
            'unmatched' => 0,
            'duplicate_remote_sku' => 0,
            'price_mismatch' => 0,
        ];
        $rows = [];

        foreach ($remoteProducts as $remote) {
            $externalId = trim((string) ($remote['external_id'] ?? ''));
            $remoteSku = $this->normalizeSku($remote['sku'] ?? null);
            $local = null;
            $method = '';
            $issues = [];

            if ($externalId !== '') {
                $summary['remote_with_external_id']++;
            }

            if (preg_match('/^kotlov-(\d+)$/i', $externalId, $match) === 1) {
                $local = $localById->get((int) $match[1]);
                if ($local) {
                    $method = 'external_id';
                    $summary['matched_by_external_id']++;
                }
            }

            if (! $local && $remoteSku !== '') {
                /** @var Collection<int, Product> $slugCandidates */
                $slugCandidates = $localBySlug->get(mb_strtolower($remoteSku), collect());
                if ($slugCandidates->count() === 1) {
                    $local = $slugCandidates->first();
                    $method = 'slug';
                    $summary['matched_by_slug']++;
                }
            }

            if (! $local && $remoteSku !== '') {
                /** @var Collection<int, Product> $candidates */
                $candidates = $localBySku->get($remoteSku, collect());
                if ($candidates->count() === 1) {
                    $local = $candidates->first();
                    $method = 'sku';
                    $summary['matched_by_sku']++;
                } elseif ($candidates->count() > 1) {
                    $issues[] = 'ambiguous_local_sku';
                    $summary['ambiguous_local_sku']++;
                }
            }

            if (($remoteSkuCounts[$remoteSku] ?? 0) > 1 && $remoteSku !== '') {
                $issues[] = 'duplicate_remote_sku';
                $summary['duplicate_remote_sku']++;
            }

            if (! $local) {
                $issues[] = 'unmatched';
                $summary['unmatched']++;
            }

            $remotePrice = is_numeric($remote['price'] ?? null) ? (float) $remote['price'] : null;
            $priceDifference = $local && $remotePrice !== null
                ? round($remotePrice - (float) $local->price, 2)
                : null;
            if ($priceDifference !== null && abs($priceDifference) >= 0.01) {
                $issues[] = 'price_mismatch';
                $summary['price_mismatch']++;
            }

            $rows[] = [
                $remote['id'] ?? '',
                $externalId,
                $remote['sku'] ?? '',
                $remote['name'] ?? '',
                $remote['status'] ?? '',
                $remote['presence'] ?? '',
                $remotePrice,
                $remote['quantity_in_stock'] ?? '',
                $local?->id,
                $method,
                $local?->sku,
                $local?->slug,
                $local?->name,
                $local?->price,
                $priceDifference,
                implode('|', array_unique($issues)),
            ];
        }

        $reportPath = 'deal-by/catalog-audit-'.now()->format('Ymd-His').'.csv';
        Storage::disk('local')->put($reportPath, $this->csv($rows));

        return ['summary' => $summary, 'report_path' => Storage::disk('local')->path($reportPath)];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function loadRemoteProducts(): Collection
    {
        $products = collect();
        $lastId = null;

        for ($page = 1; $page <= 2000; $page++) {
            $payload = $this->client->listProducts(self::PAGE_SIZE, $lastId);
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

    private function normalizeSku(mixed $sku): string
    {
        return mb_strtoupper(trim((string) $sku));
    }

    /** @param list<array<int, mixed>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Не удалось создать временный CSV отчёта.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'deal_id', 'external_id', 'deal_sku', 'deal_name', 'deal_status', 'deal_presence',
            'deal_price', 'deal_quantity', 'local_product_id', 'match_method', 'local_sku',
            'local_slug', 'local_name', 'local_price', 'price_difference', 'issues',
        ], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать CSV отчёта.');
        }

        return $contents;
    }
}
