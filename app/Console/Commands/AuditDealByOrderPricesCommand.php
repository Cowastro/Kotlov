<?php

namespace App\Console\Commands;

use App\Models\DealByOrderInbox;
use Illuminate\Console\Command;

class AuditDealByOrderPricesCommand extends Command
{
    protected $signature = 'deal:audit-order-prices {ids?* : Deal.by order IDs}';

    protected $description = 'Show non-personal raw price fields stored for Deal.by orders';

    public function handle(): int
    {
        $ids = collect($this->argument('ids'))->map(fn (mixed $id): string => trim((string) $id))->filter();
        $orders = DealByOrderInbox::query()
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('remote_order_id', $ids))
            ->latest('remote_created_at')
            ->limit(100)
            ->get();

        $rows = [];
        foreach ($orders as $inbox) {
            $products = collect(data_get($inbox->payload, 'products', []));
            foreach ($products as $index => $product) {
                if (! is_array($product)) {
                    continue;
                }

                $rows[] = [
                    $inbox->remote_order_id,
                    $this->encoded(data_get($inbox->payload, 'price')),
                    $this->encoded(data_get($inbox->payload, 'full_price')),
                    $this->encoded(data_get($inbox->payload, 'delivery_cost')),
                    $index + 1,
                    $this->encoded($product['price'] ?? null),
                    $this->encoded($product['total_price'] ?? null),
                    $this->encoded($product['quantity'] ?? null),
                ];
            }
        }

        $this->table(
            ['Order', 'price', 'full_price', 'delivery', 'Line', 'line_price', 'line_total', 'qty'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
    }
}
