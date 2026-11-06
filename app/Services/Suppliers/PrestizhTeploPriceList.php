<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The price list of «Престиж Тепло» («Прайс Престиж Тепло DD.MM.YYYY.xlsm»): one sheet a brand (Vaillant, Protherm,
 * Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes…) or a group (pumps, copper pipes, other). A table has a
 * header row with «Артикул», the name («Наименование» / «Модель»), a size («Типоразмер») or a description, the opt
 * price «ОЦ, €» and the retail «РЦ, €». Prices are in EUR; the supplier sells in BYN at its own rate («Курс 3,45»
 * in a sheet title), which turns them into BYN. «по запросу», «#N/A» and 0 rows have no price and are left out.
 * The matrix sheet of Kermi radiators, the sheets without a header and the estimate are not read. The file stays on
 * the server only.
 */
class PrestizhTeploPriceList
{
    public const FILE = 'suppliers/prestizh-teplo/latest.xlsm';

    public const DEFAULT_RATE = 3.45;

    /** Sheets that are no price table. */
    private const SKIP = ['смета', 'kermi, brugman', 'engo, salus', 'шкафы монт.', 'sven'];

    /** A sheet => its brand; a mixed sheet finds the brand in the name. */
    private const SHEET_BRANDS = [
        'vaillant' => 'Vaillant', 'protherm' => 'Protherm', 'kospel' => 'Kospel', 'drazice' => 'Drazice', 'reflex' => 'Reflex',
        'flamco' => 'Flamco', 'styleboiler' => 'Styleboiler', 'aquastic' => 'Aquastic', 'comisa' => 'Comisa',
        'conex banninger' => 'Conex Banninger', 'galmet' => 'Galmet', 'afriso' => 'Afriso', 'milwaukee' => 'Milwaukee',
    ];

    /** Brand words of a name on a mixed sheet. */
    private const NAME_BRANDS = [
        'Huch' => '/\bhuch\b|entec/iu', 'Meibes' => '/meibes|^ме\d|^me\d/iu', 'Grundfos' => '/grundfos/iu', 'WILO' => '/\bwilo\b/iu',
        'DAB' => '/\bdab\b/iu', 'Walraven' => '/walraven/iu', 'Afriso' => '/afriso/iu', 'Reflex' => '/reflex/iu', 'Flamco' => '/flamco/iu',
        'Vaillant' => '/vaillant/iu', 'Protherm' => '/protherm/iu', 'Kospel' => '/kospel/iu', 'Comisa' => '/comisa/iu',
    ];

