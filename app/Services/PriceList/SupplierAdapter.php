<?php

namespace App\Services\PriceList;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierAdapter
{
    /**
     * Column header configurations per supplier code.
     */
    private const CONFIGS = [
        'berezka' => [
            'sku_col'      => 'Арт.',
            'name_col'     => 'Наименование',
            'rrc_col'      => 'РРЦ',
            'opt_col'      => 'Цена с НДС',
        ],
    ];

    public static function getConfig(string $supplier): array
    {
        if (! isset(self::CONFIGS[$supplier])) {
            $available = implode(', ', array_keys(self::CONFIGS));
            throw new \InvalidArgumentException(
                "Unknown supplier adapter: '{$supplier}'. Available: {$available}"
            );
        }

        return self::CONFIGS[$supplier];
    }

    public static function available(): array
    {
        return array_keys(self::CONFIGS);
    }

    /**
     * Read Excel file and return rows as associative arrays keyed by column header.
     * Auto-detects header row by searching for the sku column header.
     */
    public static function readExcel(string $filePath, array $config): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rawRows     = $sheet->toArray(null, true, true, false);

        // Find header row
        $headerRowIdx = null;
        $headers      = [];

        foreach ($rawRows as $i => $row) {
            foreach ($row as $cell) {
                if (trim((string) $cell) === $config['sku_col']) {
                    $headerRowIdx = $i;
                    $headers      = $row;
                    break 2;
                }
            }
        }

        if ($headerRowIdx === null) {
            throw new \RuntimeException(
                "Header row not found. Expected column '{$config['sku_col']}' in file."
            );
        }

        $result = [];
        for ($i = $headerRowIdx + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];

            $nonEmpty = array_filter($row, fn($v) => $v !== null && trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $colIdx => $colName) {
                if ($colName !== null && trim((string) $colName) !== '') {
                    $mapped[trim((string) $colName)] = $row[$colIdx] ?? null;
                }
            }

            $result[] = $mapped;
        }

        return $result;
    }

    /**
     * Parse a price value from raw Excel cell (handles int/float, comma/dot separators).
     */
    public static function parsePrice(mixed $raw): ?float
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return $raw > 0 ? round((float) $raw, 2) : null;
        }

        $str = trim((string) $raw);
        $str = str_replace(["\xc2\xa0", "\u{00A0}", ' '], '', $str);
        $str = preg_replace('/[^\d.,]/u', '', $str); // убрать р., $, BYN и др.

        $hasComma = str_contains($str, ',');
        $hasDot   = str_contains($str, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($str, '.') > strrpos($str, ',')) {
                $str = str_replace(',', '', $str);         // 1,234.56
            } else {
                $str = str_replace('.', '', $str);          // 1.234,56
                $str = str_replace(',', '.', $str);
            }
        } elseif ($hasComma) {
            $str = str_replace(',', '.', $str);
        }

        if (! is_numeric($str)) {
            return null;
        }

        $value = (float) $str;
        return $value > 0 ? round($value, 2) : null;
    }
}
