<?php

namespace App\Services\Orders;

use App\Models\IntegrationSource;
use App\Models\PurchasePlan;
use App\Models\PurchasePlanStatusHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasePlanManager
{
    /** @return array{plan:PurchasePlan,created:bool} */
    public function createDraft(
        Collection $recommendations,
        IntegrationSource $source,
        int $periodDays,
        ?User $user,
    ): array {
        $items = $recommendations
            ->filter(fn (array $row): bool => ($row['recommended_purchase'] ?? 0) > 0
                && ($row['stock_data_ready'] ?? false) === true)
            ->map(fn (array $row): array => [
                'product_id' => (int) $row['product_id'],
                'integration_product_id' => $row['stock_integration_product_id'],
                'product_sku' => filled($row['sku'] ?? null) ? (string) $row['sku'] : null,
                'product_name' => (string) $row['name'],
                'current_stock' => (float) $row['current_own_stock'],
                'target_stock' => (int) $row['target_stock'],
                'recommended_quantity' => (int) $row['recommended_purchase'],
                'planned_quantity' => (int) $row['recommended_purchase'],
                'unit_purchase_price' => $row['unit_purchase_price'] !== null
                    ? (float) $row['unit_purchase_price']
                    : null,
                'purchase_total' => $row['unit_purchase_price'] !== null
                    ? round((float) $row['unit_purchase_price'] * (int) $row['recommended_purchase'], 2)
                    : null,
                'price_tax_mode' => $row['price_tax_mode'],
                'vat_rate' => $row['vat_rate'],
                'stock_confirmed_at' => $row['stock_confirmed_at'],
                'explanation' => (string) $row['explanation'],
            ])
            ->sortBy('product_id')
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'selection' => 'Выберите хотя бы одну позицию с подтверждённым остатком и рекомендацией к пополнению.',
            ]);
        }

        $snapshotHash = hash('sha256', json_encode([
            'source' => $source->id,
            'period' => $periodDays,
            'items' => $items->map(fn (array $item): array => [
                $item['product_id'],
                $item['current_stock'],
                $item['target_stock'],
                $item['planned_quantity'],
            ])->all(),
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));

        $existing = PurchasePlan::query()->where('snapshot_hash', $snapshotHash)->first();
        if ($existing) {
            return ['plan' => $existing, 'created' => false];
        }

        return DB::transaction(function () use ($items, $source, $periodDays, $user, $snapshotHash): array {
            $missingPriceCount = $items->whereNull('unit_purchase_price')->count();
            $knownPurchaseTotal = round((float) $items->sum('purchase_total'), 2);

            $plan = PurchasePlan::query()->create([
                'number' => 'PUR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'status' => 'draft',
                'integration_source_id' => $source->id,
                'source_code' => $source->code,
                'source_name' => $source->name,
                'period_days' => $periodDays,
                'items_count' => $items->count(),
                'total_quantity' => $items->sum('planned_quantity'),
                'known_purchase_total' => $knownPurchaseTotal,
                'purchase_total' => $missingPriceCount === 0 ? $knownPurchaseTotal : null,
                'missing_price_count' => $missingPriceCount,
                'snapshot_hash' => $snapshotHash,
                'created_by' => $user?->id,
            ]);

            $plan->items()->createMany($items->all());
            $this->recordStatus($plan, null, 'draft', $user, 'Создан из подтверждённой аналитики спроса и остатка.');

            return ['plan' => $plan->load('items'), 'created' => true];
        });
    }

    public function confirm(PurchasePlan $plan, ?User $user): PurchasePlan
    {
        if ($plan->status !== 'draft') {
            throw ValidationException::withMessages(['plan' => 'Подтвердить можно только черновик плана.']);
        }

        $source = IntegrationSource::query()->find($plan->integration_source_id);
        if (! $source) {
            throw ValidationException::withMessages(['plan' => 'Источник плана больше недоступен. Создайте новый план.']);
        }

        $fresh = app(OrderStockRecommendationService::class)
            ->recommendations($plan->period_days, $source->code)
            ->keyBy('product_id');

        foreach ($plan->items as $item) {
            $row = $fresh->get($item->product_id);
            $unchanged = $row
                && ($row['stock_data_ready'] ?? false) === true
                && (int) ($row['recommended_purchase'] ?? -1) === $item->planned_quantity
                && abs((float) ($row['current_own_stock'] ?? -1) - (float) $item->current_stock) < 0.0005;

            if (! $unchanged) {
                throw ValidationException::withMessages([
                    'plan' => "Данные по позиции «{$item->product_name}» изменились. Создайте новый черновик по свежим остаткам.",
                ]);
            }
        }

        return DB::transaction(function () use ($plan, $user): PurchasePlan {
            $plan->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => $user?->id,
                'confirmed_at' => now(),
            ])->save();
            $this->recordStatus($plan, 'draft', 'confirmed', $user, 'План подтверждён менеджером. Остатки не изменялись.');

            return $plan->refresh();
        });
    }

    public function cancel(PurchasePlan $plan, ?User $user): PurchasePlan
    {
        if ($plan->status !== 'draft') {
            throw ValidationException::withMessages(['plan' => 'Отменить можно только черновик плана.']);
        }

        return DB::transaction(function () use ($plan, $user): PurchasePlan {
            $plan->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $user?->id,
                'cancelled_at' => now(),
            ])->save();
            $this->recordStatus($plan, 'draft', 'cancelled', $user, 'Черновик отменён без изменения остатков.');

            return $plan->refresh();
        });
    }

    private function recordStatus(
        PurchasePlan $plan,
        ?string $from,
        string $to,
        ?User $user,
        string $comment,
    ): void {
        PurchasePlanStatusHistory::query()->create([
            'purchase_plan_id' => $plan->id,
            'user_id' => $user?->id,
            'status_from' => $from,
            'status_to' => $to,
            'comment' => $comment,
        ]);
    }
}
