<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChimneyDiameterQuickFilter
{
    public function diameterPair(string $name): ?string
    {
        // A mono reducer (150/200) is not a sandwich system (150/200).
        if (preg_match('/моно/ui', $name) && ! preg_match('/термо|сэндвич|сендвич/ui', $name)) {
            return null;
        }
        if (! preg_match('/(?<![\d.,])(\d{2,3})\s*\/\s*(\d{2,4})(?![\d.,])/u', $name, $match)) {
            return null;
        }
        $inner = (int) $match[1];
        $outer = (int) $match[2];

        return $inner >= 60 && $outer > $inner && $outer <= 1000
            ? $inner.'/'.$outer
            : null;
    }

    /** @return Collection<string, Collection<int, int>> */
    public function groups(Builder $query): Collection
    {
        $groups = collect();
        foreach ((clone $query)->get(['products.id', 'products.name']) as $product) {
            $pair = $this->diameterPair($product->name);
            if ($pair !== null) {
                if (! $groups->has($pair)) {
                    $groups->put($pair, collect());
                }
                $groups->get($pair)->push($product->id);
            }
        }

        return $groups->sortKeysUsing(function (string $a, string $b): int {
            return array_map('intval', explode('/', $a)) <=> array_map('intval', explode('/', $b));
        });
    }
}
