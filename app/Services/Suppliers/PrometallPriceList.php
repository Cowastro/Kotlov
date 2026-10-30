<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * «ПРАЙС-ЛИСТ PROMETALL Опт» of ЧТУП «Бани и печи» (banipechi.by), the ProMetall dealer in Belarus.
 * The first sheet groups the goods under section rows («Печи "Атмосфера"», «Печное литье»…); a goods row
 * carries the ProMetall article (p0320), EAN-13, the name, and prices: РРЦ and опт in BYN. Stock is on request.
 * The sheet «КАМНИ СКИДКА» has no articles and is left out. The uploaded file stays on the server only.
 */
class PrometallPriceList
{
    public const FILE = 'suppliers/prometall/latest.xlsx';

    /** Columns of the goods rows (0-based). */
    private const ARTICLE = 1;

    private const EAN = 2;

    private const DESCRIPTION = 4;

    private const GROUP_NAME = 5;

    private const FULL_NAME = 6;

    private const RRC_BYN = 14;

    private const WHOLESALE_BYN = 16;

    /** Опт before rounding: some rows fill only this one. */
    private const WHOLESALE_BYN_EXACT = 15;

    /** @return list<array{code:string,ean:string,section:string,name:string,description:string,rrc:float,wholesale:float}> */
    public static function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $rows = [];
        $section = '';
        $seen = [];
        foreach ($sheet->toArray(null, true, false, false) as $index => $cells) {
            $cells = array_pad($cells, 20, null);
            $text = fn (int $column): string => trim((string) preg_replace('/\s+/u', ' ', (string) ($cells[$column] ?? '')));
            $code = $text(self::ARTICLE);
            if (! preg_match('/^p\d/i', $code)) {
                // A section row: its title stands alone in the first columns.
                $title = trim(implode(' ', array_filter(array_map(fn ($c) => trim((string) $c), array_slice($cells, 0, 8)), fn ($c) => $c !== '' && $c !== '0')));
                if ($index > 9 && $title !== '' && mb_strlen($title) < 80 && ! preg_match('/\d{6,}/', $title)) {
                    $section = $title;
                }

                continue;
            }
            $name = $text(self::FULL_NAME) ?: $text(self::GROUP_NAME);
            $name = trim((string) preg_replace('/\s*под\s+заказ\s*$/iu', '', $name));
            $rrc = self::number($cells[self::RRC_BYN]);
            if ($name === '' || $rrc <= 0) {
                continue;
            }
            // An article repeats for variants of one item: the second gets a suffix, still the same key for linking.
            $key = mb_strtolower($code);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $rows[] = [
                'code' => $seen[$key] > 1 ? $code.'-'.$seen[$key] : $code,
                'article' => $code,
                'ean' => preg_replace('/\D+/', '', (string) $cells[self::EAN]) ?? '',
                'section' => $section,
                'name' => $name,
                'description' => $text(self::DESCRIPTION),
                'rrc' => round($rrc, 2),
                'wholesale' => round(self::number($cells[self::WHOLESALE_BYN]) ?: self::number($cells[self::WHOLESALE_BYN_EXACT]), 2),
            ];
        }

        return $rows;
    }

    public static function store(string $uploadedPath): void
    {
        Storage::disk('local')->put(self::FILE, (string) file_get_contents($uploadedPath));
    }

    public static function storedPath(): string
    {
        return Storage::disk('local')->exists(self::FILE) ? Storage::disk('local')->path(self::FILE) : '';
    }

    private static function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = str_replace([' ', "\u{00a0}", ','], ['', '', '.'], (string) $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
