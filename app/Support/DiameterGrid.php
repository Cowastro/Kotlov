<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Lays out a category of chimney parts (or pipes) as a table: a row per item kind
 * («Отвод моно … ОМ-Р 45°, 430, 0.8»), a column per diameter («Ø 150», «150/210»).
 * Items without a recognisable diameter, or a second item in the same cell, stay in «rest».
 */
class DiameterGrid
{
    /** «Ø 150», «Ø150/210», «D 115», «Д120», «d=200» */
    private const PATTERN = '/(?:Ø|⌀|\bD|\bД|\bd)\s*=?\s*(\d{2,4}(?:\s*[\/хx×]\s*\d{2,4})?)(?![\d.,])/u';

    /**
     * @param  Collection<int, mixed>  $products  models with id and name
     * @return array{tables: list<array{columns: list<string>, rows: array<string, array<string, mixed>>}>, rest: list<mixed>}|null
     *                                                                                                                              null when the items do not form a table
     */
    public static function build(Collection $products): ?array
    {
        $rows = [];
        $rest = [];
        foreach ($products as $product) {
            $parsed = self::parse((string) $product->name);
            if ($parsed === null || isset($rows[$parsed['row']][$parsed['diameter']])) {
                $rest[] = $product;

                continue;
            }
            $rows[$parsed['row']][$parsed['diameter']] = $product;
        }

        // Single-wall (Ø 150) and insulated (150/210) items get tables of their own.
        $tables = [];
        foreach (collect($rows)->groupBy(fn (array $cells) => str_contains((string) array_key_first($cells), '/') ? 'pair' : 'single', true) as $family) {
            $family = $family->all();
            $columns = collect($family)->flatMap(fn (array $cells) => array_map('strval', array_keys($cells)))->unique()
                ->sortBy(fn (string $diameter) => array_map('intval', preg_split('/\D+/', $diameter)))->values()->all();
            // A table pays off only when items of one kind differ by diameter.
            if (count($columns) < 2 || collect($family)->max(fn (array $cells) => count($cells)) < 2) {
                array_push($rest, ...collect($family)->flatten(1)->all());

                continue;
            }
            ksort($family, SORT_NATURAL | SORT_FLAG_CASE);
            $tables[] = ['columns' => $columns, 'rows' => $family];
        }

        return $tables === [] ? null : ['tables' => $tables, 'rest' => $rest];
    }

    /** @return array{row: string, diameter: string}|null */
    public static function parse(string $name): ?array
    {
        if (! preg_match_all(self::PATTERN, $name, $matches, PREG_OFFSET_CAPTURE) || count($matches[0]) !== 1) {
            return null;
        }
        [$token, $offset] = $matches[0][0];
        $diameter = preg_replace('/\s*[\/хx×]\s*/u', '/', $matches[1][0][0]);
        $row = substr($name, 0, $offset).substr($name, $offset + strlen($token));
        $row = trim(preg_replace(['/\s+/u', '/\s+([,.;)])/u', '/[\s,;.\-–—]+$/u', '/\(\s*\)/u'], [' ', '$1', '', ''], $row));

        return $row === '' ? null : ['row' => $row, 'diameter' => $diameter];
    }
}
