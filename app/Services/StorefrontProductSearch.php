<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class StorefrontProductSearch
{
    public function apply(Builder $query, string $search): Builder
    {
        $normalized = mb_strtolower(trim($search));
        $normalized = preg_replace('/гидравлическ\S*\s+разделител\S*/u', 'гидрострелка', $normalized);
        $tokens = preg_split('/[\s\-–—]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function (Builder $matches) use ($search, $tokens): void {
            $matches->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere(function (Builder $words) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $alternatives = str_starts_with($token, 'гидрострел')
                            ? ['гидрострел', 'гидравлическ разделител']
                            : [$token];

                        $words->where(function (Builder $synonyms) use ($alternatives): void {
                            foreach ($alternatives as $alternative) {
                                $synonyms->orWhere(function (Builder $phrase) use ($alternative): void {
                                    foreach (explode(' ', $alternative) as $word) {
                                        $phrase->where(function (Builder $fields) use ($word): void {
                                            foreach (array_unique([$word, mb_convert_case($word, MB_CASE_TITLE), mb_strtoupper($word)]) as $variant) {
                                                $fields->orWhere('name', 'like', '%'.$variant.'%')
                                                    ->orWhere('sku', 'like', '%'.$variant.'%')
                                                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', '%'.$variant.'%'));
                                            }
                                        });
                                    }
                                });
                            }
                        });
                    }
                });
        });
    }
}
