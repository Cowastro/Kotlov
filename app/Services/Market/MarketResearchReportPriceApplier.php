<?php

namespace App\Services\Market;

use App\Models\ActivityLog;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketResearchReportPriceApplier
{
    /** @return array{product: Product, report: MarketResearchReport, audit: ActivityLog} */
    public function apply(
        MarketResearchReport $report,
        User $actor,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Применять цену из анализа может только администратор.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Укажите осмысленную причину изменения цены (не менее 10 символов).',
            ]);
        }

        return DB::transaction(function () use ($report, $actor, $reason, $ipAddress, $userAgent): array {
            /** @var MarketResearchReport $lockedReport */
            $lockedReport = MarketResearchReport::query()->lockForUpdate()->findOrFail($report->getKey());
            $recommendedPrice = round((float) $lockedReport->recommended_price, 2);

            if ($recommendedPrice <= 0) {
                throw ValidationException::withMessages([
                    'price' => 'В этом анализе нет корректной рекомендованной цены.',
                ]);
            }

            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($lockedReport->product_id);
            $oldPrice = round((float) $product->price, 2);

            if (abs($oldPrice - $recommendedPrice) < 0.01) {
                throw ValidationException::withMessages([
                    'price' => 'Рекомендованная цена уже установлена на сайте.',
                ]);
            }

            $oldValues = [
                'price' => $oldPrice,
                'price_old' => $product->price_old !== null ? (float) $product->price_old : null,
                'requires_price_confirmation' => (bool) $product->requires_price_confirmation,
            ];
            $newOldPrice = $product->price_old !== null && (float) $product->price_old <= $recommendedPrice
                ? null
                : $product->price_old;

            $product->forceFill([
                'price' => $recommendedPrice,
                'price_old' => $newOldPrice,
                'requires_price_confirmation' => false,
            ])->save();

            $lockedReport->forceFill([
                'decision' => 'price_applied',
                'decision_reason' => $reason,
                'price_after' => $recommendedPrice,
                'requires_price_confirmation' => false,
                'applied_at' => now(),
            ])->save();

            $audit = ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'market_research_report_price_applied',
                'model_type' => Product::class,
                'model_id' => $product->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'price' => $recommendedPrice,
                    'price_old' => $newOldPrice !== null ? (float) $newOldPrice : null,
                    'requires_price_confirmation' => false,
                    'reason' => $reason,
                    'market_research_report_id' => $lockedReport->id,
                    'batch_code' => $lockedReport->batch_code,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return [
                'product' => $product->fresh(),
                'report' => $lockedReport->fresh(),
                'audit' => $audit,
            ];
        });
    }
}
