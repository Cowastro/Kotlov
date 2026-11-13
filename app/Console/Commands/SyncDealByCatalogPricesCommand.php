<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use App\Services\Marketplaces\DealByPricePlan;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SyncDealByCatalogPricesCommand extends Command
{
    protected $signature = 'deal:sync-catalog-prices {--apply : Persist verified links and update sbg.by prices} {--inspect-id= : Emit the matching decision for one public product}';

    protected $description = 'Link exact catalog identities and synchronize KOTLOV retail prices to sbg.by';

    private const LINKS = 'deal-by/product-links.json';

    public function handle(DealByClient $client, DealByPricePlan $planner): int
    {
        $lock = Cache::lock('deal-by-catalog-price-sync', 7200);
        if (! $lock->get()) {
            $this->error('Синхронизация sbg.by уже выполняется.');

            return self::FAILURE;
        }
        try {
            $products = Product::query()->with(['supplierProducts' => fn ($query) => $query
                ->select(['id', 'product_id', 'supplier_article'])
                ->where('match_status', 'matched')
                ->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true)),
                'integrationProducts' => fn ($query) => $query
                    ->select(['id', 'product_id', 'external_sku', 'barcode'])
                    ->where('match_status', 'matched')->where('match_confidence', '>=', 0.99)
                    ->whereHas('source', fn ($source) => $source->where('is_active', true))])
                ->get(['id', 'sku', 'slug', 'name', 'price', 'currency', 'is_active', 'is_archived', 'requires_price_confirmation']);
            $disk = Storage::disk('local');
            $links = $disk->exists(self::LINKS) ? json_decode($disk->get(self::LINKS), true, 512, JSON_THROW_ON_ERROR) : [];
            $remote = $this->loadProducts($client);
            $plan = $planner->build($products, $remote, $links);
            $stamp = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
            if (! $disk->put('deal-by/price-plans/'.$stamp.'.json', json_encode($plan->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Не удалось сохранить план цен sbg.by.');
            }
            $this->metric('remote_products', $remote->count());
            $this->metric('legacy_byr_labels', $remote->where('_api_currency', 'BYR')->count());
            $this->metric('local_products', $products->count());
            foreach ($plan->countBy('reason') as $reason => $count) {
                $this->metric($reason, $count);
            }
            if ($id = (int) $this->option('inspect-id')) {
                foreach ($plan->where('deal_id', $id) as $row) {
                    $this->line('audit.deal_catalog_price.item='.$id.':'.($row['local_id'] ?? 0).':'.$row['reason'].':'.($row['local_slug'] ?? 'none'));
                    $this->metric('inspected_old_cents', (int) round((float) $row['old_price'] * 100));
                    $this->metric('inspected_new_cents', (int) round((float) $row['new_price'] * 100));
                }
            }
            if (! $this->option('apply')) {
                return self::SUCCESS;
            }
            $accepted = $plan->whereIn('reason', ['update', 'unchanged']);
            if ($accepted->isEmpty()) {
                throw new RuntimeException('Нет однозначных соответствий с безопасной ценой.');
            }
            // Keep a recovery copy before any API write, including the previous link registry.
            if (! $disk->put('deal-by/price-plans/'.$stamp.'-links-before.json', json_encode($links, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Не удалось сохранить резервную копию связей sbg.by.');
            }
            $updates = $accepted->where('reason', 'update');
            $sent = 0;
            foreach ($updates->chunk(100) as $chunk) {
                $client->editProducts($chunk->map(fn (array $row): array => ['id' => $row['deal_id'], 'price' => $row['new_price']])->values()->all());
                $sent += $chunk->count();
            }
            $this->metric('sent', $sent);
            $verified = $this->loadProducts($client)->keyBy('id');
            $failed = 0;
            foreach ($accepted as $row) {
                $item = $verified->get($row['deal_id']);
                if (! $item || ($item['currency'] ?? '') !== 'BYN' || $planner->fingerprint($item) !== $row['fingerprint'] || ! is_numeric($item['price'] ?? null)
                    || abs((float) $item['price'] - $row['new_price']) >= 0.005 || ! empty($item['discount'])) {
                    $failed++;

                    continue;
                }
                $links[$row['deal_id']] = ['local_id' => $row['local_id'], 'local_slug' => $row['local_slug'],
                    'fingerprint' => $row['fingerprint'], 'verified_at' => now()->toIso8601String()];
            }
            if (! $disk->put(self::LINKS, json_encode($links, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Не удалось сохранить связи товаров sbg.by.');
            }
            $this->metric('linked', $accepted->count() - $failed);
            $this->metric('verification_failures', $failed);
            if ($failed) {
                throw new RuntimeException('Часть цен не прошла повторную проверку API.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->metric('failed', 1);
            $this->error('Синхронизация sbg.by остановлена. Подробности сохранены в журнале сервера.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function loadProducts(DealByClient $client): Collection
    {
        $products = collect();
        $lastId = null;
        $seen = [];
        for ($page = 0; $page < 2000; $page++) {
            $response = $client->listProducts(100, $lastId);
            $items = $response['products'] ?? null;
            if (! is_array($items) || count($items) > 100) {
                throw new RuntimeException('Некорректный ответ каталога sbg.by.');
            }
            foreach ($items as $item) {
                $id = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
                if ($id <= 0 || isset($seen[$id])) {
                    throw new RuntimeException('Некорректный или повторный ID товара sbg.by.');
                }
                $seen[$id] = true;
                // Deal.by still labels modern Belarusian-ruble amounts BYR in its API.
                // Verified against sbg.by's public BYN offer (1494 API = 1494 BYN, not a FX conversion).
                // Never apply this legacy alias to another marketplace endpoint.
                if (($item['currency'] ?? '') === 'BYR'
                    && strtolower((string) parse_url((string) config('services.deal_by.base_url'), PHP_URL_HOST)) === 'my.deal.by') {
                    $item['_api_currency'] = 'BYR';
                    $item['currency'] = 'BYN';
                }
                $products->push($item);
            }
            if (count($items) < 100) {
                return $products;
            }
            $lastId = (int) $items[array_key_last($items)]['id'];
        }
        throw new RuntimeException('Превышен предел страниц sbg.by.');
    }

    private function metric(string $name, int $value): void
    {
        $this->line('metric.deal_catalog_prices.'.$name.'='.$value);
    }
}
