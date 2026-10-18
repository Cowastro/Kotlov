<?php

namespace App\Services\Suppliers;

use App\Models\SupplierProduct;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class TeplouchetPriceList
{
    public const SOURCE_DATE = '2026-10-12';

    /** @return array{source_date:string, supplier:array<string,string>, rows:array<int,array<string,mixed>>, references:array<int,array<string,mixed>>} */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Файл ТЕПЛОУЧЁТ не найден: {$path}");
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $payload['rows'] = array_map(fn (array $row): array => $this->normalizeAvailability($row), $payload['rows'] ?? []);
            $this->validate($payload);

            return $payload;
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $main = $book->getSheetByName('склад Минск');
        $stock = $book->getSheetByName('DAB (заказ до 10 р.дней)');
        $cross = $book->getSheetByName('ТАБЛИЦА ПЕРЕПОДБОРА');

        if (! $main || ! $stock || ! $cross) {
            throw new RuntimeException('В прайсе ТЕПЛОУЧЁТ отсутствуют обязательные листы DAB, склада или аналогов.');
        }

        $availability = [];
        for ($row = 2; $row <= $stock->getHighestRow(); $row++) {
            $article = $this->article($this->cell($stock, 'B', $row));
            if ($article !== '') {
                $availability[$article] = [
                    'name' => $this->cell($stock, 'C', $row),
                    'unit' => $this->cell($stock, 'D', $row),
                    'text' => $this->cell($stock, 'E', $row),
                ];
            }
        }

        $analogs = $this->crossReference($cross);
        $references = [];
        foreach ($analogs as $article => $selection) {
            $references[] = [
                'article' => $article,
                'article_key' => $article,
                'name' => $selection['technical']['model'] ?? '',
                'model' => $selection['technical']['model'] ?? '',
                'source_url' => null,
                'source_row' => $selection['source_row'],
                'analogs' => [
                    'grundfos' => array_values(array_unique($selection['grundfos'])),
                    'wilo' => array_values(array_unique($selection['wilo'])),
                ],
                'technical' => $selection['technical'],
            ];
        }
        $rows = [];
        $currentBrand = '';
        for ($row = 3; $row <= $main->getHighestRow(); $row++) {
            $brand = $this->cell($main, 'A', $row);
            $article = $this->article($this->cell($main, 'B', $row));
            $name = $this->cell($main, 'C', $row);
            $wholesale = $this->number($main->getCell("E{$row}")->getCalculatedValue());
            $retail = $this->number($main->getCell("F{$row}")->getCalculatedValue());

            if ($brand !== '' && ! preg_match('/^(?:новинка|акция)$/ui', $brand)) {
                $currentBrand = $brand;
            }
            $effectiveBrand = preg_match('/^(?:новинка|акция)$/ui', $brand) || $brand === '' ? $currentBrand : $brand;

            if (! str_contains(mb_strtolower($effectiveBrand), 'dab') || $article === '' || $name === '' || $wholesale <= 0 || $retail <= 0) {
                continue;
            }

            $inline = $this->inlineAnalogs($this->cell($main, 'D', $row));
            $selection = $analogs[$article] ?? ['grundfos' => [], 'wilo' => [], 'technical' => []];
            $stockRow = $availability[$article] ?? null;
            $rows[] = $this->normalizeAvailability([
                'article' => $this->cell($main, 'B', $row),
                'article_key' => $article,
                'name' => $name,
                'model' => $this->model($name),
                'wholesale' => round($wholesale, 2),
                'retail' => round($retail, 2),
                'margin_percent' => round((($retail - $wholesale) / $retail) * 100, 2),
                'in_stock' => $stockRow === null,
                'stock_quantity' => null,
                'stock_status' => $stockRow ? 'preorder' : SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE,
                'stock_text' => $stockRow
                    ? 'Поставка со склада DAB до 10 рабочих дней; наличие у поставщика: '.$stockRow['text']
                    : 'В наличии: склад Минск',
                'warehouse_name' => $stockRow ? 'Склад DAB' : 'Минск',
                'delivery_days' => $stockRow ? 10 : 0,
                'unit' => $stockRow['unit'] ?? null,
                'source_url' => $this->firstUrl($main, $row) ?: 'https://teplouchet.by/',
                'source_row' => $row,
                'analog_note' => $this->cell($main, 'D', $row) ?: null,
                'analogs' => [
                    'grundfos' => array_values(array_unique([...$inline['grundfos'], ...$selection['grundfos']])),
                    'wilo' => array_values(array_unique([...$inline['wilo'], ...$selection['wilo']])),
                ],
                'technical' => $selection['technical'],
            ]);
        }

        $payload = [
            'source_date' => self::SOURCE_DATE,
            'supplier' => [
                'name' => 'ООО «ТЕПЛОУЧЁТ»',
                'address' => 'г. Минск, ул. Серова, 22а',
                'email' => 'vzljotbel@gmail.com',
                'phone' => '+375 29 342-01-07',
                'website' => 'https://teplouchet.by/',
            ],
            'rows' => $rows,
            'references' => $references,
        ];
        $this->validate($payload);

        return $payload;
    }

    public function article(?string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[^\p{L}\p{N}-]+/u', '', trim((string) $value)));
    }

    public function modelKey(?string $value): string
    {
        $value = mb_strtolower((string) $value);
        $value = preg_replace('/страна происхождения.*$/u', '', $value);
        $value = preg_replace('/\b(?:циркуляционный|центробежный|дренажный|скважинный|насосная|станция|насос|dab|pumps|италия)\b/ui', ' ', $value);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    public function coreModelKey(?string $value): string
    {
        $value = mb_strtolower((string) $value);
        $value = preg_replace('/страна происхождения.*$/u', '', $value);
        $value = preg_replace(
            '/\b(?:циркуляционный|центробежный|дренажный|скважинный|поверхностный|погружной|фекальный|канализационный|канализационная|повысительный|повысительная|насосная|установка|станция|насос|для|воды|водоснабжения|dab|pumps|италия)\b/ui',
            ' ',
            $value,
        );

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    public function sourceUrlKey(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $host = mb_strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = mb_strtolower(rtrim((string) parse_url($value, PHP_URL_PATH), '/'));

        return $host.$path;
    }

    /** @return array<string,array{grundfos:array<int,string>,wilo:array<int,string>,technical:array<string,string>,source_row:int}> */
    private function crossReference($sheet): array
    {
        $result = [];
        $current = null;
        for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
            $article = $this->article($this->cell($sheet, 'A', $row));
            if ($article !== '' && preg_match('/\d/u', $article)) {
                $current = $article;
                $result[$current] = [
                    'grundfos' => [],
                    'wilo' => [],
                    'source_row' => $row,
                    'technical' => array_filter([
                        'model' => $this->cell($sheet, 'B', $row),
                        'voltage' => $this->cell($sheet, 'C', $row),
                        'dn' => $this->cell($sheet, 'D', $row),
                        'length_mm' => $this->cell($sheet, 'E', $row),
                        'head_m' => $this->cell($sheet, 'F', $row),
                        'flow_m3_h' => $this->cell($sheet, 'G', $row),
                    ], fn ($value) => $value !== ''),
                ];
            }
            if ($current === null) {
                continue;
            }
            foreach (['H' => 'grundfos', 'I' => 'wilo'] as $column => $brand) {
                $value = $this->cell($sheet, $column, $row);
                if ($value !== '' && $value !== '-') {
                    $result[$current][$brand][] = $value;
                }
            }
        }

        return $result;
    }

    private function firstUrl($sheet, int $row): string
    {
        foreach (['G', 'H', 'I', 'J'] as $column) {
            $value = $this->cell($sheet, $column, $row);
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }

        return '';
    }

    /** @return array{grundfos:array<int,string>,wilo:array<int,string>} */
    private function inlineAnalogs(string $note): array
    {
        $result = ['grundfos' => [], 'wilo' => []];
        if (preg_match('/GRUNDFOS\s+(.+?)(?=\s+WILO\b|$)/iu', $note, $match)) {
            $result['grundfos'][] = trim($match[1]);
        }
        if (preg_match('/WILO\s+(.+)$/iu', $note, $match)) {
            $result['wilo'][] = trim($match[1]);
        }

        return $result;
    }

    private function model(string $name): string
    {
        $name = preg_replace('/страна происхождения.*$/ui', '', $name);
        $name = preg_replace('/^\s*насос\s*,*\s*/ui', '', (string) $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name), " .\t\n\r\0\x0B");
    }

    private function cell($sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell($column.$row)->getCalculatedValue());
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.-]+/u', '', (string) $value));
    }

    private function validate(array $payload): void
    {
        if (($payload['source_date'] ?? null) !== self::SOURCE_DATE || count($payload['rows'] ?? []) < 150) {
            throw new RuntimeException('Прайс ТЕПЛОУЧЁТ не прошёл контроль полноты или даты.');
        }
        foreach ($payload['rows'] as $row) {
            if (($row['wholesale'] ?? 0) <= 0 || ($row['retail'] ?? 0) <= ($row['wholesale'] ?? 0)) {
                throw new RuntimeException('В прайсе ТЕПЛОУЧЁТ обнаружена некорректная пара опт/РРЦ.');
            }
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeAvailability(array $row): array
    {
        $stockText = mb_strtolower(trim((string) ($row['stock_text'] ?? '')));
        $isSupplierOrder = str_contains($stockText, 'заказ до 10')
            || str_contains($stockText, 'до 10 рабочих дней')
            || (int) ($row['delivery_days'] ?? 0) === 10;

        if ($isSupplierOrder) {
            $row['in_stock'] = false;
            $row['stock_quantity'] = null;
            $row['stock_status'] = 'preorder';
            $row['stock_text'] = 'Поставка со склада DAB до 10 рабочих дней; наличие у поставщика: больше 2';
            $row['warehouse_name'] = 'Склад DAB';
            $row['delivery_days'] = 10;
        } else {
            $row['in_stock'] = true;
            $row['stock_quantity'] = null;
            $row['stock_status'] = SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE;
            $row['stock_text'] = 'В наличии: склад Минск';
            $row['warehouse_name'] = 'Минск';
            $row['delivery_days'] = 0;
        }

        return $row;
    }
}
