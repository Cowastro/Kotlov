<?php

namespace App\Services\Suppliers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use ZipArchive;

class EurotermPriceList
{
    public const WEBSITE = 'https://euroterm.by/';

    /** @return array{source_date:string,source_sha256:string,supplier:array<string,string>,warehouse:array<string,string>,rows:array<int,array<string,mixed>>} */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Прайс Евротерм не найден: {$path}");
        }

        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->validate($payload);

            return $payload;
        }

        $xlsxRows = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx'
            ? $this->readXlsxRows($path)
            : null;
        $sheet = null;
        if ($xlsxRows === null) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            $sheet = $book->getSheetByName('TDSheet') ?? $book->getActiveSheet();
        }
        $headers = [];
        $rows = [];
        $highestRow = $xlsxRows !== null ? max(array_keys($xlsxRows)) : $sheet->getHighestRow();

        for ($rowNumber = 7; $rowNumber <= $highestRow; $rowNumber++) {
            $article = $xlsxRows !== null
                ? trim((string) ($xlsxRows[$rowNumber]['A'] ?? ''))
                : $this->cell($sheet, 'A', $rowNumber);
            $name = $xlsxRows !== null
                ? trim((string) ($xlsxRows[$rowNumber]['F'] ?? ''))
                : $this->cell($sheet, 'F', $rowNumber);
            $wholesale = $this->number($xlsxRows !== null
                ? ($xlsxRows[$rowNumber]['N'] ?? 0)
                : $sheet->getCell("N{$rowNumber}")->getCalculatedValue());
            $retail = $this->number($xlsxRows !== null
                ? ($xlsxRows[$rowNumber]['O'] ?? 0)
                : $sheet->getCell("O{$rowNumber}")->getCalculatedValue());
            $stock = max(0, $this->number($xlsxRows !== null
                ? ($xlsxRows[$rowNumber]['P'] ?? 0)
                : $sheet->getCell("P{$rowNumber}")->getCalculatedValue()));
            $level = $xlsxRows !== null
                ? max(0, (int) ($xlsxRows[$rowNumber]['_level'] ?? 0))
                : max(0, (int) $sheet->getRowDimension($rowNumber)->getOutlineLevel());

            if ($name === '') {
                if ($article !== '') {
                    $headers[$level] = $article;
                    foreach (array_keys($headers) as $headerLevel) {
                        if ($headerLevel > $level) {
                            unset($headers[$headerLevel]);
                        }
                    }
                }
                continue;
            }
            if ($wholesale <= 0 || $retail <= 0) {
                continue;
            }

            $article = $article ?: $this->articleFromName($name);
            $articleKey = $this->articleKey($article);
            if ($articleKey === '') {
                throw new RuntimeException("Не удалось определить артикул в строке {$rowNumber}: {$name}");
            }

            ksort($headers);
            $pathParts = array_values($headers);
            $rows[] = [
                'article' => $article,
                'article_key' => $articleKey,
                'name' => $this->cleanName($name),
                'name_key' => $this->nameKey($name),
                'category_path' => $pathParts,
                'section' => $pathParts[0] ?? '',
                'category' => $pathParts[1] ?? '',
                'brand' => $this->brand($pathParts, $name),
                'unit' => $this->unit($name),
                'wholesale' => round($wholesale, 2),
                'retail' => round($retail, 2),
                'stock_quantity' => (int) round($stock),
                'in_stock' => $stock > 0,
                'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
                'stock_text' => $stock > 0 ? 'В наличии: '.$this->quantity($stock) : 'Нет в наличии у Евротерм',
                'warehouse_name' => 'Евротерм · Машиностроителей, 9А',
                'source_url' => $this->sourceUrl($pathParts),
                'source_row' => $rowNumber,
            ];
        }

        $rows = $this->mergeDuplicateArticles($rows);
        $payload = [
            'source_date' => $this->sourceDate($xlsxRows !== null
                ? trim((string) ($xlsxRows[1]['A'] ?? ''))
                : $this->cell($sheet, 'A', 1)),
            'source_sha256' => hash_file('sha256', $path),
            'supplier' => [
                'code' => 'euroterm',
                'name' => 'ООО «Евротерм»',
                'website' => self::WEBSITE,
            ],
            'warehouse' => [
                'name' => 'Евротерм · Машиностроителей, 9А',
                'address' => 'Минск, ул. Машиностроителей, 9А',
            ],
            'rows' => $rows,
        ];
        $this->validate($payload);

        return $payload;
    }

    public function articleKey(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = strtr($value, [
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T',
            'Х' => 'X', 'У' => 'Y',
        ]);

        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }

    public function nameKey(?string $value): string
    {
        $value = mb_strtolower((string) $value);
        $value = preg_replace('/\/(?:страна происхождения|производство).*$/ui', '', $value);
        $value = preg_replace('/,?\s*(?:россия|рф|китай|корея|польша|словакия),?\s*(?:шт|м)?\s*$/ui', '', $value);
        $value = preg_replace('/,?\s*(?:шт|м)\s*$/ui', '', $value);

        return $this->articleKey($value);
    }

    private function articleFromName(string $name): string
    {
        if (preg_match('/^(\d+\s*KOV)\b/ui', $name, $match)) {
            return trim($match[1]);
        }

        return trim((string) strtok($name, ','));
    }

    private function cleanName(string $name): string
    {
        return trim((string) preg_replace('/,\s*(?:шт|м)\s*$/ui', '', $name));
    }

    /** @param array<int,string> $path */
    private function brand(array $path, string $name): string
    {
        foreach (array_reverse($path) as $part) {
            if (preg_match('/^(?:Navien|Protherm|Vaillant|VGR|Salus Controls)$/ui', $part)) {
                return trim($part);
            }
        }
        if (preg_match('/\b(Navien|Protherm|Proterm|Vaillant|VGR|Salus|PROFLine)\b/ui', $name, $match)) {
            return match (mb_strtolower($match[1])) {
                'proterm' => 'Protherm',
                'salus' => 'Salus Controls',
                default => $match[1],
            };
        }

        return ($path[0] ?? '') === 'СТМ (ProfLine)' ? 'PROFLine' : '';
    }

    /** @param array<int,string> $path */
    private function sourceUrl(array $path): string
    {
        $value = mb_strtolower(implode(' ', $path));

        return match (true) {
            str_contains($value, 'котлы газовые') => self::WEBSITE.'otopitelnoe-oborudovanie/gazovye-kotly.html',
            str_contains($value, 'котлы электрические') => self::WEBSITE.'otopitelnoe-oborudovanie/elektricheskie-kotly.html',
            str_contains($value, 'водонагреватели') => self::WEBSITE.'vodonagrevateli/kosvennogo-nagreva.html',
            str_contains($value, 'детектор') => self::WEBSITE.'avtomatika-i-elektronika/detektory-gaza.html',
            str_contains($value, 'терморегулятор') => self::WEBSITE.'avtomatika-i-elektronika/termoregulyatory.html',
            str_contains($value, 'стабилизатор') => self::WEBSITE.'avtomatika-i-elektronika/stabilizatory-napryazheniya.html',
            str_contains($value, 'дымоудален') => self::WEBSITE.'aksessuary/dymokhody.html',
            str_contains($value, 'автоматика') => self::WEBSITE.'avtomatika-i-elektronika.html',
            default => self::WEBSITE,
        };
    }

    private function sourceDate(string $title): string
    {
        $months = [
            'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
            'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
            'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
        ];
        if (preg_match('/(\d{1,2})\s+([а-яё]+)\s+(\d{4})/ui', $title, $match)
            && isset($months[mb_strtolower($match[2])])) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], $months[mb_strtolower($match[2])], (int) $match[1]);
        }

        throw new RuntimeException('Не удалось определить дату прайса Евротерм.');
    }

    private function unit(string $name): string
    {
        return preg_match('/,\s*(шт|м)\s*$/ui', $name, $match) ? mb_strtolower($match[1]) : 'шт';
    }

    private function quantity(float $value): string
    {
        return abs($value - round($value)) < 0.0005
            ? number_format($value, 0, ',', ' ')
            : rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }

    private function cell($sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell($column.$row)->getCalculatedValue());
    }

    /** @return array<int,array<string,mixed>> */
    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть XLSX Евротерм: '.$path);
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $document = simplexml_load_string($xml);
                foreach ($document?->si ?? [] as $item) {
                    $parts = [];
                    foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                        $parts[] = (string) $text;
                    }
                    $shared[] = implode('', $parts);
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('В XLSX Евротерм не найден первый лист.');
            }
            $sheet = simplexml_load_string($sheetXml);
            $rows = [];
            foreach ($sheet?->xpath('//*[local-name()="row"]') ?: [] as $row) {
                $number = (int) $row['r'];
                $rows[$number]['_level'] = (int) ($row['outlineLevel'] ?? 0);
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $reference = (string) $cell['r'];
                    if (! preg_match('/^([A-Z]+)\d+$/', $reference, $match)
                        || ! in_array($match[1], ['A', 'F', 'N', 'O', 'P'], true)) {
                        continue;
                    }
                    $type = (string) $cell['t'];
                    $children = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $value = isset($children->v) ? (string) $children->v : '';
                    if ($type === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $textNodes = $children->is?->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map(fn ($text): string => (string) $text, $textNodes));
                    }
                    $rows[$number][$match[1]] = $value;
                }
            }
            ksort($rows);

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.-]+/u', '', (string) $value));
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function mergeDuplicateArticles(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = $row['article_key'];
            if (! isset($merged[$key])) {
                $row['source_rows'] = [$row['source_row']];
                $merged[$key] = $row;
                continue;
            }
            if (abs((float) $merged[$key]['wholesale'] - (float) $row['wholesale']) > 0.005
                || abs((float) $merged[$key]['retail'] - (float) $row['retail']) > 0.005) {
                throw new RuntimeException('Повтор артикула с разными ценами: '.$row['article']);
            }
            $merged[$key]['stock_quantity'] += $row['stock_quantity'];
            $merged[$key]['in_stock'] = $merged[$key]['stock_quantity'] > 0;
            $merged[$key]['stock_status'] = $merged[$key]['in_stock'] ? 'in_stock' : 'out_of_stock';
            $merged[$key]['stock_text'] = $merged[$key]['in_stock']
                ? 'В наличии: '.$this->quantity((float) $merged[$key]['stock_quantity'])
                : 'Нет в наличии у Евротерм';
            $merged[$key]['source_rows'][] = $row['source_row'];
        }

        return array_values($merged);
    }

    private function validate(array $payload): void
    {
        if (empty($payload['source_date']) || empty($payload['rows'])) {
            throw new RuntimeException('Прайс Евротерм не прошёл контроль даты или полноты.');
        }
        $articles = collect($payload['rows'])->pluck('article_key');
        if ($articles->unique()->count() !== $articles->count()) {
            $duplicates = $articles->countBy()->filter(fn (int $count): bool => $count > 1)->keys()->implode(', ');
            throw new RuntimeException('В прайсе Евротерм повторяются артикулы: '.$duplicates);
        }
        foreach ($payload['rows'] as $row) {
            if (($row['wholesale'] ?? 0) <= 0 || ($row['retail'] ?? 0) <= 0 || ($row['stock_quantity'] ?? -1) < 0) {
                throw new RuntimeException('Некорректная цена или остаток Евротерм: '.($row['article'] ?? '?'));
            }
        }
    }
}
