<?php

namespace App\Services\PriceList;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SvahGroupPriceList
{
    /**
     * @return array<int, array{
     *     source_row:int,
     *     section:string,
     *     brand:string,
     *     article:string,
     *     signature:string,
     *     name:string,
     *     partner_price:float,
     *     retail_price:float,
     *     stock_quantity:int
     * }>
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Файл прайса не найден: {$path}");
        }

        $extension = mb_strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xls', 'xlsx'], true)) {
            throw new \RuntimeException('Прайс СВАГ Груп должен быть в формате XLS или XLSX.');
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        [$headerIndex, $columns] = $this->detectColumns($rawRows);

        $section = '';
        $items = [];
        $seen = [];

        for ($index = $headerIndex + 1; $index < count($rawRows); $index++) {
            $row = $rawRows[$index];
            $name = $this->clean((string) ($row[$columns['name']] ?? ''));
            $partnerRaw = $row[$columns['partner_price']] ?? null;
            $partnerPrice = $this->number($partnerRaw);
            $retailPrice = $this->number($row[$columns['retail_price']] ?? null);
            $stockQuantity = $this->number($row[$columns['stock_quantity']] ?? null);

            if ($name === '') {
                continue;
            }

            if ($retailPrice === null
                && $stockQuantity === null
                && ($partnerPrice === null || str_contains(mb_strtolower((string) $partnerRaw), 'скидк'))) {
                $section = $name;

                continue;
            }

            $brand = $this->brand($name);
            $signature = $this->signature($name, $brand);
            if ($signature === null) {
                throw new \RuntimeException(sprintf(
                    'Не удалось определить бренд или модель в строке %d: %s',
                    $index + 1,
                    $name,
                ));
            }

            if ($partnerPrice === null || $partnerPrice <= 0) {
                throw new \RuntimeException('Некорректная партнёрская цена в строке '.($index + 1).'.');
            }
            if ($retailPrice === null || $retailPrice <= 0) {
                throw new \RuntimeException('Некорректная отпускная цена в строке '.($index + 1).'.');
            }
            if ($stockQuantity === null || $stockQuantity < 0 || floor($stockQuantity) !== $stockQuantity) {
                throw new \RuntimeException('Некорректный остаток в строке '.($index + 1).'.');
            }
            if (isset($seen[$signature])) {
                throw new \RuntimeException("Модель TATRA повторяется в прайсе: {$name}");
            }

            $seen[$signature] = true;
            $items[] = [
                'source_row' => $index + 1,
                'section' => $section,
                'article' => $this->article($signature),
                'signature' => $signature,
                'brand' => $this->brandName($brand),
                'name' => $name,
                'partner_price' => round($partnerPrice, 2),
                'retail_price' => round($retailPrice, 2),
                'stock_quantity' => (int) $stockQuantity,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('В прайсе не найдены товарные строки TATRA, SVAH или HOT.');
        }

        return $items;
    }

    public function signature(string $name, ?string $brand = null): ?string
    {
        $normalized = mb_strtoupper($this->clean($name));
        $brand ??= $this->brand($normalized);
        if ($brand === null) {
            return null;
        }

        $normalized = str_replace(['TATRA', 'ТАТРА', 'SVAH', 'HOT'], '', $normalized);
        $normalized = str_replace(
            ['КТ', 'Н', 'П', 'Л', 'Т', 'Ё', '–', '—'],
            ['KT', 'N', 'P', 'L', 'T', 'Е', '-', '-'],
            $normalized,
        );
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if (preg_match('/KT\s*(\d+)\s*(?:-\s*(N(?:P|L)?\d?))?/u', $normalized, $match)) {
            return $brand.':boiler:kt'.(int) $match[1].(! empty($match[2]) ? '-'.mb_strtolower($match[2]) : '');
        }

        if (! preg_match('/T\s*(\d{1,3})\s*(T)?/u', $normalized, $match)) {
            return null;
        }

        $signature = $brand.':stove:t'.(int) $match[1].(! empty($match[2]) ? 't' : '');
        if (preg_match('/БАК(?:ОМ)?\s*(\d+)\s*[ЛL]/u', $normalized, $tank)) {
            $signature .= ':tank'.(int) $tank[1];
        }

        return $signature;
    }

    private function article(string $signature): string
    {
        [$brand] = explode(':', $signature, 2);
        $model = preg_replace('/^[^:]+:(?:boiler|stove):/', '', $signature) ?? $signature;

        return mb_strtoupper($brand.'-'.str_replace(':', '-', $model));
    }

    private function brand(string $name): ?string
    {
        return match (true) {
            preg_match('/(?:татра|tatra)/iu', $name) === 1 => 'tatra',
            preg_match('/\bsvah\b/iu', $name) === 1 => 'svah',
            preg_match('/\bhot\b/iu', $name) === 1 => 'hot',
            default => null,
        };
    }

    private function brandName(?string $brand): string
    {
        return match ($brand) {
            'tatra' => 'Tatra',
            'svah' => 'SVAH',
            'hot' => 'HOT',
            default => throw new \RuntimeException('Неизвестный бренд в прайсе СВАГ Груп.'),
        };
    }

    /** @return array{0:int,1:array{name:int,partner_price:int,stock_quantity:int,retail_price:int}} */
    private function detectColumns(array $rows): array
    {
        foreach ($rows as $rowIndex => $row) {
            $columns = [];
            foreach ($row as $columnIndex => $value) {
                $header = mb_strtolower($this->clean((string) $value));
                if ($header === '') {
                    continue;
                }

                if (str_contains($header, 'наименование')) {
                    $columns['name'] = $columnIndex;
                } elseif (str_contains($header, 'остаток')) {
                    $columns['stock_quantity'] = $columnIndex;
                } elseif (str_contains($header, 'отпускн')) {
                    $columns['retail_price'] = $columnIndex;
                } elseif (str_contains($header, 'скидк') || str_contains($header, 'партнер')) {
                    $columns['partner_price'] = $columnIndex;
                }
            }

            if (count($columns) === 4) {
                /** @var array{name:int,partner_price:int,stock_quantity:int,retail_price:int} $columns */
                return [$rowIndex, $columns];
            }
        }

        throw new \RuntimeException(
            'Не найдены обязательные колонки: Наименование, Скидка/партнёрская цена, Остаток, Цена отпускная.',
        );
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = str_replace(["\xc2\xa0", ' '], '', trim((string) $value));
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $value)) ?? $value);
    }
}
