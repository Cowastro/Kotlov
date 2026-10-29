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

    /** @return array{status:string,items:int,matched:int,suggested:int,message:string} */
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

        [$skuMatches, $nameMatches, $products, $tokenIndex] = $this->productIndexes();
        $rows = [];
        $errors = [];

        foreach ($spreadsheets as $attachment) {
            try {
                foreach ($this->readAttachment($attachment) as $item) {
                    [$productId, $confidence, $reason] = $this->match($item, $skuMatches, $nameMatches);
                    $candidates = $productId ? [] : $this->candidates($item, $products, $tokenIndex);
                    $rows[] = [
                        ...$item,
                        'b2b_specification_id' => $specification->id,
                        'b2b_specification_attachment_id' => $attachment->id,
                        'status' => $productId ? 'matched' : ($candidates === [] ? 'unmatched' : 'suggested'),
                        'product_id' => $productId,
                        'confidence' => $confidence ?? ($candidates === [] ? null : round((float) $candidates[0]['score'] * 100, 2)),
                        'match_reason' => $reason ?? ($candidates === [] ? null : 'fuzzy_name'),
                        'candidates' => $candidates === [] ? null : json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
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
        $suggested = collect($rows)->where('status', 'suggested')->count();
        if ($rows === []) {
            $message = $errors === []
                ? 'В таблице не найдены строки с товарами. Менеджер обработает файл вручную.'
                : 'Не удалось прочитать таблицу. Менеджер обработает файл вручную.';

            return $this->finish($specification, 'failed', 0, 0, $message, 0);
        }

        $message = 'Извлечено позиций: '.count($rows).", автоматически сопоставлено: {$matched}.";
        if ($suggested > 0) {
            $message .= " Вариантов для проверки менеджером: {$suggested}.";
        }
        if ($errors !== []) {
            $message .= ' Не прочитаны файлы: '.implode(', ', $errors).'.';
        }

        return $this->finish($specification, 'parsed', count($rows), $matched, $message, $suggested);
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

    /** @return array{array<string, int|null>, array<string, int|null>, array<int, array{id:int,sku:?string,name:string,normalized:string}>, array<string, array<int, int>>} */
    private function productIndexes(): array
    {
        $sku = [];
        $name = [];
        $products = [];
        $tokenIndex = [];

        Product::query()->active()->notArchived()->select(['id', 'sku', 'name'])->chunkById(1000, function ($chunk) use (&$sku, &$name, &$products, &$tokenIndex): void {
            foreach ($chunk as $product) {
                if (($key = $this->normalize((string) $product->sku)) !== '') {
                    $sku[$key] = array_key_exists($key, $sku) ? null : (int) $product->id;
                }
                $normalized = $this->normalize((string) $product->name);
                if ($normalized !== '') {
                    $name[$normalized] = array_key_exists($normalized, $name) ? null : (int) $product->id;
                    $products[$product->id] = [
                        'id' => (int) $product->id,
                        'sku' => $product->sku,
                        'name' => (string) $product->name,
                        'normalized' => $normalized,
                    ];
                    foreach ($this->tokens($normalized) as $token) {
                        $tokenIndex[$token][] = (int) $product->id;
                    }
                }
            }
        });

        return [$sku, $name, $products, $tokenIndex];
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

    /**
     * Suggestions are manager-only hints. They never set product_id and can
     * reach a customer cart only after an explicit staff decision.
     *
     * @param  array<int, array{id:int,sku:?string,name:string,normalized:string}>  $products
     * @param  array<string, array<int, int>>  $tokenIndex
     * @return array<int, array{product_id:int,sku:?string,name:string,score:float}>
     */
    private function candidates(array $item, array $products, array $tokenIndex): array
    {
        $sourceName = (string) ($item['source_name'] ?? '');
        $normalized = $this->normalize($sourceName);
        if (mb_strlen($normalized) < 8) {
            return [];
        }

        $candidateIds = collect($this->tokens($normalized))
            ->flatMap(fn (string $token): array => $tokenIndex[$token] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(300);

        return $candidateIds
            ->map(fn (int $id): ?array => $products[$id] ?? null)
            ->filter()
            ->filter(fn (array $product): bool => $this->hasCompatibleTechnicalValues($sourceName, $product['name']))
            ->map(function (array $product) use ($normalized): array {
                similar_text($normalized, $product['normalized'], $score);

                return [
                    'product_id' => $product['id'],
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'score' => round($score / 100, 4),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] >= 0.62)
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function tokens(string $normalized): array
    {
        return collect(explode(' ', $normalized))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    private function hasCompatibleTechnicalValues(string $source, string $candidate): bool
    {
        $sourceValues = $this->technicalValues($source);
        $candidateValues = $this->technicalValues($candidate);

        return $sourceValues === [] || $candidateValues === [] || $sourceValues === $candidateValues;
    }

    /** @return array<int, string> */
    private function technicalValues(string $value): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', mb_strtolower($value), $matches);
        $values = array_map(function (string $number): string {
            $number = str_replace(',', '.', $number);

            return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
        }, $matches[0] ?? []);
        sort($values, SORT_NATURAL);

        return array_values(array_unique($values));
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

    /** @return array{status:string,items:int,matched:int,suggested:int,message:string} */
    private function finish(B2bSpecification $specification, string $status, int $items, int $matched, string $message, int $suggested = 0): array
    {
        $specification->update([
            'parsing_status' => $status,
            'parse_message' => $message,
            'parsed_at' => now(),
        ]);

        return compact('status', 'items', 'matched', 'suggested', 'message');
    }
}
