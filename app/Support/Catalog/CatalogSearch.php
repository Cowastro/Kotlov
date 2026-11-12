<?php

namespace App\Support\Catalog;

use Illuminate\Database\Eloquent\Builder;

final class CatalogSearch
{
    public static function term(mixed $raw): string
    {
        return is_string($raw) ? mb_substr(trim($raw), 0, 100) : '';
    }

    public static function apply(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/u', $search, 6, PREG_SPLIT_NO_EMPTY) as $word) {
            $match = '%'.strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $term) => $term
                ->whereRaw("products.name LIKE ? ESCAPE '!'", [$match])
                ->orWhereRaw("products.sku LIKE ? ESCAPE '!'", [$match])
                ->orWhereRaw("products.slug LIKE ? ESCAPE '!'", [$match])
                ->orWhereHas('brand', fn (Builder $brand) => $brand->whereRaw("name LIKE ? ESCAPE '!'", [$match])));
        }

        return $query;
    }
}
