<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The price list of VISTAR («VISTAR Price <date>.xlsx»): BAXI boilers (traditional, condensing, electric Ampera)
 * with flues and accessories, Viena indirect water heaters, Reflex/Flamco expansion tanks and a Viessmann sale.
 * Every sheet has tables under a series title: a header row with «Артикул», the name («Наименование», «Описание»,
 * or «Мощность» on the Ampera table), «Тип», the opt price («Цена ОПТ» / «Цена со скидкой»), «Розничная
 * стоимость» and a column «Изображение» that some tables fill with the stock («В наличии», «Скоро на складе»).
 * Prices are in BYN. The file stays on the server only.
 */
class VistarPriceList
{
    public const FILE = 'suppliers/vistar/latest.xlsx';

    /** Brand words of a name; the sheet gives the brand otherwise. */
    private const BRANDS = [
        'VIESSMANN' => '/viessmann|vito(dens|tron|com|cal)|divicon/iu',
        'KRATS' => '/\bkrats\b/iu',
        'Flamco' => '/flamco/iu',
        'Reflex' => '/reflex/iu',
        'Afriso' => '/\bafr_|afriso/iu',
        'Barberi' => '/barberi/iu',
        'WILO' => '/\bwilo\b/iu',
        'Grundfos' => '/grundfos/iu',
        'ZONT' => '/\bzont\b/iu',
        'Viena' => '/\bviena\b/iu',
        'BAXI' => '/\bbaxi\b|\bluna\b|\bnuvola\b|\beco[ -]?(4s|four|nova|life|home)\b|\bampera\b|\bslim\b|\bmain\b|\bprime\b|\bduo-tec\b/iu',
    ];

