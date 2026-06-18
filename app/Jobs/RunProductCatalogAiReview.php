<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SupplierReviewDecision;
use App\Models\User;
use App\Services\ProductCatalogAiAdvisor;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RunProductCatalogAiReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param array<int> $productIds
     */
    public function __construct(
        private array $productIds,
        private int $userId,
        private bool $useAi = true,
    ) {}

    public function handle(ProductCatalogAiAdvisor $advisor): void
    {
        $user = User::query()->find($this->userId);
        $products = Product::query()
            ->whereKey($this->productIds)
            ->with(['brand', 'category', 'supplierProducts.supplier'])
            ->get();

        $processed = 0;
        $queued = 0;
        $duplicates = 0;
        $unchanged = 0;
        $lines = [];

        foreach ($products as $product) {
            $processed++;
            $advice = $advisor->advise($product, $this->useAi);
            $changes = is_array($advice['changes'] ?? null) ? $advice['changes'] : [];
            $hasDuplicate = filled($advice['duplicate_product_id'] ?? null);

            if ($changes === [] && ! $hasDuplicate) {
                $unchanged++;
                continue;
            }

            if ($hasDuplicate) {
                $duplicates++;
            }

            $decisionKey = 'product-catalog-ai:' . $product->id;
            SupplierReviewDecision::query()->updateOrCreate(
                ['decision_key' => $decisionKey],
                [
                    'supplier_code' => $product->supplierProducts->first()?->supplier?->code,
                    'report_file' => 'admin/product-catalog-ai',
                    'report_row' => (string) $product->id,
                    'decision' => SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG,
                    'status' => SupplierReviewDecision::STATUS_PENDING,
                    'supplier_product_id' => $product->supplierProducts->first()?->id,
                    'product_id' => $product->id,
                    'supplier_title' => $product->name,
                    'supplier_article' => $product->supplierProducts->pluck('supplier_article')->filter()->first(),
                    'source_url' => $product->supplierProducts->pluck('source_url')->filter()->first(),
                    'reason' => $advice['reason'] ?? null,
                    'payload' => $advice,
                    'error' => null,
                ],
            );

            $queued++;
            $lines[] = $this->line($advice);
        }

        $body = [
            'Проверено товаров: ' . $processed,
            'Создано/обновлено решений: ' . $queued,
            'Без изменений: ' . $unchanged,
            'Подозрений на дубль: ' . $duplicates,
        ];

        if ($lines !== []) {
            $body[] = '';
            $body[] = 'Первые подсказки:';
            array_push($body, ...array_slice($lines, 0, 12));
        }

        $body[] = '';
        $body[] = 'Проверить: php artisan supplier:apply-review-decisions --decision=update_product_catalog --list';
        $body[] = 'Применить: php artisan supplier:apply-review-decisions --decision=update_product_catalog --apply';

        $notification = Notification::make()
            ->success()
            ->title('AI-разбор каталога завершён')
            ->body(implode("\n", $body))
            ->persistent();

        if ($user) {
            $notification->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('AI-разбор каталога не выполнен')
            ->body(Str::limit($exception->getMessage(), 700))
            ->persistent()
            ->sendToDatabase($user);
    }

    /**
     * @param array<string, mixed> $advice
     */
    private function line(array $advice): string
    {
        $parts = [];
        if (filled($advice['suggested_brand'] ?? null)) {
            $parts[] = 'бренд: ' . $advice['suggested_brand'];
        }
        if (filled($advice['suggested_category'] ?? null)) {
            $parts[] = 'категория: ' . $advice['suggested_category'];
        }
        if (filled($advice['duplicate_sku'] ?? null)) {
            $parts[] = 'похожий дубль: ' . $advice['duplicate_sku'];
        }

        return ($advice['sku'] ?: ('ID ' . $advice['product_id'])) . ' — ' . implode(', ', $parts);
    }
}
