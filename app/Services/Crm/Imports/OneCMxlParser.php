<?php

namespace App\Services\Crm\Imports;

use RuntimeException;

class OneCMxlParser
{
    /** @return list<array<string, mixed>> */
    public function tradeContactReport(string $path): array
    {
        $content = $this->content($path);

        preg_match_all(
            '/\{20,12,\s*\{"#",([^\r\n}]+)\},\s*\{1,(0|1),(?:\s*\{"#","((?:""|[^"])*)"\}\s*)?\},0\},(\d+),/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $rows = [];
        foreach ($matches as $match) {
            $externalId = trim($match[1]);
            $column = (int) $match[4];
            $rows[$externalId][$column] = $match[2] === '1'
                ? trim(str_replace('""', '"', $match[3]))
                : null;
        }

        $result = [];
        foreach ($rows as $externalId => $row) {
            if (blank($row[1] ?? null)) {
                continue;
            }

            $result[] = [
                'external_id' => $externalId,
                'name' => $row[1],
                'public_name' => $row[2] ?? null,
                'business_region' => $row[3] ?? null,
                'manager_name' => $row[4] ?? null,
                'registered_at' => $this->date($row[5] ?? null),
                'abc_xyz_classification' => $row[6] ?? null,
                'legal_address' => $row[7] ?? null,
                'postal_address' => $row[8] ?? null,
                'actual_address' => $row[9] ?? null,
                'phones' => $this->phones($row[10] ?? null),
                'emails' => $this->emails($row[11] ?? null),
            ];
        }

        if ($result === []) {
            throw new RuntimeException('В MXL не найден отчёт «Контактная информация по партнёрам».');
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function accountingCounterparties(string $path): array
    {
        [$headers, $rows] = $this->grid($path);
        $map = array_flip(array_map(static fn ($value): string => trim((string) $value), $headers));

        foreach (['Наименование', 'Код', 'Полное наименование', 'Вид', 'УНП'] as $required) {
            if (! array_key_exists($required, $map)) {
                throw new RuntimeException("В бухгалтерском MXL отсутствует колонка «{$required}».");
            }
        }

        return array_values(array_filter(array_map(static function (array $row) use ($map): ?array {
            $name = trim((string) ($row[$map['Наименование']] ?? ''));
            $code = trim((string) ($row[$map['Код']] ?? ''));

            if ($name === '' || $code === '') {
                return null;
            }

            return [
                'external_id' => $code,
                'external_code' => $code,
                'name' => $name,
                'legal_name' => trim((string) ($row[$map['Полное наименование']] ?? '')) ?: null,
                'type_1c' => trim((string) ($row[$map['Вид']] ?? '')) ?: null,
                'unp' => preg_replace('/\s+/', '', (string) ($row[$map['УНП']] ?? '')) ?: null,
            ];
        }, $rows)));
    }

    /** @return array{0: list<?string>, 1: list<list<?string>>} */
    private function grid(string $path): array
    {
        $content = $this->content($path);
        preg_match('/\},0,2,\d+,0,0,(\d+),0,/', $content, $columnMatch);
        $columnCount = (int) ($columnMatch[1] ?? 0);

        preg_match_all('/\{16,\d+,/', $content, $markers, PREG_OFFSET_CAPTURE);
        $values = [];
        foreach ($markers[0] as $index => $marker) {
            $start = $marker[1];
            $end = $markers[0][$index + 1][1] ?? strlen($content);
            $cell = substr($content, $start, $end - $start);
            $values[] = preg_match('/\{"#","((?:""|[^"])*)"\}/s', $cell, $valueMatch)
                ? str_replace('""', '"', $valueMatch[1])
                : null;
        }

        if ($columnCount < 1 || count($values) < $columnCount) {
            throw new RuntimeException('Не удалось распознать табличный MXL 1С.');
        }

        $rows = array_chunk($values, $columnCount);
        $headers = array_shift($rows);

        return [$headers, $rows];
    }

    private function content(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Файл MXL не найден или недоступен для чтения.');
        }

        $content = file_get_contents($path);
        if ($content === false || ! str_starts_with($content, 'MOXCEL')) {
            throw new RuntimeException('Файл не является табличным документом 1С MXL.');
        }

        return substr($content, 15);
    }

    private function date(?string $value): ?string
    {
        if (! $value || ! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $parts)) {
            return null;
        }

        return "{$parts[3]}-{$parts[2]}-{$parts[1]}";
    }

    /** @return list<string> */
    private function phones(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $parts = preg_split('/[\r\n;,]+/u', $value) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), static fn (string $phone): bool => strlen(preg_replace('/\D+/', '', $phone) ?: '') >= 7
        )));
    }

    /** @return list<string> */
    private function emails(?string $value): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', (string) $value, $matches);

        return array_values(array_unique(array_map('mb_strtolower', $matches[0] ?? [])));
    }
}
