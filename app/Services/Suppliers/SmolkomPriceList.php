<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * «Прайс-лист Смолком Камин»: portals Smolcom and electric fireplaces Dimplex, Royal Flame and Real Flame in BYN.
 * One sheet; the header row has «Бренд, Номенклатура, Характеристика (colour), Остаток, Резерв, Св. Остаток,
 * Опт 3, РРЦ», the sizes (изделие, упаковка, встраиваемые — three cells each), «Ссылка», «Номенклатура.Артикул»
 * and «Штрихкод». A row without a brand is a group («ПОРТАЛЫ», «ЭЛЕКТРОКАМИНЫ») or a section («0 ЛИНЕЙНЫЕ 40
 * дюймов»). A portal model comes in several colours, each its own row and article. The file is a Google Drive
 * file that the supplier updates; it is also uploaded by hand on the supplier page.
 */
class SmolkomPriceList
{
    public const FILE = 'suppliers/smolkom/latest.xlsx';

    /** The Google Drive file the supplier shares (09.11.2026). */
    public const DRIVE_FILE_ID = '1hu5CpzQZVNaC5H6gRcNAunsUj8SwEaeb';

    public const BRANDS = ['smolcom' => 'Smolcom', 'dimplex' => 'Dimplex', 'royalflame' => 'Royal Flame', 'realflame' => 'Real Flame'];

