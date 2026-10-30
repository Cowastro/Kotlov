<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\User;

class B2bCheckoutTerms
{
    /**
     * @param  array<int|string, array<string, mixed>>  $cart
     * @return array<string, mixed>
     */
    public function evaluate(?User $user, array $cart): array
    {
        $items = collect($cart);
        $b2bItems = $items->filter(
            fn (array $item): bool => ($item['pricing_type'] ?? 'retail') === 'b2b'
        );
        $isB2b = $b2bItems->isNotEmpty();
        $client = $isB2b && $user
            ? $user->b2bClient()->with(['counterparty', 'priceLevel'])->first()
            : null;
        $subtotal = round((float) $b2bItems->sum(
            fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)
        ), 2);

        $issues = [];
        if ($isB2b && (! $user?->isB2B() || ! $client || $client->status !== 'approved')) {
            $issues[] = 'Партнёрский доступ изменился. Обновите корзину или обратитесь к менеджеру.';
        }
        if ($client?->commercial_terms_valid_until?->isBefore(today())) {
            $issues[] = 'Срок ваших коммерческих условий истёк. Менеджер должен подтвердить новые условия перед заказом.';
        }

        $minimum = $client?->minimum_order_amount !== null
            ? (float) $client->minimum_order_amount
            : null;
        $shortfall = $minimum !== null ? max(0, round($minimum - $subtotal, 2)) : 0.0;
        if ($shortfall > 0) {
            $issues[] = 'До минимальной суммы B2B-заказа не хватает '.number_format($shortfall, 2, ',', ' ').' BYN.';
        }

        return [
            'is_b2b' => $isB2b,
            'client' => $client,
            'b2b_subtotal' => $subtotal,
            'minimum_order_amount' => $minimum,
            'minimum_shortfall' => $shortfall,
            'can_checkout' => $issues === [],
            'issues' => $issues,
            'message' => $issues[0] ?? null,
            'payment_methods' => $this->paymentMethods($isB2b),
            'invoice_profile' => $this->invoiceProfile($user, $client),
            'terms_snapshot' => $isB2b && $client ? $this->snapshot($client) : null,
        ];
    }

    /** @return list<string> */
    private function paymentMethods(bool $isB2b): array
    {
        $methods = $isB2b
            ? config('shop.b2b_checkout_payment_methods', ['invoice', 'cash'])
            : config('shop.checkout_payment_methods', []);
        $known = array_keys(config('shop.payment_methods', []));

        return collect($methods)
            ->map(fn ($method): string => (string) $method)
            ->filter(fn (string $method): bool => in_array($method, $known, true))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{company_name:string,company_unp:string,company_address:string,company_email:string} */
    private function invoiceProfile(?User $user, ?B2bClient $client): array
    {
        $counterparty = $client?->counterparty;

        return [
            'company_name' => (string) ($counterparty?->legal_name ?: $client?->company_name ?: ''),
            'company_unp' => (string) ($counterparty?->unp ?: $client?->unp ?: ''),
            'company_address' => (string) ($counterparty?->legal_address ?: ''),
            'company_email' => (string) ($client?->contact_email ?: $user?->email ?: ''),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(B2bClient $client): array
    {
        return [
            'b2b_client_id' => (int) $client->id,
            'price_level_id' => $client->b2b_price_level_id ? (int) $client->b2b_price_level_id : null,
            'price_level_code' => $client->priceLevel?->code,
            'price_level_name' => $client->priceLevel?->name,
            'service_mode' => $client->service_mode,
            'service_mode_name' => $client->serviceModeLabel(),
            'payment_terms' => $client->payment_terms,
            'delivery_terms' => $client->delivery_terms,
            'minimum_order_amount' => $client->minimum_order_amount !== null
                ? (float) $client->minimum_order_amount
                : null,
            'payment_deferment_days' => $client->payment_deferment_days,
            'credit_limit' => $client->credit_limit !== null ? (float) $client->credit_limit : null,
            'valid_until' => $client->commercial_terms_valid_until?->toDateString(),
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
