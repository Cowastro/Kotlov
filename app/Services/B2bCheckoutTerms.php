<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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

        $credit = $client ? $this->creditSummary($client) : $this->emptyCreditSummary();

        return [
            'is_b2b' => $isB2b,
            'client' => $client,
            'b2b_subtotal' => $subtotal,
            'minimum_order_amount' => $minimum,
            'minimum_shortfall' => $shortfall,
            'can_checkout' => $issues === [],
            'issues' => $issues,
            'message' => $issues[0] ?? null,
            'payment_methods' => $this->paymentMethods($isB2b, $credit, $subtotal),
            'payment_method_overrides' => $this->paymentMethodOverrides($credit),
            'invoice_profile' => $this->invoiceProfile($user, $client),
            'terms_snapshot' => $isB2b && $client ? $this->snapshot($client) : null,
            'credit' => $credit,
        ];
    }

    /** @return array{enabled:bool,deferment_days:int,limit:?float,exposure:float,available:?float,issue:?string} */
    public function creditSummary(B2bClient $client): array
    {
        $days = max(0, (int) $client->payment_deferment_days);
        $limit = $client->credit_limit !== null ? max(0, (float) $client->credit_limit) : null;
        $exposure = $days > 0 && $limit !== null
            ? round((float) Order::query()
                ->where('user_id', $client->user_id)
                ->where('payment_type', 'invoice_deferred')
                ->whereNotIn('payment_status', ['paid', 'refunded'])
                ->where('status', '!=', 'cancelled')
                ->sum('total'), 2)
            : 0.0;
        $available = $limit !== null ? max(0, round($limit - $exposure, 2)) : null;
        $issue = match (true) {
            $days <= 0 => null,
            $limit === null || $limit <= 0 => 'Для отсрочки не установлен положительный кредитный лимит.',
            $available <= 0 => 'Кредитный лимит использован. Доступна предоплата по счёту.',
            default => null,
        };

        return [
            'enabled' => $days > 0 && $limit !== null && $limit > 0 && $available > 0,
            'deferment_days' => $days,
            'limit' => $limit,
            'exposure' => $exposure,
            'available' => $available,
            'issue' => $issue,
        ];
    }

    /**
     * Must be called inside the order transaction. Locking the client row
     * serializes concurrent deferred orders for the same company.
     */
    /** @return array{enabled:bool,deferment_days:int,limit:?float,exposure:float,available:?float,issue:?string} */
    public function assertDeferredPaymentAvailable(User $user, float $orderTotal): array
    {
        $client = B2bClient::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
        $credit = $client ? $this->creditSummary($client) : $this->emptyCreditSummary();
        $available = (float) ($credit['available'] ?? 0);

        if (! $credit['enabled'] || $orderTotal > $available + 0.005) {
            $message = $credit['issue']
                ?: 'Сумма заказа превышает доступный кредитный лимит на '
                    .number_format(max(0, $orderTotal - $available), 2, ',', ' ').' BYN. Выберите предоплату по счёту.';

            throw ValidationException::withMessages(['payment_type' => $message]);
        }

        return $credit;
    }

    /**
     * @param  array{enabled:bool,deferment_days:int,limit:?float,exposure:float,available:?float,issue:?string}  $credit
     * @return list<string>
     */
    private function paymentMethods(bool $isB2b, array $credit, float $b2bSubtotal): array
    {
        $methods = $isB2b
            ? config('shop.b2b_checkout_payment_methods', ['invoice', 'cash'])
            : config('shop.checkout_payment_methods', []);
        $known = array_keys(config('shop.payment_methods', []));

        return collect($methods)
            ->map(fn ($method): string => (string) $method)
            ->reject(fn (string $method): bool => $method === 'invoice_deferred'
                && (! $credit['enabled'] || $b2bSubtotal > (float) $credit['available'] + 0.005))
            ->filter(fn (string $method): bool => in_array($method, $known, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{enabled:bool,deferment_days:int,limit:?float,exposure:float,available:?float,issue:?string}  $credit
     * @return array<string, array{name:string,desc:string}>
     */
    private function paymentMethodOverrides(array $credit): array
    {
        $available = number_format((float) ($credit['available'] ?? 0), 2, ',', ' ');

        return [
            'invoice' => [
                'name' => 'По счёту — предоплата',
                'desc' => 'Счёт после проверки наличия менеджером; отгрузка после оплаты.',
            ],
            'invoice_deferred' => [
                'name' => 'По счёту с отсрочкой '.$credit['deferment_days'].' дней',
                'desc' => 'Доступный кредитный лимит: '.$available.' BYN.',
            ],
        ];
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

    /** @return array{enabled:bool,deferment_days:int,limit:?float,exposure:float,available:?float,issue:?string} */
    private function emptyCreditSummary(): array
    {
        return [
            'enabled' => false,
            'deferment_days' => 0,
            'limit' => null,
            'exposure' => 0.0,
            'available' => null,
            'issue' => null,
        ];
    }
}