    /**
     * @return list<array{code:string,article:string,barcode:string,brand:string,group:string,section:string,name:string,colour:string,
     *     stock:int,reserve:int,free:int,wholesale:float,rrc:float,sizes:array<string,string>,link:string,valid_until:string,row:int}>
     */
    public static function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);
        $rows = [];
        $columns = null;
        $group = $section = '';
        $seen = [];
        foreach ($sheet->toArray(null, true, false, false) as $index => $cells) {
            $cells = array_map(fn ($c) => is_string($c) ? trim((string) preg_replace('/\s+/u', ' ', $c)) : $c, $cells);
            if ($columns === null) {
                $columns = self::header($cells);

                continue;
            }
            $get = fn (string $key) => isset($columns[$key]) ? ($cells[$columns[$key]] ?? null) : null;
            $brand = trim((string) $get('brand'));
            $name = trim((string) $get('name'));
            if ($name === '' || mb_strtolower($brand) === 'бренд') {
                continue;
            }
            if ($brand === '') {
                // «ПОРТАЛЫ» / «ЭЛЕКТРОКАМИНЫ» is the group, «2 классические STD» a section of it.
                if (preg_match('/^\d+\s/u', $name)) {
                    $section = trim((string) preg_replace('/^\d+\s+/u', '', $name));
                } else {
                    [$group, $section] = [$name, ''];
                }

                continue;
            }
            $brand = self::BRANDS[Str::slug($brand, '')] ?? $brand;
            $colour = trim((string) $get('colour'));
            $article = self::text($get('article'));
            $code = $article !== '' ? 'a-'.self::codePart($article) : Str::slug($brand.' '.$name.' '.$colour);
            $code = Str::limit($code, 120, '');
            for ($base = $code, $i = 2; isset($seen[$code]); $i++) {
                $code = $base.'-'.$i;
            }
            $seen[$code] = true;
            $rows[] = [
                'code' => $code,
                'article' => $article,
                'barcode' => self::text($get('barcode')),
                'brand' => $brand,
                'group' => $group,
                'section' => $section,
                'name' => $name,
                'colour' => $colour,
                'stock' => (int) self::number($get('stock')),
                'reserve' => (int) self::number($get('reserve')),
                'free' => (int) self::number($get('free')),
                'wholesale' => round(self::number($get('wholesale')), 2),
                'rrc' => round(self::number($get('rrc')), 2),
                'sizes' => array_filter([
                    'Габариты изделия, мм' => self::size($cells, $columns['size'] ?? null),
                    'Габариты упаковки, мм' => self::size($cells, $columns['pack'] ?? null),
                    'Размеры для встраивания, мм' => self::size($cells, $columns['builtin'] ?? null),
                ]),
                'link' => self::text($get('link')),
                'valid_until' => self::text($get('valid_until')),
                'row' => $index + 1,
            ];
        }

        return $rows;
    }

    /** Is the row a portal (Smolcom) rather than an electric fireplace? */
    public static function isPortal(array $row): bool
    {
        return str_contains(mb_strtolower((string) $row['group']), 'портал');
    }

    /** Downloads the shared Google Drive file; false when Drive gave no xlsx. */
    public static function fetchFromDrive(string $fileId = self::DRIVE_FILE_ID): bool
    {
        $response = Http::timeout(60)->retry(2, 2000, throw: false)->withoutVerifying()
            ->get('https://drive.google.com/uc', ['export' => 'download', 'id' => $fileId]);
        $body = $response->successful() ? $response->body() : '';
        // An xlsx is a zip: «PK».
        if (! str_starts_with($body, 'PK')) {
            return false;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'smolkom');
        file_put_contents($tmp, $body);
        try {
            if (self::parse($tmp) === []) {
                return false;
            }
            self::store($tmp);

            return true;
        } finally {
            @unlink($tmp);
        }
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
     * The model of a name as a sorted set of words without the brand, the item type and the colour words:
     * «Портал Smolcom Albion V42 бьянко белый» and the row «ALBION V42» meet as «albion v42»; «Электрокамин Royal
     * Flame Vision 60 LOG LED» and «Vision 60 LOG LED» as «60 led log vision». «5DVART» is «5d vart», «Vis42» is «v42».
     */
    public static function modelKey(string $name): string
    {
        return implode(' ', self::modelWords($name));
    }

    /** @return list<string> */
    public static function modelWords(string $name): array
    {
        $words = array_diff(self::words($name), self::DESCRIPTIVE, array_keys(self::COLOUR_WORDS));
        $words = array_values(array_unique($words));
        sort($words);

        return $words;
    }

    /** Colour words of a name or a «Характеристика»: «Бьянко белый/черный» → [белый, бьянко, черный]. @return list<string> */
    public static function colourWords(string $text): array
    {
        $words = array_values(array_unique(array_map(fn (string $w) => self::COLOUR_WORDS[$w], array_intersect(self::words($text), array_keys(self::COLOUR_WORDS)))));
        sort($words);

        return $words;
    }

    /** @return list<string> */
    private static function words(string $name): array
    {
        $name = mb_strtolower(str_replace(['ё', '-', '/', '(', ')', ','], ['е', ' ', ' ', ' ', ' ', ' '], $name));
        // 5DVART → 5d vart; Vis42 → v42; DF2608 stays one word.
        $name = (string) preg_replace('/\b5d(?=[a-z])/u', '5d ', $name);
        $name = (string) preg_replace('/\bvis(\d)/u', 'v$1', $name);
        $name = str_replace(['royal flame', 'real flame'], ['royalflame', 'realflame'], $name);
        $words = preg_split('/[^\p{L}\p{N}.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(fn (string $w) => trim($w, '.'), $words), fn (string $w) => $w !== ''));
    }

    /** Words that say what the item is or who made it, not which one. */
    private const DESCRIPTIVE = ['smolcom', 'смолком', 'dimplex', 'димплекс', 'royalflame', 'realflame', 'royal', 'real', 'flame', 'rf',
        'портал', 'портала', 'порталы', 'для', 'электрокамина', 'электрокаминов', 'электрокамин', 'электрокамины', 'электрический', 'электроочаг',
        'очаг', 'камин', 'каминокомплект', 'каминный', 'комплект', 'деревянный', 'мдф', 'с', 'и', 'под', 'в', 'цвет', 'цвета', 'int', 'eu'];

    /** Colour words of the price list, spelt as a card may spell them. */
    private const COLOUR_WORDS = [
        'бьянко' => 'бьянко', 'белый' => 'белый', 'белая' => 'белый', 'белое' => 'белый', 'белого' => 'белый',
        'черный' => 'черный', 'черная' => 'черный', 'серый' => 'серый', 'серая' => 'серый', 'графит' => 'графит',
        'кашемир' => 'кашемир', 'дуб' => 'дуб', 'антик' => 'антик', 'махагон' => 'махагон', 'коричневый' => 'коричневый',
        'коричневая' => 'коричневый', 'орех' => 'орех', 'темный' => 'темный', 'ваниль' => 'ваниль', 'патина' => 'патина',
        'золото' => 'золото', 'слоновая' => 'слоновая', 'кость' => 'кость', 'беленый' => 'беленый', 'венге' => 'венге',
    ];

    /** @return array<string,int> */
    private static function header(array $cells): ?array
    {
        $map = [];
        foreach ($cells as $i => $cell) {
            $title = mb_strtolower(trim((string) $cell));
            $key = match ($title) {
                'бренд' => 'brand', 'номенклатура' => 'name', 'характеристика' => 'colour', 'остаток' => 'stock', 'резерв' => 'reserve',
                'св. остаток' => 'free', 'опт 3' => 'wholesale', 'ррц' => 'rrc', 'габариты изделия' => 'size', 'габариты упаковки' => 'pack',
                'габариты встраиваемые' => 'builtin', 'ссылка' => 'link', 'цена действует до:' => 'valid_until',
                'номенклатура.артикул' => 'article', 'штрихкод' => 'barcode', default => null,
            };
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $i;
            }
        }

        return isset($map['brand'], $map['name'], $map['rrc']) ? $map : null;
    }

    private static function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** «64940373.0» → «64940373», a text stays a text. */
    private static function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }

    /** Three cells «1 500 | 788 | 360» → «1500 × 788 × 360». */
    private static function size(array $cells, ?int $start): string
    {
        if ($start === null) {
            return '';
        }
        $parts = array_filter(array_map(fn ($c) => self::number($c), array_slice($cells, $start, 3)), fn (float $n) => $n > 0);

        return count($parts) === 3 ? implode(' × ', array_map(fn (float $n) => rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.'), $parts)) : '';
    }

    /** An article with Cyrillic letters («ALBV4Бб») as a code: transliterated, case kept apart («Бб» ≠ «Бв»). */
    private static function codePart(string $article): string
    {
        return Str::limit(Str::slug(Str::ascii($article), '-') ?: md5($article), 100, '');
    }
}
