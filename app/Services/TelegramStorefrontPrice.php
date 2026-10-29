<?php

namespace App\Services;

use App\Models\Product;

class TelegramStorefrontPrice
{
    public function productFromUrl(?string $url): ?Product
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || trim($path, '/') === '') {
            return null;
        }

        $slug = rawurldecode((string) collect(explode('/', trim($path, '/')))->last());
        if ($slug === '') {
            return null;
        }

        return Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->first();
    }

    public function line(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        $price = (float) $product->price;

        return $price > 0
            ? '💰 *Цена на сайте:* '.number_format($price, 2, ',', ' ').' '.($product->currency ?: 'BYN')
            : '💰 *Цена на сайте:* по запросу';
    }
}
