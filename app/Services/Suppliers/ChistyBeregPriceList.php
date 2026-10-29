<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * «Остатки и цены Чистого берега на ДДММГГГГ.xls» — sent every morning. The flat sheet «Список»:
 * Код (the supplier's 8-digit code, also «Артикул» on bereg.by), Категория, Бренд, Номенклатура,
 * Код производителя, Единица, Кол-во в упаковке, Остаток, Резерв, Свободно, Цена ОПТ с НДС, РРЦ с НДС.
 * The uploaded file stays on the server only (private disk), never in the repository.
 */
class ChistyBeregPriceList
{
    public const FILE = 'suppliers/chisty-bereg/latest.xls';

    public const SHEET = 'Список';

    /** @return list<array{code:string,category:string,brand:string,name:string,maker_code:string,unit:string,pack:mixed,stock:float,reserve:float,free:float,wholesale:float,rrc:float}> */
    public static function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        $sheet = $reader->load($path)->getSheetByName(self::SHEET);
        if (! $sheet) {
            return [];
        }

        $rows = [];
        $columns = null;
        foreach ($sheet->toArray(null, true, false, false) as $cells) {
            $cells = array_map(fn ($cell) => is_string($cell) ? trim((string) preg_replace('/\s+/u', ' ', $cell)) : $cell, $cells);
            // The header row names the columns; the report may shift them.
            if ($columns === null) {
                if (in_array('Код', $cells, true) && in_array('Номенклатура', $cells, true)) {
                    $columns = array_flip(array_filter($cells, fn ($cell) => is_string($cell) && $cell !== ''));
                }

                continue;
            }
            $cell = fn (string $name) => isset($columns[$name]) ? ($cells[$columns[$name]] ?? null) : null;
            $code = trim((string) $cell('Код'));
            $name = trim((string) $cell('Номенклатура'));
            if (! preg_match('/^\d{5,}$/', $code) || $name === '') {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'category' => (string) $cell('Категория'),
                'brand' => (string) $cell('Бренд'),
                'name' => $name,
                'maker_code' => trim((string) $cell('Код производителя')),
                'unit' => (string) $cell('Единица измерения'),
                'pack' => $cell('Кол-во в упаковке'),
                'stock' => self::number($cell('Остаток')),
                'reserve' => self::number($cell('Резерв')),
                'free' => self::number($cell('Свободно')),
                'wholesale' => round(self::number($cell('Цена ОПТ с НДC, BYN') ?? $cell('Цена ОПТ с НДС, BYN')), 2),
                'rrc' => round(self::number($cell('РРЦ с НДС, BYN')), 2),
            ];
        }

        return $rows;
    }

    public static function store(string $uploadedPath): void
    {
        Storage::disk('local')->put(self::FILE, (string) file_get_contents($uploadedPath));
    }

    public static function storedPath(): ?string
    {
        return Storage::disk('local')->exists(self::FILE) ? Storage::disk('local')->path(self::FILE) : null;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : (float) str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], (string) $value);
    }
}
