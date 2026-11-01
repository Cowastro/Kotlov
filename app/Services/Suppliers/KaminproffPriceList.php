<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

/**
 * «Прайс Fireway» of ЧП «Камин-профессионал» (kaminproff.by), the official Fireway distributor in Belarus.
 * Five sheets (stoves and fireboxes, sauna stoves, cast iron, sauna glass doors, other); a goods row has the
 * name, sometimes the specs («Мощность…», «Посадочный размер…») and the prices опт and ррц in BYN. A row with a
 * name and no prices is a section title («Паровар 18», «Дверцы топочные»). There are no articles: the code of
 * a row is its sheet and name. One row may name two cards («SOFIA левая/правая»). The photos are pictures in
 * the sheet next to the row. The uploaded file stays on the server only.
 */
class KaminproffPriceList
{
    public const FILE = 'suppliers/kaminproff/latest.xlsx';

    /** @return list<array{code:string,sheet:string,section:string,name:string,specs:string,wholesale:float,rrc:float,row:int}> */
    public static function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $rows = [];
        $seen = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $sheetName = trim($sheet->getTitle());
            $section = '';
            foreach ($sheet->toArray(null, true, false, false) as $index => $cells) {
                $texts = [];
                $numbers = [];
                foreach ($cells as $cell) {
                    if ($cell === null || $cell === '') {
                        continue;
                    }
                    if (is_numeric($cell)) {
                        $numbers[] = (float) $cell;
                    } elseif (preg_match('/^\s*опт:\s*([\d.,]+)/iu', (string) $cell, $m)) {
                        // «опт: 18 рублей, от 12 шт- 17 рублей»: the price of one piece.
                        $numbers[] = (float) str_replace(',', '.', $m[1]);
                    } else {
                        $texts[] = trim((string) preg_replace('/\s+/u', ' ', (string) $cell));
                    }
                }
                $texts = array_values(array_filter($texts, fn (string $t) => $t !== '' && ! in_array(mb_strtolower($t), ['new', 'новинка'], true)
                    && ! str_contains(mb_strtolower($t), 'kaminproff')));
                if ($texts === []) {
                    continue;
                }
                $name = $texts[0];
                // Header rows («Наименование», «опт», «ррц»).
                if (preg_grep('/^(опт|ррц|опт\s*)$/iu', $texts) || mb_strtolower($name) === 'наименование') {
                    continue;
                }
                // The leading running number of the row is not a price.
                if (count($numbers) >= 3 && $numbers[0] < 100 && floor($numbers[0]) === $numbers[0] && is_numeric($cells[0] ?? null)) {
                    array_shift($numbers);
                }
                if (count($numbers) < 2) {
                    if (count($numbers) === 0 && mb_strlen($name) < 60) {
                        $section = $name;
                    }

                    continue;
                }
                [$wholesale, $rrc] = array_slice($numbers, -2);
                if ($rrc <= 0) {
                    continue;
                }
                $specs = collect(array_slice($texts, 1))->first(fn (string $t) => preg_match('/мощност|посадочн|габарит|глубина/iu', $t) === 1) ?? '';
                foreach (self::variants($name) as $variant) {
                    $code = Str::limit(Str::slug($sheetName, '-').'-'.Str::slug($variant), 120, '');
                    if (isset($seen[$code])) {
                        continue; // The same item twice in one sheet.
                    }
                    $seen[$code] = true;
                    $rows[] = [
                        'code' => $code,
                        'sheet' => $sheetName,
                        'section' => $section,
                        'name' => $variant,
                        'specs' => $specs,
                        'wholesale' => round($wholesale, 2),
                        'rrc' => round($rrc, 2),
                        'row' => $index + 1,
                    ];
                }
            }
        }

        return $rows;
    }

    /** «Каминная топка SOFIA левая/правая» names two cards; «SKIF L/R» is one card. */
    public static function variants(string $name): array
    {
        if (preg_match('/^(.*?)\s*левая\s*\/\s*правая\s*(.*)$/iu', $name, $m)) {
            return [trim($m[1].' левая '.$m[2]), trim($m[1].' правая '.$m[2])];
        }

        return [trim((string) preg_replace('/\s+L\s*\/\s*R\b/u', '', $name))];
    }

    /**
     * Pictures of the sheets keyed by sheet title and row (1-based): the photo of a goods row.
     *
     * @return array<string, array<int, Drawing|MemoryDrawing>>
     */
    public static function pictures(string $path): array
    {
        $book = IOFactory::createReaderForFile($path)->load($path);
        $pictures = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getDrawingCollection() as $drawing) {
                if (preg_match('/(\d+)$/', $drawing->getCoordinates(), $m)) {
                    $pictures[trim($sheet->getTitle())][(int) $m[1]] ??= $drawing;
                }
            }
        }

        return $pictures;
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
     * The model of a name as a set of words, so that «Дверца K-411 топочная 250х240мм» and «Дверца К-411
     * топочная 250×240 мм Fireway» meet: Cyrillic letters in codes become Latin, «К-411» is «k411», sizes
     * split into numbers, words that only say what the item is are left out. Empty for a too generic name.
     */
    public static function modelKey(string $name): string
    {
        $name = mb_strtolower(str_replace(['ё', '×', '№'], ['е', ' x ', ' '], $name));
        // A door of 1,9x0,8 (metres) is the door of 1900x800 mm.
        $name = (string) preg_replace_callback('/(?<![\d.,])(\d(?:[.,]\d+)?)\s*[xх*]\s*(\d(?:[.,]\d+)?)(?![\d.,])/u',
            fn (array $m): string => round((float) str_replace(',', '.', $m[1]) * 1000).' x '.round((float) str_replace(',', '.', $m[2]) * 1000), $name);
        // 250х240мм: sizes become separate numbers.
        $name = (string) preg_replace('/(\d)\s*[xх*]\s*(?=\d)/u', '$1 ', $name);
        $name = (string) preg_replace('/(\d)\s*мм\b/u', '$1 ', $name);
        // K-411, PR-04, Z-104: a letter code joined with its number; 75-200: two numbers.
        $name = (string) preg_replace('/(\p{L})\s*-\s*(\d)/u', '$1$2', $name);
        $name = (string) preg_replace('/(\d)\s*-\s*(?=\d)/u', '$1 ', $name);
        $words = preg_split('/[^\p{L}\p{N},.]+/u', $name) ?: [];
        $key = [];
        foreach ($words as $word) {
            $word = trim($word, '.,');
            if ($word === '' || in_array($word, self::DESCRIPTIVE, true)) {
                continue;
            }
            if (preg_match('/\d/', $word)) {
                // A code (К505, Т201) is read in Latin; a word with a number (маяк1) stays as it is.
                if (preg_match('/^[a-zавекмнорстху0-9.,]+$/u', $word)) {
                    $word = strtr($word, self::LOOKALIKE);
                }
                $word = str_replace(',', '.', $word);
            }
            $key[$word] = true;
        }
        $key = array_keys($key);
        sort($key);

        return implode(' ', $key);
    }

    /** Words that say what the item is, not which one. */
    private const DESCRIPTIVE = ['fireway', 'firewa', 'печь', 'печи', 'банная', 'отопительная', 'отопительно', 'варочная', 'отопительно-варочная',
        'каминная', 'топка', 'камин', 'печь-камин', 'для', 'бани', 'мм', 'с', 'и', 'на', 'шт', 'в', 'упак'];

    /** Cyrillic letters that look like Latin ones, as in «К505» or «Т201». */
    private const LOOKALIKE = ['а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'х' => 'x', 'у' => 'y'];
}
