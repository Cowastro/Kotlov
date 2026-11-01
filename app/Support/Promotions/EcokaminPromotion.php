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
    public const CACHE_KEY = 'ecokamin-promotion:showcase:v4';

    /** One hit per series, in this order on the home slide. */
    private const SERIES = ['дельта', 'бавария', 'прага', 'альфа', 'вега', 'primo', 'рио', 'мюнхен', 'мадрид', 'крит', 'эклипс', 'родос', 'три стекла'];

    /** Firebox series of the promotion; everything else is a fireplace or a stove-fireplace. */
    public const FIREBOX_SERIES = ['дельта', 'альфа', 'вега', 'primo'];

    public static function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(ApplyEcokaminOctoberSaleCommand::ENDS_AT, 'Europe/Minsk');
    }

    public static function isRunning(): bool
    {
        return now()->lt(self::endsAt());
    }

    public static function isFirebox(Product $product): bool
    {
        return self::seriesOf($product, self::FIREBOX_SERIES) !== null;
    }

    /** @param  list<string>  $series */
    public static function seriesOf(Product $product, array $series = self::SERIES): ?string
    {
        $name = mb_strtolower($product->name);
        foreach ($series as $candidate) {
            if (str_contains($name, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{all: Collection<int, Product>, hits: Collection<int, Product>, fireboxHits: Collection<int, Product>, fireplaceHits: Collection<int, Product>, maxDiscount: int, fireboxMaxDiscount: int, fireplaceMaxDiscount: int, from: array{topki: ?float, kaminy: ?float}} */
    public static function showcase(): array
    {
        if (! self::isRunning()) {
            return ['all' => collect(), 'hits' => collect(), 'fireboxHits' => collect(), 'fireplaceHits' => collect(), 'maxDiscount' => 0, 'fireboxMaxDiscount' => 0, 'fireplaceMaxDiscount' => 0, 'from' => ['topki' => null, 'kaminy' => null]];
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
            // The biggest discount of a series first, then a card with a photo.
            $best = fn (Collection $products) => $products
                ->sortByDesc(fn (Product $product): int => (int) $product->discount_percent * 10 + (count($product->imagePaths()) > 0 ? 1 : 0))
                ->first();
            $hits = collect(self::SERIES)
                ->map(fn (string $series) => $best($all->filter(fn (Product $product): bool => self::seriesOf($product) === $series)))
                ->filter()
                ->values();
            $fireboxes = $all->filter(fn (Product $product): bool => self::isFirebox($product));
            $fireplaces = $all->reject(fn (Product $product): bool => self::isFirebox($product));

            return [
                'all' => $all,
                'hits' => $hits,
                // A catalogue banner needs three cards: one per series first, then more of the same group.
                'fireboxHits' => $hits->filter(fn (Product $product): bool => self::isFirebox($product))->concat($fireboxes)->unique('id')->take(3)->values(),
                'fireplaceHits' => $hits->reject(fn (Product $product): bool => self::isFirebox($product))->concat($fireplaces)->unique('id')->take(3)->values(),
                'fireboxMaxDiscount' => (int) $fireboxes->max(fn (Product $product): int => (int) $product->discount_percent),
                'fireplaceMaxDiscount' => (int) $fireplaces->max(fn (Product $product): int => (int) $product->discount_percent),
                'maxDiscount' => (int) $all->max(fn (Product $product): int => (int) $product->discount_percent),
                'from' => [
                    'topki' => $fireboxes->min('price'),
                    'kaminy' => $fireplaces->min('price'),
                ],
            ];
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