    /** @return list<array{code:string,article:string,brand:string,sheet:string,section:string,name:string,type:string,wholesale:float,rrc:float,stock:string,sale:bool,row:int}> */
    public static function parse(string $path): array
    {
        $book = IOFactory::createReaderForFile($path)->setReadDataOnly(true)->load($path);
        $rows = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = trim($sheet->getTitle());
            $section = '';
            $sectionBrand = '';
            $columns = null;
            foreach ($sheet->toArray(null, true, false, true) as $number => $line) {
                $line = array_values($line);
                $text = fn (?int $i): string => $i === null ? '' : trim((string) preg_replace('/\s+/u', ' ', (string) ($line[$i] ?? '')));
                $filled = array_values(array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== ''));
                $find = fn (string $pattern): ?int => collect($line)->keys()->first(fn ($i) => preg_match($pattern, $text($i)) === 1);
                if (($article = $find('/^артикул$/iu')) !== null) {
                    $columns = [
                        'article' => $article,
                        'name' => $find('/^(наименование|описание|мощность)/iu'),
                        'type' => $find('/^тип$/iu'),
                        'opt' => $find('/^цена (опт|со скидкой)/iu'),
                        'rrc' => $find('/^розничная/iu'),
                        'stock' => $find('/^(изображение|состояние)$/iu'),
                        'power' => (bool) preg_match('/^мощность/iu', $text($find('/^(наименование|описание|мощность)/iu'))),
                    ];

                    continue;
                }
                // A title over a table: one text cell (the series, «BAXI Ampera», a group of accessories).
                if (count($filled) === 1 && is_string($filled[0]) && ! is_numeric($filled[0]) && mb_strlen(trim($filled[0])) > 2) {
                    $candidate = trim((string) preg_replace('/\s+/u', ' ', $filled[0]));
                    if (! preg_match('/^(внимание|цены в прайс)/iu', $candidate)) {
                        $section = $candidate;
                        // «Reflex» over «Для закрытых систем…»: the brand title holds for the tables under it.
                        $sectionBrand = self::brandOf($candidate, '', '') ?: $sectionBrand;
                    }

                    continue;
                }
                if ($columns === null || $columns['opt'] === null || $columns['name'] === null) {
                    continue;
                }
                $code = $text($columns['article']);
                $opt = $line[$columns['opt']] ?? null;
                if ($code === '' || ! is_numeric($opt) || (float) $opt <= 0 || preg_match('/стоимость предложения/iu', $code)) {
                    continue;
                }
                $name = $text($columns['name']);
                // The flue table of one sheet has «Мощность, кВт» over its names: only a number is a power.
                if ($columns['power'] && preg_match('/^\d/u', $name)) {
                    // The Ampera table names the power only: «6 (2-4-6)» → «BAXI Ampera 6 кВт (2-4-6)».
                    $name = trim(preg_replace('/^(\d+)/u', '$1 кВт', $name) ?? $name);
                    $name = trim(($section !== '' ? $section : 'BAXI Ampera').' '.$name);
                }
                $rrc = $columns['rrc'] !== null && is_numeric($line[$columns['rrc']] ?? null) ? (float) $line[$columns['rrc']] : 0.0;
                // The stock stands in the «Изображение» column or next to it.
                $stock = collect(array_keys($line))->filter(fn ($i) => $i > max($columns['opt'], (int) $columns['rrc']))
                    ->map(fn ($i) => $text($i))->first(fn ($value) => preg_match('/наличи|скоро|ожида|под заказ/iu', $value) === 1) ?? '';
                $rows[] = [
                    'code' => $code,
                    'article' => $code,
                    'brand' => self::brandOf($name.' '.$code, $section, $title) ?: $sectionBrand,
                    'sheet' => $title,
                    'section' => $section,
                    'name' => $name,
                    'type' => $text($columns['type']),
                    'wholesale' => round((float) $opt, 2),
                    'rrc' => round($rrc, 2),
                    'stock' => $stock,
                    'sale' => (bool) preg_match('/распродаж|специальн/iu', $title.' '.$section),
                    'row' => (int) $number,
                ];
            }
        }

        // An article given to two different items (a sale bundle lists the same code twice): -01/-02 each.
        $byCode = [];
        foreach ($rows as $index => $row) {
            $byCode[$row['code']][] = $index;
        }
        foreach ($byCode as $code => $indexes) {
            if (count(array_unique(array_map(fn ($i) => $rows[$i]['name'], $indexes))) > 1) {
                foreach ($indexes as $n => $index) {
                    $rows[$index]['code'] = sprintf('%s-%02d', $code, $n + 1);
                }
            } elseif (count($indexes) > 1) {
                foreach (array_slice($indexes, 1) as $index) {
                    unset($rows[$index]);
                }
            }
        }

        return array_values($rows);
    }

    public static function brandOf(string $text, string $section, string $sheet): string
    {
        foreach (self::BRANDS as $brand => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $brand;
            }
        }
        foreach (self::BRANDS as $brand => $pattern) {
            if (preg_match($pattern, $section) === 1) {
                return $brand;
            }
        }

        return match (true) {
            (bool) preg_match('/baxi/iu', $sheet) => 'BAXI',
            (bool) preg_match('/электрокотл/iu', $sheet) => 'BAXI',
            (bool) preg_match('/viena/iu', $sheet) => 'Viena',
            default => '',
        };
    }

    /** «В наличии» → in stock, «Скоро на складе» and the rest → on order. */
    public static function stockStatus(string $stock): string
    {
        return preg_match('/в наличии/iu', $stock) ? 'in_stock' : 'preorder';
    }

    /**
     * The model of a name as a set of words: «ECO-4s 24 F (дымоход в подарок)» and «Газовый котел BAXI ECO-4s 24F»
     * meet as «24 4 eco f s»; the words of the kind of item, the brand and remarks in brackets are left out.
     */
    public static function modelKey(string $name): string
    {
        $name = mb_strtolower(str_replace(['ё', "\u{a0}"], ['е', ' '], $name));
        $name = (string) preg_replace('/\((?:[^()]*(?:подарок|комплект|состоит|termex)[^()]*)\)/u', ' ', $name);
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

    public static function slug(string $code): string
    {
        return Str::slug($code) ?: md5($code);
    }

    private const DESCRIPTIVE = ['baxi', 'бакси', 'viessmann', 'виссманн', 'viena', 'reflex', 'flamco', 'котел', 'котёл', 'газовый', 'газовая',
        'настенный', 'напольный', 'двухконтурный', 'одноконтурный', 'конденсационный', 'электрический', 'электрокотел', 'бойлер',
        'косвенного', 'нагрева', 'водонагреватель', 'бак', 'расширительный', 'с', 'и', 'для', 'в', 'на', 'квт', 'kw', 'л', 'литров'];
}
