<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A list copied from Excel or a client's letter («артикул или название + количество») → rows of the selection.
 *
 * The quantity is read only where it is stated: a separate column (tab or «;»), «5 шт», «× 5». A bare number at the
 * end of a name is left alone — «Отвод 45° Ø 150» is not 150 elbows. A row is found by an article first (ours, the
 * supplier's, 1C), then by name with the search of the catalog suggestions.
 */
class B2bPastedList
{
    public const MAX_LINES = 200;

    private const CANDIDATES = 5;

    private const POOL = 40;

    /** Share of the words and numbers of a line a card must carry: to be offered at all, to be taken without a choice. */
    private const LIKELY = 0.34;

    private const SURE = 0.6;

    public function __construct(private StorefrontProductSearch $search) {}

    /** @return list<array{line: string, term: string, quantity: int, quantity_given: bool, status: string, by: ?string, candidates: list<array>}> */
    public function recognise(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim(preg_replace('/\s+/u', ' ', str_replace("\t", ' | ', $raw)) ?? '');
            if ($line === '' || count($rows) >= self::MAX_LINES) {
                continue;
            }
            [$term, $quantity, $given] = $this->split($raw);
            if (mb_strlen($term) < 2) {
                continue;
            }
            [$by, $products] = $this->find($term);
            // By name the search is fuzzy: one candidate is «found» only when it carries most words of the line.
            // The best card is taken without a choice when it carries most words and clearly more than the next one.
            $scores = $products->map(fn (Product $product): float => $this->overlap($term, $product))->values();
            $sure = $by === 'article' || ($scores->isNotEmpty() && $scores[0] >= self::SURE && ($scores->count() === 1 || $scores[0] - $scores[1] >= 0.1));
            $rows[] = [
                'line' => mb_substr($line, 0, 300), 'term' => $term, 'quantity' => $quantity, 'quantity_given' => $given,
                'status' => match (true) {
                    $products->isEmpty() => 'none', $sure => 'found', default => 'choose'
                },
                'by' => $products->isEmpty() ? null : $by,
                'candidates' => $products->map(fn (Product $product): array => [
                    'id' => (int) $product->id, 'name' => (string) $product->name, 'sku' => (string) ($product->sku ?: ''),
                    'brand' => (string) ($product->brand?->name ?: ''), 'price' => (float) $product->price,
                ])->values()->all(),
            ];
        }

        return $rows;
    }

    /** @return array{0: string, 1: int, 2: bool} the text to look for, the quantity, whether the quantity was stated */
    public function split(string $raw): array
    {
        $cells = array_values(array_filter(array_map('trim', preg_split('/\t|;/u', $raw) ?: []), fn (string $cell): bool => $cell !== ''));
        $quantity = null;
        // Excel / a table: the last numeric cell is the quantity, the other cells are the item.
        if (count($cells) > 1) {
            foreach (array_reverse(array_keys($cells)) as $index) {
                if (preg_match('/^(\d{1,3})(?:[.,]0+)?\s*(?:шт\.?|pcs|ед\.?)?$/iu', $cells[$index], $match) === 1) {
                    $quantity = (int) $match[1];
                    unset($cells[$index]);
                    break;
                }
            }
            $text = implode(' ', $cells);
        } else {
            $text = trim($cells[0] ?? '');
            // «… — 5 шт», «… 5шт.», «… x 5», «… × 5», «… * 5»
            if (preg_match('/^(.*?)[\s,–—-]*(\d{1,3})\s*(?:шт\.?|pcs|ед\.?)\s*$/iu', $text, $match) === 1
                || preg_match('/^(.*?)\s+[xх×*]\s*(\d{1,3})\s*$/iu', $text, $match) === 1) {
                $text = $match[1];
                $quantity = (int) $match[2];
            }
        }
        $text = trim(preg_replace('/^\s*\d{1,3}[.)]\s+/u', '', $text) ?? '', " \t-–—,.");

        return [mb_substr($text, 0, 200), max(1, min(999, $quantity ?? 1)), $quantity !== null && $quantity > 0];
    }

    /** @return array{0: string, 1: Collection<int, Product>} */
    private function find(string $term): array
    {
        $articles = $this->articles($term);
        if ($articles !== []) {
            $ids = Product::query()->whereIn('sku', $articles)->pluck('id')
                ->merge(SupplierProduct::query()->whereIn('supplier_article', $articles)->whereNotNull('product_id')->pluck('product_id'))
                ->unique();
            $products = $this->live(Product::query()->whereIn('id', $ids))->limit(self::CANDIDATES)->get();
            if ($products->isNotEmpty()) {
                return ['article', $products];
            }
        }

        // The search ranks loosely («0,8» before «0,5»): take a wide pool and order it by the words of the line.
        $pool = fn (Builder $ranked): Collection => $this->ranked($term, $ranked->limit(self::POOL)->pluck('id'));
        $products = $pool($this->search->applyForSuggestions($this->live(Product::query()), $term, true));
        if ($products->isEmpty()) {
            $products = $pool($this->search->applyBestMatch($this->live(Product::query()), $term));
        }

        return ['name', $products];
    }

    /** Cards of the pool that carry at least a third of the line, most words first, the search order next. @return Collection<int, Product> */
    private function ranked(string $term, Collection $ids): Collection
    {
        $order = $ids->values()->flip();

        return $this->live(Product::query()->whereIn('id', $ids))->get()
            ->map(fn (Product $product): array => [$product, $this->overlap($term, $product)])
            ->filter(fn (array $row): bool => $row[1] >= self::LIKELY)
            ->sort(fn (array $a, array $b): int => [$b[1], $order[$a[0]->id]] <=> [$a[1], $order[$b[0]->id]])
            ->take(self::CANDIDATES)->map(fn (array $row): Product => $row[0])->values();
    }

    /** Share of the words and numbers of the line found among the words of the card name and article. */
    public function overlap(string $term, Product $product): float
    {
        $wanted = $this->tokens($term);
        if ($wanted === []) {
            return 0.0;
        }
        $card = array_flip($this->tokens($product->name.' '.$product->sku));

        return count(array_filter($wanted, fn (string $token): bool => isset($card[$token]))) / count($wanted);
    }

    /** «Ø 150», «D150», «430-0,5»: letters and numbers apart, lower case, ё = е. @return list<string> */
    private function tokens(string $value): array
    {
        $value = str_replace('ё', 'е', mb_strtolower($value));
        preg_match_all('/\p{L}+|\d+/u', $value, $matches);

        $tokens = $matches[0];
        // «ТиС» is how managers and clients write «Теплов и Сухов».
        if (in_array('теплов', $tokens, true) && in_array('сухов', $tokens, true)) {
            $tokens[] = 'тис';
        }

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 2 || ctype_digit($token))));
    }

    private function live(Builder $query): Builder
    {
        return $query->active()->notArchived()->with('brand:id,name');
    }

    /** Article-like words of the line: with a digit, no spaces, 4+ characters («TS.KMP.FRK.0260.79671», «KOTLOV-007972»). @return list<string> */
    private function articles(string $term): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}.\-_\/]{3,}/u', $term, $matches);

        return collect($matches[0])->map(fn (string $word): string => rtrim($word, '.-_/'))
            ->filter(fn (string $word): bool => preg_match('/\d/', $word) === 1 && mb_strlen($word) >= 4 && preg_match('/^\d{1,4}$/', $word) !== 1)
            ->unique()->values()->all();
    }
}
