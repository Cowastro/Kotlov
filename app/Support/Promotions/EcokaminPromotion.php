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
    public const CACHE_KEY = 'ecokamin-promotion:showcase:v1';

    private const SERIES = ['дельта', 'рио', 'мюнхен', 'мадрид', 'крит', 'прага', 'эклипс', 'родос', 'три стекла'];

    public static function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(ApplyEcokaminOctoberSaleCommand::ENDS_AT, 'Europe/Minsk');
    }

    public static function isRunning(): bool
    {
        return now()->lt(self::endsAt());
    }

    /** @return array{all: Collection<int, Product>, hits: Collection<int, Product>, maxDiscount: int, from: array{delta: ?float, kaminy: ?float}} */
    public static function showcase(): array
    {
        if (! self::isRunning()) {
            return ['all' => collect(), 'hits' => collect(), 'maxDiscount' => 0, 'from' => ['delta' => null, 'kaminy' => null]];
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

            return [
                'all' => $all,
                'hits' => $hits,
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
