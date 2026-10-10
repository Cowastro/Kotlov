<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\SupplierChannelTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SupplierChannelTransitionPlanner
{
    /** @return array<string, mixed> */
    public function preview(IntegrationSource $source): array
    {
        if (! $source->supplier_id) {
            throw new InvalidArgumentException('Сначала назначьте поставщика-владельца источника.');
        }

        $legacyProductIds = DB::table('supplier_products')
            ->where('supplier_id', $source->supplier_id)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        $matchedQuery = DB::table('integration_products')
            ->where('integration_source_id', $source->id)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id');
        $integrationProductIds = (clone $matchedQuery)->distinct()->pluck('product_id');

        $shared = $legacyProductIds->intersect($integrationProductIds)->count();
        $legacyOnly = $legacyProductIds->diff($integrationProductIds)->count();
        $integrationOnly = $integrationProductIds->diff($legacyProductIds)->count();
        $unmatchedInStock = DB::table('integration_products')
            ->where('integration_source_id', $source->id)
            ->where('stock_quantity', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('product_id')->orWhere('match_status', '!=', 'matched');
            })
            ->count();
        $missingPriceInStock = DB::table('integration_products')
            ->where('integration_source_id', $source->id)
            ->where('stock_quantity', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('price')->orWhere('price', '<=', 0);
            })
            ->count();
        $duplicateTargets = DB::query()
            ->fromSub(
                (clone $matchedQuery)
                    ->select('product_id')
                    ->groupBy('product_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_targets',
            )
            ->count();

        $blockers = [];
        if (! $source->is_active) {
            $blockers[] = 'Источник 1С выключен.';
        }
        if ($legacyOnly > 0) {
            $blockers[] = "{$legacyOnly} карточек старого канала ещё не покрыты подтверждёнными привязками 1С.";
        }
        if ($unmatchedInStock > 0) {
            $blockers[] = "{$unmatchedInStock} товаров 1С в наличии ещё не привязаны.";
        }
        if ($missingPriceInStock > 0) {
            $blockers[] = "{$missingPriceInStock} товаров 1С в наличии не имеют положительной цены.";
        }
        if ($duplicateTargets > 0) {
            $blockers[] = "{$duplicateTargets} карточек сайта связаны более чем с одним товаром этого источника.";
        }

        return [
            'legacy_links' => DB::table('supplier_products')->where('supplier_id', $source->supplier_id)->count(),
            'legacy_products' => $legacyProductIds->count(),
            'integration_items' => DB::table('integration_products')->where('integration_source_id', $source->id)->count(),
            'matched_items' => (clone $matchedQuery)->count(),
            'matched_products' => $integrationProductIds->count(),
            'shared_products' => $shared,
            'legacy_only_products' => $legacyOnly,
            'integration_only_products' => $integrationOnly,
            'unmatched_in_stock' => $unmatchedInStock,
            'missing_price_in_stock' => $missingPriceInStock,
            'duplicate_target_products' => $duplicateTargets,
            'blockers' => $blockers,
            'can_start_control_exchange' => $blockers === [],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function recordPreview(IntegrationSource $source, ?int $userId): SupplierChannelTransition
    {
        $snapshot = $this->preview($source);

        return SupplierChannelTransition::query()->create([
            'supplier_id' => $source->supplier_id,
            'integration_source_id' => $source->id,
            'previewed_by' => $userId,
            'status' => SupplierChannelTransition::STATUS_PREVIEWED,
            'snapshot' => $snapshot,
        ]);
    }

    public function summary(IntegrationSource $source): string
    {
        if (! $source->supplier_id) {
            return 'Поставщик-владелец не назначен.';
        }

        $preview = $this->preview($source);

        return implode(' · ', [
            'старый канал: '.number_format($preview['legacy_products'], 0, ',', ' '),
            'привязано в 1С: '.number_format($preview['matched_products'], 0, ',', ' '),
            'совпадают: '.number_format($preview['shared_products'], 0, ',', ' '),
            'блокировок: '.number_format(count($preview['blockers']), 0, ',', ' '),
        ]);
    }

    public function refreshLatestReadiness(IntegrationSource $source): ?SupplierChannelTransition
    {
        if (! Schema::hasTable('supplier_channel_transitions')) {
            return null;
        }

        $transition = SupplierChannelTransition::query()
            ->where('integration_source_id', $source->id)
            ->where('status', SupplierChannelTransition::STATUS_PREVIEWED)
            ->latest('id')
            ->first();

        if (! $transition || $this->preview($source)['blockers'] !== []) {
            return $transition;
        }

        $controlRun = $source->exchangeRuns()
            ->where('operation', 'catalog')
            ->where('status', 'success')
            ->where('finished_at', '>', $transition->created_at)
            ->orderByDesc('finished_at')
            ->get()
            ->first(fn ($run): bool => filled(data_get($run->summary, 'stock_snapshot.completed_at')));

        if (! $controlRun) {
            return $transition;
        }

        $transition->update([
            'status' => SupplierChannelTransition::STATUS_READY,
            'control_exchange_run_id' => $controlRun->id,
            'ready_at' => now(),
        ]);

        return $transition->fresh();
    }

    public function latestStatusLabel(IntegrationSource $source): string
    {
        $transition = SupplierChannelTransition::query()
            ->where('integration_source_id', $source->id)
            ->latest('id')
            ->first();

        if (! $transition) {
            return 'Проверка ещё не сохранялась.';
        }

        $date = $transition->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i');

        return match ($transition->status) {
            SupplierChannelTransition::STATUS_READY => "Контрольный обмен пройден · {$date}",
            SupplierChannelTransition::STATUS_LEGACY_DISABLED => "Старый канал отключён · {$date}",
            default => "Предпросмотр сохранён · {$date}",
        };
    }
}
