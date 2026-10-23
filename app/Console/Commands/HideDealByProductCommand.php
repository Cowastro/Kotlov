<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByClient;
use Illuminate\Console\Command;
use RuntimeException;

class HideDealByProductCommand extends Command
{
    protected $signature = 'deal:hide-product
        {sku : Exact Deal.by SKU}
        {--title= : Exact product name used as an additional safety check}
        {--apply : Hide the matched product on Deal.by}';

    protected $description = 'Find one exact Deal.by product and optionally hide it from the storefront';

    public function handle(DealByClient $client): int
    {
        $sku = $this->normalize((string) $this->argument('sku'));
        $name = $this->normalize((string) $this->option('title'));

        if ($sku === '' || $name === '') {
            $this->error('Укажите точный артикул и точное название товара.');

            return self::INVALID;
        }

        $matches = collect($this->loadProducts($client))
            ->filter(fn (array $product): bool =>
                $this->normalize((string) ($product['sku'] ?? '')) === $sku
                && $this->normalize((string) ($product['name'] ?? '')) === $name
            )
            ->values();

        $this->line('metric.matches='.$matches->count());

        if ($matches->count() !== 1) {
            $this->error('Безопасная остановка: найдено не ровно одно точное совпадение.');

            return self::FAILURE;
        }

        $product = $matches->first();
        $id = (int) ($product['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Deal.by вернул товар без корректного ID.');
        }

        $this->line('metric.product_id='.$id);
        $this->line('metric.current_status='.(string) ($product['status'] ?? ''));
        $this->line('metric.current_presence='.(string) ($product['presence'] ?? ''));

        if (! $this->option('apply')) {
            $this->info('Проверка завершена. Deal.by не изменялся.');

            return self::SUCCESS;
        }

        $response = $client->editProducts([[
            'id' => $id,
            'status' => 'not_on_display',
            'presence' => 'not_available',
            'quantity_in_stock' => 0,
        ]]);

        $errors = collect($response['errors'] ?? [])->filter();
        if ($errors->isNotEmpty() || filled($response['error'] ?? null)) {
            throw new RuntimeException('Deal.by не принял отключение товара.');
        }

        $this->line('metric.hidden=1');
        $this->info('Товар скрыт с витрины Deal.by и отмечен как отсутствующий.');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function loadProducts(DealByClient $client): array
    {
        $products = [];
        $lastId = null;

        for ($page = 1; $page <= 2000; $page++) {
            $payload = $client->listProducts(100, $lastId);
            $items = collect($payload['products'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values();

            foreach ($items as $item) {
                $products[] = $item;
            }

            if ($items->count() < 100) {
                return $products;
            }

            $nextLastId = (int) data_get($items->last(), 'id');
            if ($nextLastId <= 0 || $nextLastId === $lastId) {
                throw new RuntimeException('Deal.by вернул некорректный курсор пагинации товаров.');
            }

            $lastId = $nextLastId;
        }

        throw new RuntimeException('Превышен безопасный предел каталога Deal.by.');
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
