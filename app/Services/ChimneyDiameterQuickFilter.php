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
        // Double diameters also occur on convectors and unrelated adapters.
        // Require a sandwich designation or a known thermal-series element code.
        if (! preg_match('/термо|сэндвич|сендвич|(?<!\pL)(?:ТТ|ОТ|КТ|[ТT][РP][ТT]|ПМТ)(?=[\s(\-])/ui', $name)) {
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

    public function monoDiameter(string $name): ?string
    {
        if (preg_match('/термо|сэндвич|сендвич/ui', $name)
            || ! preg_match('/моно|(?<!\pL)(?:ТМ|ОМ|ЗМ|ШМ|ДМ|КМ|ТРМ|ЗРМ)(?=[\s(\-])/ui', $name)
            || preg_match('/\d{2,3}\s*\/\s*\d{2,4}/u', $name)) {
            return null;
        }
        if (! preg_match('/[Øø⌀ФфDdДд]\s*(\d{2,3})(?!\d)(\s*[\/хx×–—-]\s*\d)?/u', $name, $match)
            || ! empty($match[2])) {
            return null;
        }

        return (int) $match[1] >= 60 ? (string) (int) $match[1] : null;
    }

    /** @return Collection<string|int, Collection<int, int>> */
    public function groups(Builder $query, bool $mono = false): Collection
    {
        $groups = collect();
        foreach ((clone $query)->get(['products.id', 'products.name']) as $product) {
            $pair = $mono ? $this->monoDiameter($product->name) : $this->diameterPair($product->name);
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
