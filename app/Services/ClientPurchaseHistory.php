<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\CrmCounterparty;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * What a client bought before, for the manager who makes a new selection for them: the orders of the site
 * (by the CRM counterparty, the УНП or the account) and the B2B orders, cancelled ones left out.
 *
 * A client is «crm:<counterparty id>» or «b2b:<B2B client id>»; both reach the same orders when they are linked.
 */
class ClientPurchaseHistory
{
    private const ORDERS = 30;

    /** @return list<array{key: string, label: string, unp: string}> */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
        $crm = CrmCounterparty::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('legal_name', 'like', $like)
            ->orWhere('public_name', 'like', $like)->orWhere('unp', 'like', $like))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'legal_name', 'unp'])
            ->map(fn (CrmCounterparty $counterparty): array => ['key' => 'crm:'.$counterparty->id,
                'label' => (string) ($counterparty->name ?: $counterparty->legal_name), 'unp' => (string) $counterparty->unp]);
        // B2B clients without a CRM counterparty are clients too.
        $b2b = B2bClient::query()->whereNull('counterparty_id')->where(fn ($q) => $q->where('company_name', 'like', $like)->orWhere('unp', 'like', $like))
            ->orderBy('company_name')->limit(5)->get(['id', 'company_name', 'unp'])
            ->map(fn (B2bClient $client): array => ['key' => 'b2b:'.$client->id, 'label' => (string) $client->company_name, 'unp' => (string) $client->unp]);

        return $crm->concat($b2b)->values()->all();
    }

    /** @return array{key: string, label: string, unp: string}|null */
    public function describe(string $key): ?array
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');
        if ($kind === 'crm' && ($counterparty = CrmCounterparty::query()->find((int) $id))) {
            return ['key' => $key, 'label' => (string) ($counterparty->name ?: $counterparty->legal_name), 'unp' => (string) $counterparty->unp];
        }
        if ($kind === 'b2b' && ($client = B2bClient::query()->find((int) $id))) {
            return ['key' => $key, 'label' => (string) $client->company_name, 'unp' => (string) $client->unp];
        }

        return null;
    }

    /**
     * The orders, newest first, and per product the last price.
     *
     * @return array{orders: list<array>, products: array<int, array{price: float, quantity: float, date: string, number: string, times: int}>}
     */
    public function for(string $key): array
    {
        [$counterpartyIds, $clientIds, $userIds, $unps] = $this->identity($key);
        $orders = collect();

        if ($counterpartyIds || $userIds || $unps) {
            Order::query()->where('status', '!=', 'cancelled')
                ->where(fn ($q) => $q->when($counterpartyIds, fn ($q) => $q->orWhereIn('crm_counterparty_id', $counterpartyIds))
                    ->when($userIds, fn ($q) => $q->orWhereIn('user_id', $userIds))
                    ->when($unps, fn ($q) => $q->orWhereIn('company_unp', $unps)))
                ->latest('id')->limit(self::ORDERS)->with('items:id,order_id,product_id,product_name,product_sku,price,quantity')
                ->get(['id', 'number', 'status', 'total', 'created_at'])
                ->each(fn (Order $order) => $orders->push($this->order('site', $order->number, $order->created_at, (float) $order->total, $order->items)));
        }
        if ($clientIds) {
            B2bOrder::query()->whereIn('b2b_client_id', $clientIds)->where('status', '!=', 'cancelled')
                ->latest('id')->limit(self::ORDERS)->with('items:id,b2b_order_id,product_id,product_name,product_sku,price,quantity')
                ->get(['id', 'number', 'status', 'total', 'created_at'])
                ->each(fn (B2bOrder $order) => $orders->push($this->order('b2b', $order->number, $order->created_at, (float) $order->total, $order->items)));
        }
        $orders = $orders->sortByDesc('sort')->take(self::ORDERS)->values();

        $products = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $item) {
                if (! $item['product_id']) {
                    continue;
                }
                $id = $item['product_id'];
                if (! isset($products[$id])) {
                    $products[$id] = ['price' => $item['price'], 'quantity' => $item['quantity'], 'date' => $order['date'], 'number' => $order['number'], 'times' => 0];
                }
                $products[$id]['times']++;
            }
        }

        return ['orders' => $orders->map(fn (array $order) => array_diff_key($order, ['sort' => true]))->all(), 'products' => $products];
    }

    private function order(string $source, ?string $number, $date, float $total, Collection $items): array
    {
        return [
            'source' => $source, 'number' => (string) $number, 'date' => $date?->format('d.m.Y') ?? '', 'sort' => $date?->timestamp ?? 0, 'total' => $total,
            'items' => $items->map(fn (OrderItem|B2bOrderItem $item): array => [
                'product_id' => $item->product_id ? (int) $item->product_id : null, 'name' => (string) $item->product_name, 'sku' => (string) ($item->product_sku ?? ''),
                'price' => (float) $item->price, 'quantity' => (float) $item->quantity,
            ])->values()->all(),
        ];
    }

    /** Counterparties, B2B clients, accounts and УНП that are this client. @return array{0: list<int>, 1: list<int>, 2: list<int>, 3: list<string>} */
    private function identity(string $key): array
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');
        $counterparty = $kind === 'crm' ? CrmCounterparty::query()->find((int) $id, ['id', 'unp']) : null;
        $clients = match ($kind) {
            'crm' => $counterparty ? B2bClient::query()->where('counterparty_id', $counterparty->id)->get(['id', 'user_id', 'unp']) : collect(),
            'b2b' => B2bClient::query()->whereKey((int) $id)->get(['id', 'user_id', 'unp', 'counterparty_id']),
            default => collect(),
        };
        $counterpartyIds = array_values(array_filter([$counterparty?->id, ...$clients->pluck('counterparty_id')->all()]));
        $unps = collect([$counterparty?->unp, ...$clients->pluck('unp')->all()])->map(fn ($unp) => trim((string) $unp))->filter(fn ($unp) => strlen($unp) >= 9)->unique()->values()->all();

        return [array_map('intval', array_unique($counterpartyIds)), $clients->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $clients->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(), $unps];
    }
}
