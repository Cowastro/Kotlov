<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * What an order sends to 1C, and why it may not go. 1C creates a new item for an unknown product id and a
 * new counterparty for an unknown customer id, so an order goes only when every line points at exactly one
 * current 1C item, a retail buyer is the one «Розничный покупатель» counterparty and a company has its УНП.
 */
class OneCOrderReadiness
{
    public const RETAIL_COUNTERPARTY_ID = 'kotlov-retail-customer';

    public const RETAIL_COUNTERPARTY_NAME = 'Розничный покупатель';

    public function __construct(private readonly OrderIntegrationRouter $router) {}

    /**
     * @return array{problems: list<string>, external_ids: array<int, string>, partner: array{b2b: bool, id: string, name: string, full_name: string, tax_id: string}}
     */
    public function check(Order $order, IntegrationSource $source): array
    {
        $order->loadMissing(['items.integrationProduct', 'user']);
        $items = $this->router->itemsForSource($order, $source);
        $problems = [];
        $externalIds = [];

        $linked = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->whereIn('product_id', $items->pluck('product_id')->filter()->unique())
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->get(['product_id', 'external_id'])
            ->groupBy('product_id');

        foreach ($items as $item) {
            $name = '«'.($item->product_name ?: 'позиция #'.$item->id).'»';
            $row = $item->integrationProduct;
            if ($row && (int) $row->integration_source_id === (int) $source->id) {
                $row->match_status === 'ignored'
                    ? $problems[] = $name.': строка 1С отмечена «не для сайта»'
                    : $externalIds[$item->id] = (string) $row->external_id;

                continue;
            }
            if (! $item->product_id) {
                $problems[] = $name.': позиция без карточки товара — в 1С её не с чем связать';

                continue;
            }
            $ids = collect($linked->get($item->product_id, []))->pluck('external_id')->filter()->unique()->values();
            match ($ids->count()) {
                0 => $problems[] = $name.': карточка не привязана к товару 1С',
                1 => $externalIds[$item->id] = (string) $ids->first(),
                default => $problems[] = $name.': карточка связана с несколькими товарами 1С — оставьте один',
            };
        }

        $partner = $this->partner($order, $items->contains(fn (OrderItem $item): bool => $item->pricing_type === 'b2b'), $source);
        if ($partner['b2b'] && $partner['tax_id'] === '') {
            $problems[] = 'У компании-покупателя не указан УНП — по нему 1С находит контрагента';
        }

        return ['problems' => $problems, 'external_ids' => $externalIds, 'partner' => $partner];
    }

    /** @return array{b2b: bool, id: string, name: string, full_name: string, tax_id: string} */
    public function partner(Order $order, bool $hasB2bItems, IntegrationSource $source): array
    {
        $snapshot = is_array($order->b2b_commercial_terms_snapshot) ? $order->b2b_commercial_terms_snapshot : [];
        $b2b = (filled($snapshot['b2b_client_id'] ?? null) || $order->user?->isB2B() === true) && $hasB2bItems;

        if (! $b2b) {
            $name = (string) (data_get($source->settings, 'retail_counterparty_name') ?: self::RETAIL_COUNTERPARTY_NAME);

            return [
                'b2b' => false,
                'id' => (string) (data_get($source->settings, 'retail_counterparty_external_id') ?: self::RETAIL_COUNTERPARTY_ID),
                'name' => $name,
                'full_name' => $name,
                'tax_id' => '',
            ];
        }

        $companyAccountUserId = (int) ($snapshot['company_account_user_id'] ?? $order->user_id);

        return [
            'b2b' => true,
            'id' => 'kotlov-b2b-user-'.$companyAccountUserId,
            'name' => (string) (($snapshot['company_name'] ?? null) ?: $order->user?->company_name ?: $order->company_name ?: $order->customer_name),
            'full_name' => (string) ($order->company_name ?: ($snapshot['company_name'] ?? null) ?: $order->user?->company_name ?: $order->customer_name),
            'tax_id' => trim((string) ($order->company_unp ?: ($snapshot['company_unp'] ?? null) ?: $order->user?->company_inn)),
        ];
    }
}
