<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;

class IntegrationOrderStatusMapper
{
    /** @var array<string, string> */
    public const PAYMENT_STATUSES = [
        'pending' => 'Ожидает оплаты',
        'paid' => 'Оплачен',
        'refunded' => 'Возврат',
    ];

    public function orderStatus(string $status, ?IntegrationSource $source = null): ?string
    {
        $configured = $this->configuredTarget(
            $status,
            data_get($source?->settings, 'order_status_rules', []),
            array_keys(Order::STATUSES),
        );

        if ($configured !== null) {
            return $configured;
        }

        $status = $this->normalize($status);

        return match (true) {
            $status === '' => null,
            str_contains($status, 'отмен') => 'cancelled',
            str_contains($status, 'достав'), str_contains($status, 'выполн'), str_contains($status, 'заверш') => 'delivered',
            str_contains($status, 'отгруж'), str_contains($status, 'отправ') => 'shipped',
            str_contains($status, 'обработ'), str_contains($status, 'сбор'), str_contains($status, 'комплект') => 'processing',
            str_contains($status, 'подтверж'), str_contains($status, 'принят') => 'confirmed',
            str_contains($status, 'нов') => 'new',
            default => null,
        };
    }

    public function paymentStatus(string $status, ?IntegrationSource $source = null): ?string
    {
        $configured = $this->configuredTarget(
            $status,
            data_get($source?->settings, 'payment_status_rules', []),
            array_keys(self::PAYMENT_STATUSES),
        );

        if ($configured !== null) {
            return $configured;
        }

        $status = $this->normalize($status);

        return match (true) {
            $status === '' => null,
            str_contains($status, 'не опла'), str_contains($status, 'ожида') => 'pending',
            str_contains($status, 'возврат') => 'refunded',
            str_contains($status, 'опла') => 'paid',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $allowedTargets
     */
    private function configuredTarget(string $status, mixed $rules, array $allowedTargets): ?string
    {
        $normalizedStatus = $this->normalize($status);
        if ($normalizedStatus === '' || ! is_array($rules)) {
            return null;
        }

        foreach ($this->normalizedRules($rules) as $source => $target) {
            if ($this->normalize($source) === $normalizedStatus && in_array($target, $allowedTargets, true)) {
                return $target;
            }
        }

        return null;
    }

    /** @param array<mixed> $rules @return array<string, string> */
    private function normalizedRules(array $rules): array
    {
        if (! array_is_list($rules)) {
            return collect($rules)
                ->filter(fn (mixed $target, mixed $source): bool => is_string($source) && is_string($target))
                ->mapWithKeys(fn (string $target, string $source): array => [$source => $target])
                ->all();
        }

        return collect($rules)
            ->filter(fn (mixed $rule): bool => is_array($rule)
                && is_string($rule['source'] ?? null)
                && is_string($rule['target'] ?? null))
            ->mapWithKeys(fn (array $rule): array => [$rule['source'] => $rule['target']])
            ->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
