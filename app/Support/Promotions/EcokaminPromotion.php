<?php

namespace App\Support\Promotions;

use App\Console\Commands\ApplyEcokaminOctoberSaleCommand;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Storefront data of the running ЭкоКамин promotion: the orderable products,
 * one hit per series with a photo, the biggest discount and «from» prices.
 * Shared by the home page and the fireplace catalogue banners.
 */
final class EcokaminPromotion
{
    public const CACHE_KEY = 'ecokamin-promotion:showcase:v3';

    private const SERIES = ['дельта', 'бавария', 'рио', 'мюнхен', 'мадрид', 'крит', 'прага', 'эклипс', 'родос', 'три стекла'];

    public static function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(ApplyEcokaminOctoberSaleCommand::ENDS_AT, 'Europe/Minsk');
    }

    public static function isRunning(): bool
    {
        return now()->lt(self::endsAt());
    }

    /** @return array{all: Collection<int, Product>, hits: Collection<int, Product>, deltaHits: Collection<int, Product>, fireplaceHits: Collection<int, Product>, maxDiscount: int, deltaMaxDiscount: int, fireplaceMaxDiscount: int, from: array{delta: ?float, kaminy: ?float}} */
    public static function showcase(): array
    {
        if (! self::isRunning()) {
            return ['all' => collect(), 'hits' => collect(), 'deltaHits' => collect(), 'fireplaceHits' => collect(), 'maxDiscount' => 0, 'deltaMaxDiscount' => 0, 'fireplaceMaxDiscount' => 0, 'from' => ['delta' => null, 'kaminy' => null]];
        }

        return Cache::remember(self::CACHE_KEY, 600, function (): array {
            $all = Product::query()
                ->orderable()
                ->withSupplierChannels()
                ->with(['category', 'brand'])
                ->inActiveSaleCampaign(ApplyEcokaminOctoberSaleCommand::CAMPAIGN)
                ->homepageRatingOrder(true)
                ->get()
                ->filter(fn (Product $product): bool => $product->canBeAddedToCart())
                ->values();
            $hits = collect(self::SERIES)
                ->map(fn (string $series) => $all
                    ->filter(fn (Product $product): bool => str_contains(mb_strtolower($product->name), $series))
                    ->sortByDesc(fn (Product $product): int => count($product->imagePaths()) > 0 ? 1 : 0)
                    ->first())
                ->filter()
                ->unique('id')
                ->values();
            $isDelta = fn (Product $product): bool => str_contains(mb_strtolower($product->name), 'дельта');
            $delta = $all->filter($isDelta);
            // One Дельта per width (700, 800, 1000…), photos first, cheapest first.
            $deltaHits = $delta
                ->sortBy(fn (Product $product): string => (count($product->imagePaths()) > 0 ? '0' : '1').str_pad((string) (int) $product->price, 9, '0', STR_PAD_LEFT))
                ->unique(fn (Product $product): string => preg_match('/дельта\s*(\d{3,4})/iu', $product->name, $m) ? $m[1] : (string) $product->id)
                ->sortBy(fn (Product $product): int => (int) (preg_match('/дельта\s*(\d{3,4})/iu', $product->name, $m) ? $m[1] : 9999))
                ->take(3)
                ->values();

            return [
                'all' => $all,
                'hits' => $hits,
                'deltaHits' => $deltaHits,
                'fireplaceHits' => $hits->reject($isDelta)->take(3)->values(),
                'fireplaceMaxDiscount' => (int) $all->reject($isDelta)->max(fn (Product $product): int => (int) $product->discount_percent),
                'deltaMaxDiscount' => (int) $delta->max(fn (Product $product): int => (int) $product->discount_percent),
                'maxDiscount' => (int) $all->max(fn (Product $product): int => (int) $product->discount_percent),
                'from' => [
                    'delta' => $all->filter($isDelta)->min('price'),
                    'kaminy' => $all->reject($isDelta)->min('price'),
                ],
            ];
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
