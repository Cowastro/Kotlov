<?php

namespace App\Services;

use App\Models\B2bSpecification;
use App\Models\B2bSpecificationAttachment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class B2bSpecificationParser
{
    private const SPREADSHEET_EXTENSIONS = ['xlsx', 'xls', 'csv', 'ods'];

    private const MAX_ROWS = 5000;

    private const MAX_COLUMNS = 30;

    /** @var array<string, array<int, string>> */
    private const HEADERS = [
        'sku' => ['артикул', 'код', 'код товара', 'код позиции', 'sku', 'vendor code'],
        'name' => ['наименование', 'название', 'товар', 'описание', 'позиция', 'product'],
        'quantity' => ['количество', 'кол во', 'колво', 'кол.', 'qty', 'quantity'],
        'unit' => ['ед', 'ед изм', 'единица', 'единица измерения', 'unit'],
    ];

    /** @return array{status:string,items:int,matched:int,message:string} */
    public function parse(B2bSpecification $specification): array
    {
        $attachments = $specification->attachments()->get();
        $spreadsheets = $attachments->filter(fn (B2bSpecificationAttachment $attachment): bool => in_array(
            mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)),
            self::SPREADSHEET_EXTENSIONS,
            true,
        ));

        if ($spreadsheets->isEmpty()) {
            return $this->finish($specification, 'manual', 0, 0, 'PDF и изображения переданы менеджеру для ручной обработки.');
        }

        [$skuMatches, $nameMatches] = $this->productIndexes();
        $rows = [];
        $errors = [];

        foreach ($spreadsheets as $attachment) {
            try {
                foreach ($this->readAttachment($attachment) as $item) {
                    [$productId, $confidence, $reason] = $this->match($item, $skuMatches, $nameMatches);
                    $rows[] = [
                        ...$item,
                        'b2b_specification_id' => $specification->id,
                        'b2b_specification_attachment_id' => $attachment->id,
                        'status' => $productId ? 'matched' : 'unmatched',
                        'product_id' => $productId,
                        'confidence' => $confidence,
                        'match_reason' => $reason,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            } catch (\Throwable) {
                $errors[] = $attachment->original_name;
            }
        }

        DB::transaction(function () use ($specification, $rows): void {
            $specification->items()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                $specification->items()->insert($chunk);
            }
        });

        $matched = collect($rows)->where('status', 'matched')->count();
        if ($rows === []) {
            $message = $errors === []
                ? 'В таблице не найдены строки с товарами. Менеджер обработает файл вручную.'
                : 'Не удалось прочитать таблицу. Менеджер обработает файл вручную.';

            return $this->finish($specification, 'failed', 0, 0, $message);
        }

        $message = 'Извлечено позиций: '.count($rows).", автоматически сопоставлено: {$matched}.";
        if ($errors !== []) {
            $message .= ' Не прочитаны файлы: '.implode(', ', $errors).'.';
        }

        return $this->finish($specification, 'parsed', count($rows), $matched, $message);
    }

    /** @return array<int, array<string, mixed>> */
    private function readAttachment(B2bSpecificationAttachment $attachment): array
    {
        $path = Storage::disk($attachment->disk)->path($attachment->path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = min(self::MAX_ROWS, max(1, $sheet->getHighestDataRow()));
        $lastColumn = min(self::MAX_COLUMNS, max(1, Coordinate::columnIndexFromString($sheet->getHighestDataColumn())));
        // Uploaded formulas are treated as inert data: never evaluate customer input.
        $values = $sheet->rangeToArray('A1:'.Coordinate::stringFromColumnIndex($lastColumn).$lastRow, null, false, true, false);
        $spreadsheet->disconnectWorksheets();

        [$headerRow, $columns] = $this->detectColumns($values);
        $items = [];

        foreach (array_slice($values, $headerRow + 1, null, true) as $index => $row) {
            $sku = $this->cell($row, $columns['sku']);
            $name = $this->cell($row, $columns['name']);
            if ($sku === '' && $name === '') {
                continue;
            }

            if ($name !== '' && preg_match('/^(итого|всего|total)\b/ui', trim($name))) {
                continue;
            }

            $quantity = $this->quantity($this->cell($row, $columns['quantity']));
            $items[] = [
                'source_row' => $index + 1,
                'source_sku' => $sku !== '' ? Str::limit($sku, 255, '') : null,
                'source_name' => $name !== '' ? $name : null,
                'quantity' => $quantity,
                'unit' => ($unit = $this->cell($row, $columns['unit'])) !== '' ? Str::limit($unit, 40, '') : null,
                'raw_data' => json_encode(array_values($row), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ];
        }

        return $items;
    }

    /** @return array{int, array{sku:?int,name:?int,quantity:?int,unit:?int}} */
    private function detectColumns(array $rows): array
    {
        $bestRow = 0;
        $bestScore = 0;
        $best = ['sku' => null, 'name' => null, 'quantity' => null, 'unit' => null];

        foreach (array_slice($rows, 0, 20, true) as $rowIndex => $row) {
            $found = ['sku' => null, 'name' => null, 'quantity' => null, 'unit' => null];
            foreach ($row as $column => $value) {
                $header = $this->normalize((string) $value);
                foreach (self::HEADERS as $type => $synonyms) {
                    if ($header !== '' && in_array($header, $synonyms, true)) {
                        $found[$type] ??= $column;
                    }
                }
            }
            $score = collect($found)->filter(fn ($value): bool => $value !== null)->count();
            if ($found['name'] !== null) {
                $score += 2;
            }
            if ($score > $bestScore) {
                $bestRow = $rowIndex;
                $bestScore = $score;
                $best = $found;
            }
        }

        if ($bestScore < 2) {
            $bestRow = -1;
            $best = ['sku' => 0, 'name' => 1, 'quantity' => 2, 'unit' => 3];
        } elseif ($best['name'] === null && $best['sku'] !== null) {
            $best['name'] = $best['sku'] === 0 ? 1 : 0;
        }

        return [$bestRow, $best];
    }

    /** @return array{array<string, int|null>, array<string, int|null>} */
    private function productIndexes(): array
    {
        $sku = [];
        $name = [];

        Product::query()->active()->notArchived()->select(['id', 'sku', 'name'])->chunkById(1000, function ($products) use (&$sku, &$name): void {
            foreach ($products as $product) {
                if (($key = $this->normalize((string) $product->sku)) !== '') {
                    $sku[$key] = array_key_exists($key, $sku) ? null : (int) $product->id;
                }
                if (($key = $this->normalize((string) $product->name)) !== '') {
                    $name[$key] = array_key_exists($key, $name) ? null : (int) $product->id;
                }
            }
        });

        return [$sku, $name];
    }

    /** @return array{?int, ?float, ?string} */
    private function match(array $item, array $skuMatches, array $nameMatches): array
    {
        $sku = $this->normalize((string) ($item['source_sku'] ?? ''));
        if ($sku !== '' && isset($skuMatches[$sku])) {
            return [$skuMatches[$sku], 100, 'exact_sku'];
        }

        $name = $this->normalize((string) ($item['source_name'] ?? ''));
        if ($name !== '' && isset($nameMatches[$name])) {
            return [$nameMatches[$name], 95, 'exact_name'];
        }

        return [null, null, null];
    }

    private function cell(array $row, ?int $column): string
    {
        if ($column === null || ! array_key_exists($column, $row)) {
            return '';
        }

        return trim((string) $row[$column]);
    }

    private function quantity(string $value): float
    {
        $normalized = preg_replace('/[^0-9,.-]/u', '', $value);
        $number = (float) str_replace(',', '.', (string) $normalized);

        return $number > 0 ? min($number, 999999999.999) : 1;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['ё', '№'], ['е', ''], $value);

        return trim((string) preg_replace('/[^\pL\pN]+/u', ' ', $value));
    }

    /** @return array{status:string,items:int,matched:int,message:string} */
    private function finish(B2bSpecification $specification, string $status, int $items, int $matched, string $message): array
    {
        $specification->update([
            'parsing_status' => $status,
            'parse_message' => $message,
            'parsed_at' => now(),
        ]);

        return compact('status', 'items', 'matched', 'message');
    }
}
