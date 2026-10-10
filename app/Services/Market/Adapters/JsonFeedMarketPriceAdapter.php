<?php

namespace App\Services\Market\Adapters;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;
use App\Models\Product;
use App\Services\Market\Contracts\MarketPriceSourceAdapter;
use App\Services\Market\MarketCollectionHttpClient;
use App\Services\Market\MarketPriceCollectionManager;
use App\Services\Market\MarketRobotsPolicy;
use Illuminate\Support\Arr;

class JsonFeedMarketPriceAdapter implements MarketPriceSourceAdapter
{
    public function __construct(
        private readonly MarketCollectionHttpClient $http,
        private readonly MarketPriceCollectionManager $manager,
        private readonly MarketRobotsPolicy $robots,
    ) {}

    public function key(): string
    {
        return 'json_feed_v1';
    }

    public function collect(MarketPriceSource $source, MarketPriceCollectionRun $run): void
    {
        $settings = $source->collection_settings ?? [];
        $endpointPath = '/'.ltrim(trim((string) Arr::get($settings, 'endpoint_path')), '/');
        if ($endpointPath === '/') {
            $this->manager->fail($run, 'adapter_not_configured', 'Для JSON-адаптера не указан путь endpoint.');

            return;
        }

        $endpointUrl = rtrim((string) $source->base_url, '/').$endpointPath;
        if (! $this->robots->allows($source, $run, $endpointUrl)) {
            return;
        }

        $response = $this->http->get($run, $endpointUrl);
        if ($response === null) {
            return;
        }

        $decoded = $response->json();
        $itemsPath = trim((string) Arr::get($settings, 'items_path', 'items'));
        $items = $itemsPath === '' ? $decoded : data_get($decoded, $itemsPath);
        if (! is_array($items)) {
            $this->manager->fail($run, 'invalid_feed_shape', 'JSON-ответ не содержит массив предложений по указанному пути.');

            return;
        }

        $maxItems = min(max((int) Arr::get($settings, 'max_items_per_run', 500), 1), 5000);
        foreach (array_slice($items, 0, $maxItems) as $index => $item) {
            if (! is_array($item)) {
                $this->manager->skip($run, 'invalid_item', 'Строка '.($index + 1).' не является объектом JSON.');

                continue;
            }

            $sku = trim((string) Arr::get($item, 'product_sku'));
            $product = $sku === '' ? null : Product::query()->where('sku', $sku)->first();
            if (! $product) {
                $this->manager->skip(
                    $run,
                    'product_not_found',
                    $sku === ''
                        ? 'Строка '.($index + 1).' не содержит product_sku.'
                        : 'Карточка сайта с SKU '.$sku.' не найдена; товар не создан автоматически.',
                );

                continue;
            }

            $this->manager->recordFetched($run, $product, [
                'url' => Arr::get($item, 'url'),
                'external_name' => Arr::get($item, 'name'),
                'external_sku' => Arr::get($item, 'sku'),
                'model' => Arr::get($item, 'model'),
                'package' => Arr::get($item, 'package'),
                'unit' => Arr::get($item, 'unit'),
                'observed_price' => Arr::get($item, 'price'),
                'currency' => Arr::get($item, 'currency', $source->currency),
                'exchange_rate_to_byn' => Arr::get($item, 'exchange_rate_to_byn', $source->currency === 'BYN' ? 1 : 0),
                'price_includes_vat' => Arr::get($item, 'price_includes_vat'),
                'vat_rate' => Arr::get($item, 'vat_rate'),
                'delivery_price_byn' => Arr::get($item, 'delivery_price_byn'),
                'delivery_terms' => Arr::get($item, 'delivery_terms'),
                'region' => Arr::get($item, 'region', $source->region),
                'availability_status' => Arr::get($item, 'availability_status', 'unknown'),
                'match_method' => 'exact_sku',
                'match_confidence' => 1,
                'is_confirmed' => false,
                'is_comparable' => false,
                'validation_flags' => ['requires_human_confirmation'],
                'observed_at' => Arr::get($item, 'observed_at', now()),
            ]);
        }

        if (count($items) > $maxItems) {
            $this->manager->warn($run, 'item_limit_reached', 'Обработаны первые '.$maxItems.' предложений из '.count($items).'.');
        }
    }
}
