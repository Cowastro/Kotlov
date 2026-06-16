<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierReviewDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplySupplierReviewDecisionsCommand extends Command
{
    protected $signature = 'supplier:apply-review-decisions
        {--dry-run : Show pending decisions without changing data}
        {--apply : Apply pending decisions}
        {--list : Show pending decisions and exit}
        {--id=* : Limit to one or more decision IDs}
        {--delete=* : Delete one or more pending decision IDs}
        {--supplier= : Limit to supplier code}
        {--limit= : Maximum decisions to process}';

    protected $description = 'Apply queued manual supplier review decisions safely.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = $this->integerListOption('id');
        $deleteIds = $this->integerListOption('delete');

        if (! $apply) {
            $this->warn('DRY RUN: database will not be changed. Use --apply to apply decisions.');
        }

        if ($deleteIds !== []) {
            return $this->deleteDecisions($deleteIds, $apply);
        }

        $query = SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query->where('supplier_code', $supplier);
        }

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $decisions = $query->get();
        if ($decisions->isEmpty()) {
            $this->info('No pending supplier review decisions.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('list')) {
            $this->renderDecisionTable($decisions, 'pending');
            return self::SUCCESS;
        }

        if ($ids === [] && $this->hasRetailPriceConflicts($decisions)) {
            $this->error('There are conflicting pending retail price decisions for the same product.');
            $this->warn('Delete the wrong decision first, for example: supplier:apply-review-decisions --apply --delete=2');
            $this->renderRetailPriceConflicts($decisions);
            return self::FAILURE;
        }

        $rows = [];
        $metrics = [
            'pending' => $decisions->count(),
            'linked' => 0,
            'unlinked' => 0,
            'retail_updated' => 0,
            'ignored' => 0,
            'failed' => 0,
        ];

        foreach ($decisions as $decision) {
            try {
                $note = $this->applyDecision($decision, $apply);
                $metrics[$this->metricName($decision->decision)]++;

                $rows[] = [
                    $decision->id,
                    $decision->supplier_code ?: '-',
                    $this->decisionLabel($decision->decision),
                    $decision->supplier_product_id ?: '-',
                    $decision->product_id ?: '-',
                    $apply ? SupplierReviewDecision::STATUS_APPLIED : 'dry_run',
                    $note,
                ];
            } catch (Throwable $exception) {
                $metrics['failed']++;
                $rows[] = [
                    $decision->id,
                    $decision->supplier_code ?: '-',
                    $this->decisionLabel($decision->decision),
                    $decision->supplier_product_id ?: '-',
                    $decision->product_id ?: '-',
                    'failed',
                    $exception->getMessage(),
                ];

                if ($apply) {
                    $decision->forceFill([
                        'status' => SupplierReviewDecision::STATUS_FAILED,
                        'error' => $exception->getMessage(),
                    ])->save();
                }
            }
        }

        $this->table(
            ['id', 'supplier', 'decision', 'supplier_product_id', 'product_id', 'status', 'note'],
            $rows
        );

        $this->table(['metric', 'count'], collect($metrics)->map(fn (int $count, string $metric): array => [
            'metric' => $metric,
            'count' => $count,
        ])->values()->all());

        return $metrics['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function deleteDecisions(array $ids, bool $apply): int
    {
        $decisions = SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($decisions->isEmpty()) {
            $this->warn('No pending decisions found for IDs: ' . implode(', ', $ids));
            return self::SUCCESS;
        }

        $this->renderDecisionTable($decisions, $apply ? 'deleted' : 'would_delete');

        if ($apply) {
            SupplierReviewDecision::query()
                ->whereIn('id', $decisions->pluck('id')->all())
                ->delete();
            $this->info('Deleted pending decisions: ' . $decisions->pluck('id')->implode(', '));
        } else {
            $this->warn('Use --apply with --delete to actually delete these decisions.');
        }

        return self::SUCCESS;
    }

    private function renderDecisionTable($decisions, string $status): void
    {
        $this->table(
            ['id', 'supplier', 'decision', 'supplier_product_id', 'product_id', 'status', 'price', 'report_row', 'title'],
            $decisions->map(function (SupplierReviewDecision $decision) use ($status): array {
                $payload = is_array($decision->payload) ? $decision->payload : [];
                $row = is_array($payload['row'] ?? null) ? $payload['row'] : [];

                return [
                    $decision->id,
                    $decision->supplier_code ?: '-',
                    $this->decisionLabel($decision->decision),
                    $decision->supplier_product_id ?: '-',
                    $decision->product_id ?: '-',
                    $status,
                    $payload['manual_retail_price'] ?? '-',
                    $decision->report_row ?: ($row['price_row'] ?? '-'),
                    $decision->supplier_title ?: ($row['price_title'] ?? '-'),
                ];
            })->all()
        );
    }

    private function hasRetailPriceConflicts($decisions): bool
    {
        return $decisions
            ->filter(fn (SupplierReviewDecision $decision): bool => $decision->decision === SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE && $decision->product_id !== null)
            ->groupBy('product_id')
            ->contains(function ($group): bool {
                return $group
                    ->map(fn (SupplierReviewDecision $decision): ?string => $this->decisionRetailPrice($decision))
                    ->filter()
                    ->unique()
                    ->count() > 1;
            });
    }

    private function renderRetailPriceConflicts($decisions): void
    {
        $rows = [];
        $decisions
            ->filter(fn (SupplierReviewDecision $decision): bool => $decision->decision === SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE && $decision->product_id !== null)
            ->groupBy('product_id')
            ->each(function ($group, $productId) use (&$rows): void {
                if ($group
                    ->map(fn (SupplierReviewDecision $decision): ?string => $this->decisionRetailPrice($decision))
                    ->filter()
                    ->unique()
                    ->count() <= 1) {
                    return;
                }

                foreach ($group as $decision) {
                    $payload = is_array($decision->payload) ? $decision->payload : [];
                    $row = is_array($payload['row'] ?? null) ? $payload['row'] : [];
                    $rows[] = [
                        $decision->id,
                        $productId,
                        $decision->supplier_product_id ?: '-',
                        $payload['manual_retail_price'] ?? '-',
                        $row['price_article'] ?? '-',
                        $row['price_title'] ?? $decision->supplier_title ?? '-',
                    ];
                }
            });

        if ($rows !== []) {
            $this->table(['id', 'product_id', 'supplier_product_id', 'retail_price', 'price_article', 'title'], $rows);
        }
    }

    private function decisionRetailPrice(SupplierReviewDecision $decision): ?string
    {
        $payload = is_array($decision->payload) ? $decision->payload : [];
        $price = trim((string) ($payload['manual_retail_price'] ?? ''));

        return $price !== '' ? $price : null;
    }

    private function applyDecision(SupplierReviewDecision $decision, bool $apply): string
    {
        return match ($decision->decision) {
            SupplierReviewDecision::DECISION_LINK => $this->linkSupplierProduct($decision, $apply),
            SupplierReviewDecision::DECISION_UNLINK => $this->unlinkSupplierProduct($decision, $apply),
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => $this->updateRetailPrice($decision, $apply),
            SupplierReviewDecision::DECISION_IGNORE => $this->ignoreDecision($decision, $apply),
            default => throw new \RuntimeException("Unknown decision: {$decision->decision}"),
        };
    }

    private function integerListOption(string $name): array
    {
        $values = (array) $this->option($name);
        $ids = [];

        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function linkSupplierProduct(SupplierReviewDecision $decision, bool $apply): string
    {
        if (! $decision->supplier_product_id || ! $decision->product_id) {
            throw new \RuntimeException('Для связки нужен supplier_product_id и product_id.');
        }

        $supplierProduct = DB::table('supplier_products')->where('id', $decision->supplier_product_id)->first();
        if (! $supplierProduct) {
            throw new \RuntimeException('Связка поставщика не найдена.');
        }

        $product = DB::table('products')->where('id', $decision->product_id)->first(['id', 'sku']);
        if (! $product) {
            throw new \RuntimeException('Товар KOTLOV не найден.');
        }

        $oldProductId = $supplierProduct->product_id ? (int) $supplierProduct->product_id : null;
        $note = $oldProductId && $oldProductId !== (int) $product->id
            ? "будет перепривязано с product_id {$oldProductId}"
            : 'будет привязано';

        if (! $apply) {
            return $note;
        }

        DB::transaction(function () use ($decision, $product, $oldProductId): void {
            DB::table('supplier_products')->where('id', $decision->supplier_product_id)->update([
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'updated_at' => now(),
            ]);

            $this->refreshProductAvailability((int) $product->id);
            if ($oldProductId && $oldProductId !== (int) $product->id) {
                $this->refreshProductAvailability($oldProductId);
            }

            $this->markApplied($decision);
        });

        return 'связано';
    }

    private function unlinkSupplierProduct(SupplierReviewDecision $decision, bool $apply): string
    {
        if (! $decision->supplier_product_id) {
            throw new \RuntimeException('Для удаления связи нужен supplier_product_id.');
        }

        $supplierProduct = DB::table('supplier_products')->where('id', $decision->supplier_product_id)->first();
        if (! $supplierProduct) {
            throw new \RuntimeException('Связка поставщика не найдена.');
        }

        $oldProductId = $supplierProduct->product_id ? (int) $supplierProduct->product_id : null;
        if (! $apply) {
            return $oldProductId ? "будет отвязано от product_id {$oldProductId}" : 'уже без product_id';
        }

        DB::transaction(function () use ($decision, $oldProductId): void {
            DB::table('supplier_products')->where('id', $decision->supplier_product_id)->update([
                'product_id' => null,
                'product_sku' => null,
                'updated_at' => now(),
            ]);

            if ($oldProductId) {
                $this->refreshProductAvailability($oldProductId);
            }

            $this->markApplied($decision);
        });

        return 'связь удалена';
    }

    private function ignoreDecision(SupplierReviewDecision $decision, bool $apply): string
    {
        if ($apply) {
            $this->markApplied($decision);
        }

        return 'строка отмечена как проверенная без изменений';
    }

    private function updateRetailPrice(SupplierReviewDecision $decision, bool $apply): string
    {
        if (! $decision->product_id) {
            throw new \RuntimeException('Для обновления розницы нужен product_id.');
        }

        $payload = is_array($decision->payload) ? $decision->payload : [];
        $newPrice = $this->parseRetailPrice((string) ($payload['manual_retail_price'] ?? ''));
        if ($newPrice === null || $newPrice <= 0) {
            throw new \RuntimeException('Новая розничная цена пустая или некорректная.');
        }

        $product = DB::table('products')->where('id', $decision->product_id)->first(['id', 'price']);
        if (! $product) {
            throw new \RuntimeException('Товар KOTLOV не найден.');
        }

        $oldPrice = $product->price !== null ? (float) $product->price : null;
        $note = sprintf(
            'розница %s -> %.2f',
            $oldPrice !== null ? number_format($oldPrice, 2, '.', '') : 'NULL',
            $newPrice
        );

        if (! $apply) {
            return $note;
        }

        DB::transaction(function () use ($decision, $newPrice): void {
            DB::table('products')->where('id', $decision->product_id)->update([
                'price' => $newPrice,
                'updated_at' => now(),
            ]);

            $this->markApplied($decision);
        });

        return 'розница обновлена: ' . number_format($newPrice, 2, '.', '');
    }

    private function markApplied(SupplierReviewDecision $decision): void
    {
        $decision->forceFill([
            'status' => SupplierReviewDecision::STATUS_APPLIED,
            'applied_at' => now(),
            'error' => null,
        ])->save();
    }

    private function refreshProductAvailability(int $productId): void
    {
        $inStock = DB::table('supplier_products')
            ->where('product_id', $productId)
            ->where('in_stock', true)
            ->exists();

        DB::table('products')->where('id', $productId)->update([
            'in_stock' => $inStock,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'updated_at' => now(),
        ]);
    }

    private function metricName(string $decision): string
    {
        return match ($decision) {
            SupplierReviewDecision::DECISION_LINK => 'linked',
            SupplierReviewDecision::DECISION_UNLINK => 'unlinked',
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => 'retail_updated',
            SupplierReviewDecision::DECISION_IGNORE => 'ignored',
            default => 'failed',
        };
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            SupplierReviewDecision::DECISION_LINK => 'связать',
            SupplierReviewDecision::DECISION_UNLINK => 'удалить связь',
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => 'обновить розницу',
            SupplierReviewDecision::DECISION_IGNORE => 'игнорировать',
            default => $decision,
        };
    }

    private function parseRetailPrice(string $value): ?float
    {
        $normalized = trim(str_replace(["\xc2\xa0", 'BYN', 'byn', ' '], '', $value));
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }
}
