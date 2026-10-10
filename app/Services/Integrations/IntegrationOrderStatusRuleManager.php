<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IntegrationOrderStatusRuleManager
{
    public function store(
        IntegrationSource $source,
        ?string $rawOrderStatus,
        ?string $orderTarget,
        ?string $rawPaymentStatus,
        ?string $paymentTarget,
    ): IntegrationSource {
        $this->validatePair($rawOrderStatus, $orderTarget, array_keys(Order::STATUSES), 'статуса заказа');
        $this->validatePair(
            $rawPaymentStatus,
            $paymentTarget,
            array_keys(IntegrationOrderStatusMapper::PAYMENT_STATUSES),
            'статуса оплаты',
        );

        return DB::transaction(function () use (
            $source,
            $rawOrderStatus,
            $orderTarget,
            $rawPaymentStatus,
            $paymentTarget,
        ): IntegrationSource {
            $locked = IntegrationSource::query()->lockForUpdate()->findOrFail($source->id);
            $settings = $locked->settings ?? [];

            if (filled($rawOrderStatus) && filled($orderTarget)) {
                $settings['order_status_rules'] = $this->upsertRule(
                    data_get($settings, 'order_status_rules', []),
                    $rawOrderStatus,
                    $orderTarget,
                );
            }
            if (filled($rawPaymentStatus) && filled($paymentTarget)) {
                $settings['payment_status_rules'] = $this->upsertRule(
                    data_get($settings, 'payment_status_rules', []),
                    $rawPaymentStatus,
                    $paymentTarget,
                );
            }

            $locked->update(['settings' => $settings]);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, string>  $allowedTargets
     */
    private function validatePair(?string $raw, ?string $target, array $allowedTargets, string $label): void
    {
        if (blank($raw) && blank($target)) {
            return;
        }

        if (blank($raw) || blank($target) || ! in_array($target, $allowedTargets, true)) {
            throw new InvalidArgumentException('Некорректное правило '.$label.'.');
        }
    }

    /** @return array<int, array{source:string,target:string}> */
    private function upsertRule(mixed $rules, string $raw, string $target): array
    {
        $normalizedRaw = $this->normalize($raw);
        $prepared = collect($this->toRuleList($rules))
            ->reject(fn (array $rule): bool => $this->normalize($rule['source']) === $normalizedRaw)
            ->values()
            ->all();
        $prepared[] = ['source' => trim($raw), 'target' => $target];

        return array_values($prepared);
    }

    /** @return array<int, array{source:string,target:string}> */
    private function toRuleList(mixed $rules): array
    {
        if (! is_array($rules)) {
            return [];
        }

        if (! array_is_list($rules)) {
            return collect($rules)
                ->filter(fn (mixed $target, mixed $source): bool => is_string($source) && is_string($target))
                ->map(fn (string $target, string $source): array => ['source' => $source, 'target' => $target])
                ->values()
                ->all();
        }

        return collect($rules)
            ->filter(fn (mixed $rule): bool => is_array($rule)
                && is_string($rule['source'] ?? null)
                && is_string($rule['target'] ?? null))
            ->map(fn (array $rule): array => [
                'source' => $rule['source'],
                'target' => $rule['target'],
            ])
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