    /** @return list<array{code:string,article:string,brand:string,sheet:string,section:string,name:string,description:string,wholesale:float,rrc:float,wholesale_eur:float,rrc_eur:float,row:int}> */
    public static function parse(string $path): array
    {
        // The xlsm is ~14 MB of sheets and photos: a whole book takes ~250 MB to read.
        @ini_set('memory_limit', '1024M');
        $reader = IOFactory::createReaderForFile($path)->setReadDataOnly(true);
        $sheets = array_values(array_filter($reader->listWorksheetNames($path), fn (string $name) => ! in_array(mb_strtolower(trim($name)), self::SKIP, true)));
        $reader->setLoadSheetsOnly($sheets);
        $book = $reader->load($path);
        $rate = self::rate($book);
        $rows = [];
        $codes = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = trim($sheet->getTitle());
            $sheetBrand = self::SHEET_BRANDS[mb_strtolower($title)] ?? '';
            $section = '';
            $columns = null;
            foreach ($sheet->toArray(null, true, false, true) as $number => $line) {
                $line = array_values($line);
                $text = fn (?int $i): string => $i === null ? '' : trim((string) preg_replace('/\s+/u', ' ', (string) ($line[$i] ?? '')));
                $find = fn (string $pattern): ?int => collect($line)->keys()->first(fn ($i) => preg_match($pattern, $text($i)) === 1);
                $filled = array_values(array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== ''));
                if ($find('/^артикул$/iu') !== null && $find('/^рц\b/iu') !== null) {
                    $columns = [
                        'article' => $find('/^артикул$/iu'),
                        'name' => $find('/^(наименование|модель)$/iu'),
                        'size' => $find('/^типоразмер$/iu'),
                        'description' => $find('/^описание/iu'),
                        'maker' => $find('/^производитель$/iu'),
                        'opt' => $find('/^оц\b/iu'),
                        'rrc' => $find('/^рц\b/iu'),
                    ];

                    continue;
                }
                // The repeated short header («Фото | Наименование | … | ОЦ | РЦ | Заказ») under the first one.
                if ($columns && $find('/^оц$/iu') !== null) {
                    continue;
                }
                if (count($filled) === 1 && is_string($filled[0]) && ! is_numeric(trim($filled[0]))) {
                    $section = trim((string) preg_replace('/\s+/u', ' ', $filled[0]));

                    continue;
                }
                if (! $columns) {
                    continue;
                }
                $rrcEur = self::number($line[$columns['rrc']] ?? null);
                if ($rrcEur <= 0) {
                    continue;
                }
                $optEur = $columns['opt'] !== null ? self::number($line[$columns['opt']] ?? null) : 0.0;
                $article = $text($columns['article']);
                $article = $article === '.' ? '' : $article;
                $name = $text($columns['name']);
                $size = $text($columns['size']);
                if ($name === '' && $article === '') {
                    continue;
                }
                // «без насоса», «с насосом WITA…» are variants of the section's item.
                if ($name !== '' && $section !== '' && preg_match('/^[а-яё]/u', $name)) {
                    $name = $section.' '.$name;
                }
                $fullName = trim($name.($size !== '' && ! str_contains($name, $size) ? ' '.$size : ''));
                $maker = $text($columns['maker']);
                $brand = $maker !== '' ? $maker : ($sheetBrand ?: self::nameBrand($fullName.' '.$article.' '.$section));
                if ($brand === '' && str_starts_with(mb_strtolower($title), 'meibes')) {
                    $brand = 'Meibes';
                }
                $code = $article !== '' ? $article : Str::upper(Str::limit(Str::slug($title.' '.$fullName), 60, ''));
                if (isset($codes[$code])) {
                    $codes[$code]++;
                    $code .= '-'.str_pad((string) $codes[$code], 2, '0', STR_PAD_LEFT);
                } else {
                    $codes[$code] = 1;
                }
                $rows[] = [
                    'code' => $code,
                    'article' => $article,
                    'brand' => $brand,
                    'sheet' => $title,
                    'section' => $section,
                    'name' => $fullName ?: $article,
                    'description' => Str::limit($text($columns['description']), 300, ''),
                    'wholesale' => round($optEur * $rate, 2),
                    'rrc' => round($rrcEur * $rate, 2),
                    'wholesale_eur' => round($optEur, 2),
                    'rrc_eur' => round($rrcEur, 2),
                    'row' => (int) $number,
                ];
            }
        }

        return $rows;
    }

    /** «Расчет только в бел. рублях. Курс 3,45» in the first rows of a sheet. */
    private static function rate($book): float
    {
        foreach ($book->getWorksheetIterator() as $sheet) {
            foreach (array_slice($sheet->toArray(null, true, false, false), 0, 4) as $line) {
                foreach ($line as $value) {
                    if (is_string($value) && preg_match('/курс\s*(\d+[.,]\d+)/iu', $value, $m)) {
                        return (float) str_replace(',', '.', $m[1]);
                    }
                }
            }
        }

        return self::DEFAULT_RATE;
    }

    private static function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $value = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function nameBrand(string $text): string
    {
        foreach (self::NAME_BRANDS as $brand => $pattern) {
            if (preg_match($pattern, $text)) {
                return $brand;
            }
        }

        return '';
    }

    /**
     * The model of a name as a set of words without the brand and the kind of item: «eloBLOCK VE 6/14» and
     * «Электрический котел Vaillant eloBLOCK VE 6/14» meet as «14 6 eloblock ve».
     */
    public static function modelKey(string $name): string
    {
        $name = mb_strtolower(str_replace(['ё', "\u{a0}"], ['е', ' '], $name));
        $name = (string) preg_replace('/(\d),(\d)/u', '$1.$2', $name);
        $name = (string) preg_replace('/([a-zа-я])(\d)/u', '$1 $2', $name);
        $name = (string) preg_replace('/(\d)([a-zа-я])/u', '$1 $2', $name);
        $words = preg_split('/[^\p{L}\p{N}.]+/u', $name) ?: [];
        $key = [];
        foreach ($words as $word) {
            $word = trim($word, '.');
            if ($word === '' || in_array($word, self::DESCRIPTIVE, true)) {
                continue;
            }
            $key[$word] = true;
        }
        $key = array_keys($key);
        sort($key);

        return implode(' ', $key);
    }

    public static function store(string $uploadedPath): void
    {
        Storage::disk('local')->put(self::FILE, (string) file_get_contents($uploadedPath));
    }

    public static function storedPath(): string
    {
        return Storage::disk('local')->exists(self::FILE) ? Storage::disk('local')->path(self::FILE) : '';
    }

    private const DESCRIPTIVE = ['vaillant', 'вайлант', 'protherm', 'протерм', 'kospel', 'drazice', 'дражице', 'reflex', 'flamco',
        'galmet', 'afriso', 'meibes', 'aquastic', 'styleboiler', 'comisa', 'котел', 'котёл', 'газовый', 'настенный', 'напольный',
        'двухконтурный', 'одноконтурный', 'конденсационный', 'электрический', 'электрокотел', 'бойлер', 'косвенного', 'нагрева',
        'водонагреватель', 'бак', 'расширительный', 'мембранный', 'с', 'и', 'для', 'в', 'на', 'квт', 'kw', 'л', 'литров'];
}
