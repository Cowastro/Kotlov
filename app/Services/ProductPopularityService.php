<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductPopularityService
{
    private const UNIQUE_VIEW_HOURS = 12;

    public function recordView(Product $product, Request $request): bool
    {
        if ($this->isRobot((string) $request->userAgent())) {
            return false;
        }

        $userId = $request->user()?->getAuthIdentifier();
        $sessionId = hash('sha256', $request->session()->getId());
        $cutoff = now()->subHours(self::UNIQUE_VIEW_HOURS);

        $alreadyCounted = ProductView::query()
            ->where('product_id', $product->id)
            ->where('viewed_at', '>=', $cutoff)
            ->where(function ($query) use ($userId, $sessionId): void {
                if ($userId) {
                    $query->where('user_id', $userId)->orWhere('session_id', $sessionId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->exists();

        if ($alreadyCounted) {
            return false;
        }

        try {
            DB::transaction(function () use ($product, $userId, $sessionId): void {
                ProductView::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'viewed_at' => now(),
                ]);

                Product::query()->whereKey($product->id)->increment('views_count');
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isConcurrencyError($exception)) {
                throw $exception;
            }

            // A popularity metric must never make the product page unavailable.
            Log::warning('Product view counter skipped after a database concurrency conflict.', [
                'product_id' => $product->id,
                'sql_state' => (string) $exception->getCode(),
            ]);

            return false;
        }

        return true;
    }

    public function recalculate(): int
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $recentViews = DB::table('product_views')
            ->where('viewed_at', '>=', $thirtyDaysAgo)
            ->groupBy('product_id')
            ->selectRaw(
                'product_id, COUNT(*) AS views_30d, SUM(CASE WHEN viewed_at >= ? THEN 1 ELSE 0 END) AS views_7d',
                [$sevenDaysAgo]
            )
            ->get()
            ->keyBy('product_id');

        $updated = 0;
        Product::query()
            ->select(['id', 'rating', 'reviews_count', 'views_count'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($recentViews, $now, &$updated): void {
                foreach ($products as $product) {
                    $recent = $recentViews->get($product->id);
                    $score = $this->score(
                        (float) $product->rating,
                        (int) $product->reviews_count,
                        (int) ($recent->views_7d ?? 0),
                        (int) ($recent->views_30d ?? 0),
                        (int) $product->views_count,
                    );

                    Product::query()->whereKey($product->id)->update([
                        'popularity_score' => $score,
                        'popularity_updated_at' => $now,
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }

    public function score(float $rating, int $reviews, int $views7d, int $views30d, int $lifetimeViews): float
    {
        $reviewConfidence = min(1, max(0, $reviews) / 5);
        $ratingPoints = 50 * (max(0, min(5, $rating)) / 5) * $reviewConfidence;
        $reviewPoints = min(15, log1p(max(0, $reviews)) * 4);
        $freshViewPoints = min(35, log1p(max(0, $views7d)) * 8);
        $monthlyViewPoints = min(20, log1p(max(0, $views30d)) * 4);
        $historyPoints = min(10, log1p(max(0, $lifetimeViews)) * 1.2);

        return round($ratingPoints + $reviewPoints + $freshViewPoints + $monthlyViewPoints + $historyPoints, 4);
    }

    private function isRobot(string $userAgent): bool
    {
        return $userAgent === '' || preg_match(
            '/bot|crawl|spider|slurp|preview|facebookexternalhit|monitor|headless|lighthouse|pagespeed/i',
            $userAgent
        ) === 1;
    }

    protected function isConcurrencyError(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['40001', '40P01'], true)
            || str_contains($exception->getMessage(), 'Deadlock found')
            || str_contains($exception->getMessage(), 'Lock wait timeout')
            || str_contains($exception->getMessage(), '1213')
            || str_contains($exception->getMessage(), '1205');
    }
}
