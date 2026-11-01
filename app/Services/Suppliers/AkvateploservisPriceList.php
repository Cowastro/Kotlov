<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * «GALMET прайс-лист» of ООО «Акватеплосервис» (galmet.by), the importer of Galmet (Poland) and Apamet
 * (Turkey) in Belarus. One sheet of sections («TOWER», «NEPTUN KOMBI»…): a goods row has the Galmet article
 * (26-308070N; an Apamet row has none), the name, опт and РРЦ in BYN and the stock («склад», «под заказ»,
 * «ожидается поставка»…). A pair of rows (R/L, two colours) shares the price, stock and specs in merged
 * cells; the specs are the text row under the goods («Объем 153л, теплообменник…»). The file stays on the
 * server only.
 */
class AkvateploservisPriceList
{
    public const FILE = 'suppliers/akvateploservis/latest.xls';

    private const ARTICLE = '/^(?:\d{2}-\d{6}N?|M-\d{4,})$/u';

    /** @return list<array{code:string,article:string,brand:string,section:string,name:string,specs:string,wholesale:float,rrc:float,stock:string,row:int}> */
    public static function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $sheet = $reader->load($path)->getSheet(0);

        // A merged cell gives its value to every cell of the range (the pair R/L shares price and stock).
        $merged = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$from, $to] = Coordinate::rangeBoundaries($range);
            $value = $sheet->getCell(Coordinate::stringFromColumnIndex($from[0]).$from[1])->getCalculatedValue();
            for ($row = $from[1]; $row <= $to[1]; $row++) {
                for ($column = $from[0]; $column <= $to[0]; $column++) {
                    $merged[$row][$column] = $value;
                }
            }
        }
        $cell = function (int $row, int $column) use ($sheet, $merged): mixed {
            $value = $merged[$row][$column] ?? $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getCalculatedValue();

            return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : $value;
        };

        $rows = [];
        $seen = [];
        $section = '';
        $waitingSpecs = [];
        $last = $sheet->getHighestDataRow();
        for ($row = 1; $row <= $last; $row++) {
            $a = (string) ($cell($row, 1) ?? '');
            $b = (string) ($cell($row, 2) ?? '');
            // An Apamet goods row has no article; its section title is the same name in capitals.
            $isApamet = $a === '' && preg_match('/^apamet\b/iu', $b) === 1 && mb_strtoupper($b) !== $b;
            if (preg_match(self::ARTICLE, $a) !== 1 && ! $isApamet) {
                // A section title: the series name over its description in capitals.
                if ($a === '' && $b !== '' && ! is_numeric($cell($row, 3)) && ! is_numeric($cell($row, 4))) {
                    $section = trim(Str::before(Str::before($b, ' ВОДОНАГРЕВАТЕЛ'), ' БОЙЛЕР')) ?: $b;
                    $waitingSpecs = [];
                } elseif (($b === '' || $b === $a) && mb_strlen($a) > 30 && $waitingSpecs !== []) {
                    // The specs row under the goods describes every row of the group above it.
                    foreach ($waitingSpecs as $index) {
                        $rows[$index]['specs'] = $rows[$index]['specs'] ?: $a;
                    }
                    $waitingSpecs = [];
                }

                continue;
            }
            $name = $b;
            $wholesale = is_numeric($cell($row, 3)) ? (float) $cell($row, 3) : 0.0;
            $rrc = is_numeric($cell($row, 4)) ? (float) $cell($row, 4) : 0.0;
            $stock = mb_strtolower(trim((string) ($cell($row, 5) ?? '')));
            if ($stock === '') {
                // The stock of a single row may stand one row lower.
                $below = mb_strtolower(trim((string) ($cell($row + 1, 5) ?? '')));
                $stock = (string) ($cell($row + 1, 1) ?? '') === '' && (string) ($cell($row + 1, 2) ?? '') === '' ? $below : '';
            }
            if ($name === '') {
                continue;
            }
            $code = $isApamet ? 'apamet-'.Str::slug($name) : mb_strtoupper($a);
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $rows[] = [
                'code' => $code,
                'article' => $isApamet ? '' : mb_strtoupper($a),
                'brand' => $isApamet ? 'Apamet' : 'Galmet',
                'section' => $section,
                'name' => $name,
                'specs' => '',
                'wholesale' => round($wholesale, 2),
                'rrc' => round($rrc, 2),
                'stock' => $stock,
                'row' => $row,
            ];
            $waitingSpecs[] = count($rows) - 1;
        }

        return $rows;
    }

    /** «склад» is in stock; discontinued rows are not offered; everything else is ordered. */
    public static function stockStatus(string $stock, float $rrc): string
    {
        return match (true) {
            str_contains($stock, 'снят') => 'discontinued',
            $stock === 'склад' => 'in_stock',
            default => 'preorder',
        };
    }

    public static function store(string $uploadedPath): void
    {
        Storage::disk('local')->put(self::FILE, (string) file_get_contents($uploadedPath));
    }

    public static function storedPath(): string
    {
        return Storage::disk('local')->exists(self::FILE) ? Storage::disk('local')->path(self::FILE) : '';
    }

    /**
     * The model of a name as a set of words: «TOWER SGW(S) 300 Skay (w/s) FL, цвет - серый» and «Бойлер косвенного
     * нагрева Galmet Tower SGW(S) 300 Skay FL серый» meet; «80-R … справа», «80R» and «правое подключение» are «80 r».
     * The specs after the first comma are left out, the colour is kept.
     */
    public static function modelKey(string $name): string
    {
        $name = mb_strtolower(str_replace('ё', 'е', $name));
        $colour = preg_match('/(?<![а-я])(сер|бел|черн|граф)(?:ый|ая|ое|ого|ит)(?![а-я])/u', $name, $c) ? ['сер' => 'серый', 'бел' => 'белый', 'черн' => 'черный', 'граф' => 'графит'][$c[1]] : '';
        $side = preg_match('/справа|правое|правый/u', $name) ? 'r' : (preg_match('/слева|левое|левый/u', $name) ? 'l' : '');
        $name = (string) preg_replace('/\([^)]*подключени[^)]*\)|\(w\/s\)|\bw\/s\b/u', ' ', $name);
        // The specs after « - » or the first comma of the price list name («, возможность установки ТЭНа…»).
        $name = preg_match('/^(.*?\d.*?)\s+-\s(?!\d)/u', $name, $m) ? $m[1] : $name;
        $name = preg_match('/^(.*?\d.*?),\s/u', $name, $m) ? $m[1] : $name;
        // «BUFOR SG(B) 500» is the card «BUFOR SG 500».
        $name = str_replace(['sg-w', 'sg(k)', 'sg(к)', 'sg(b)'], ['sgw', 'sgk', 'sgk', 'sg'], $name);
        $name = (string) preg_replace('/\(([a-zа-я])\)/u', '$1', $name);
        $name = (string) preg_replace('/(\d)([a-zа-я])\b/u', '$1 $2', $name);
        $name = (string) preg_replace('/([a-z])(\d)/u', '$1 $2', $name);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name) ?: [];
        $key = [];
        foreach ($words as $word) {
            if ($word === '' || in_array($word, self::DESCRIPTIVE, true)) {
                continue;
            }
            // SG(К)М written with a Cyrillic К is the same code.
            $key[preg_match('/\d/', $word) || mb_strlen($word) <= 2 ? strtr($word, ['к' => 'k', 'м' => 'm', 'н' => 'h']) : $word] = true;
        }
        foreach (array_filter([$colour, $side]) as $extra) {
            $key[$extra] = true;
        }
        $key = array_keys($key);
        sort($key);

        return implode(' ', $key);
    }

    /** Words that say what the item is, not which one. */
    private const DESCRIPTIVE = ['galmet', 'apamet', 'бойлер', 'косвенного', 'нагрева', 'водонагреватель', 'косвенный', 'комбинированный',
        'электрический', 'накопительный', 'напольный', 'навесной', 'настенный', 'skay', 'fl', 'pur', 'цвет', 'серый', 'белый', 'подключение',
        'подключением', 'левое', 'правое', 'слева', 'справа', 'теплообменника', 'монтаж', 'для', 'с', 'и', 'на', 'в', 'мм', 'буферная', 'емкость',
        'вертикальный', 'накопитель', 'гвс', 'бойлеры', 'косвенные'];
}
