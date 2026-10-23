<?php

namespace App\Services\PriceList;

use PhpOffice\PhpSpreadsheet\IOFactory;

class KarinaPriceAudit
{
    public function normalize(string $value): string
    {
        // Keep model numbers and punctuation: no fuzzy matching or digit removal.
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    public function inspect(string $path): array
    {
        $book = IOFactory::load($path);
        $sheets = [];
        $items = [];
        foreach ($book->getWorksheetIterator() as $sheetIndex => $sheet) {
            $rows = $sheet->toArray(null, true, false, false);
            $structure = [];
            $headerCandidates = [];
            $header = null;
            $columns = [];
            foreach ($rows as $index => $row) {
                $found = [];
                foreach ($row as $col => $value) {
                    $label = $this->normalize((string) $value);
                    $kind = match (true) {
                        in_array($label, ['артикул', 'арт.', 'арт', 'код', 'article', 'sku'], true) => 'article',
                        in_array($label, ['наименование', 'наименование товара', 'название', 'модель', 'name'], true) => 'name',
                        in_array($label, ['ррц', 'розничная цена', 'цена', 'цена, руб.', 'цена руб.', 'price'], true) => 'retail',
                        in_array($label, ['byn', 'rub', 'rur', 'бел. руб.', 'рос. руб.'], true) => 'currency',
                        default => null,
                    };
                    if ($kind !== null) {
                        // Only known header/currency labels; never arbitrary preamble cells.
                        $structure[] = ['row' => $index + 1, 'column' => $col + 1, 'kind' => $kind, 'label' => $label];
                        $found[$kind][] = $col;
                    }
                }
                if (count($found['article'] ?? []) === 1) {
                    $cells = [];
                    foreach ($row as $col => $value) {
                        $text = trim((string) $value);
                        if ($text !== '' && mb_strlen($text) <= 160
                            && ! preg_match('/@|https?:|телефон|контакт|\\+\\d{7}/iu', $text)) {
                            $cells[] = ['column' => $col + 1, 'label' => $text];
                        }
                    }
                    $headerCandidates[] = ['row' => $index + 1, 'cells' => $cells];
                }
                if ($header === null && count($found['name'] ?? []) === 1 && count($found['retail'] ?? []) === 1) {
                    $header = $index;
                    $columns = [
                        'article' => count($found['article'] ?? []) === 1 ? $found['article'][0] : null,
                        'name' => $found['name'][0],
                        'retail' => $found['retail'][0],
                    ];
                }
            }
            $invalid = [];
            if ($header !== null) {
                foreach ($rows as $index => $row) {
                    if ($index <= $header) {
                        continue;
                    }
                    $name = trim((string) ($row[$columns['name']] ?? ''));
                    $rawPrice = $row[$columns['retail']] ?? null;
                    $price = SupplierAdapter::parsePrice($rawPrice);
                    if ($name === '' && ! $price) {
                        continue;
                    }
                    // Data cells must describe goods; contact/preamble text is never exported.
                    if ($price <= 0 || $name === '' || preg_match('/@|https?:|телефон|e-mail/iu', $name)) {
                        $invalid[] = $index + 1;
                        continue;
                    }
                    $article = $columns['article'] !== null ? trim((string) ($row[$columns['article']] ?? '')) : '';
                    if ($article !== '' && ! preg_match('/^[\pL\pN_.\/() -]{1,100}$/u', $article)) {
                        $invalid[] = $index + 1;
                        continue;
                    }
                    $items[] = [
                        'sheet' => $sheetIndex, 'row' => $index + 1,
                        'article' => $article, 'name' => $name,
                        'retail' => $price, 'purchase' => round($price * 0.75, 2),
                        'missing_article' => $article === '',
                    ];
                }
            }
            $sheets[] = [
                'index' => $sheetIndex, 'rows' => count($rows),
                'recognized_cells' => $structure, 'header_candidates' => $headerCandidates, 'header_row' => $header === null ? null : $header + 1,
                'columns' => $columns, 'excluded_rows' => $invalid,
            ];
        }
        $book->disconnectWorksheets();

        return ['sha256' => hash_file('sha256', $path), 'sheets' => $sheets, 'items' => $items];
    }

    public function match(array $items, array $products): array
    {
        $sku = [];
        $names = [];
        foreach ($products as $product) {
            if (trim((string) $product['sku']) !== '') {
                $sku[$this->normalize((string) $product['sku'])][] = $product['id'];
            }
            $names[$this->normalize($product['name'])][] = $product['id'];
        }
        $articles = [];
        $rowNames = [];
        foreach ($items as $i => $item) {
            if ($item['article'] !== '') {
                $articles[$this->normalize($item['article'])][] = $i;
            }
            $rowNames[$this->normalize($item['name'])][] = $i;
        }
        foreach ($items as &$item) {
            $bySku = $item['article'] !== '' ? ($sku[$this->normalize($item['article'])] ?? []) : [];
            $byName = $names[$this->normalize($item['name'])] ?? [];
            $ids = array_values(array_unique([...$bySku, ...$byName]));
            $item['candidate_product_ids'] = $ids;
            $item['duplicate_article'] = $item['article'] !== '' && count($articles[$this->normalize($item['article'])]) > 1;
            $item['duplicate_name'] = count($rowNames[$this->normalize($item['name'])]) > 1;
            $item['match_status'] = count($ids) > 1 ? 'ambiguous' : (count($ids) === 1 ? 'exact_candidate' : 'unmatched');
            $item['match_method'] = $bySku ? 'sku' : ($byName ? 'exact_name' : null);
            // A candidate is a suggestion for review, never an applied mapping.
        }
        unset($item);
        $targets = [];
        foreach ($items as $i => $item) {
            foreach ($item['candidate_product_ids'] as $id) {
                $targets[$id][] = $i;
            }
        }
        foreach ($items as &$item) {
            $item['shared_target'] = collect($item['candidate_product_ids'])->contains(fn ($id) => count($targets[$id]) > 1);
        }
        unset($item);

        return $items;
    }
}
