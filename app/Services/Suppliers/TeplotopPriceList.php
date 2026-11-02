<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The price lists of ООО «ТеплоТоп-Бел» (ttop.by), the Belarus importer of LEMAX (Лемакс) boilers, gas water heaters
 * and steel panel radiators, with KRATS chimneys, EKF and radiator fittings. Two files of the same sheets: «ОПТ» has
 * the wholesale price (опт, МОЦ, РОЦ) and mostly the РРЦ too, «РРЦ» the retail price only. Prices are BYN with VAT;
 * a column «РРЦ, евро» is in euro. Three layouts:
 *  - a table: a header row with the price columns, then a row per item (name or article in its own column);
 *  - boiler cards: a header row with price column pairs per model, the prices under it, the model names below;
 *  - a radiator matrix (LEMAX): lengths down, types (11, 21, 22, 33) and heights across, the price under each length.
 * The S-Tank sheet is left out: S-Tank has its own supplier and the sheet is an old euro list.
 */
class TeplotopPriceList
{
    public const OPT_FILE = 'suppliers/teplotop/opt.xlsx';

    public const RRC_FILE = 'suppliers/teplotop/rrc.xlsx';

    private const OPT_LABEL = '/^\s*(опт|моц|роц|оц|цена роц)\b/iu';

    /** Rows under a model that describe it, not another item. */
    private const SPEC_LABEL = '/^(модель|характеристики|тип камеры|номинальн|расход|давлени|минимальн|габарит|розжиг|автоматическ|модуляция|масса|срок|кпд|объем|материал)/iu';

    private const RRC_LABEL = '/ррц|розниц/iu';

    /**
     * Both files merged: the opt file gives the wholesale price and mostly the РРЦ; the RRC file only fills the РРЦ
     * the opt file has not (the radiator matrices). The RRC file of 02.11.2026 had two KRATS prices swapped.
     *
     * @return list<array{code:string,sheet:string,brand:string,name:string,article:string,wholesale:float,rrc:float,currency:string,specs:array<string,string>}>
     */
    public static function parse(string $optPath, string $rrcPath = ''): array
    {
        $rows = [];
        foreach (self::read($optPath, 'opt') as $code => $row) {
            $rows[$code] = $row;
        }
        if ($rrcPath !== '' && is_file($rrcPath)) {
            foreach (self::read($rrcPath, 'rrc') as $code => $row) {
                if (isset($rows[$code])) {
                    $rows[$code]['rrc'] = $rows[$code]['rrc'] ?: $row['rrc'];
                } elseif ($row['rrc'] > 0) {
                    $rows[$code] = $row;
                }
            }
        }

        return array_values(array_filter($rows, fn (array $row) => $row['rrc'] > 0 || $row['wholesale'] > 0));
    }

    /** @return array<string, array<string, mixed>> code => row of one file */
    private static function read(string $path, string $kind): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $rows = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = trim($sheet->getTitle());
            if (preg_match('/s-?tank|^главная|^лист\d/iu', $title)) {
                continue;
            }
            $cells = $sheet->toArray(null, true, false, false);
            $items = preg_match('/lemax/iu', $title) && preg_match('/боков|нижн/iu', $title)
                ? self::radiators($cells, $title, $kind)
                : array_merge(self::tables($cells, $title), self::cards($cells, $title));
            foreach ($items as $item) {
                $rows[$item['code']] ??= $item;
            }
        }

        return $rows;
    }

    /** Table sheets: a header row with the price columns, then an item per row. */
    private static function tables(array $cells, string $sheet): array
    {
        $items = [];
        $header = null;
        foreach ($cells as $r => $row) {
            $labels = self::priceColumns($row);
            if ($labels['rrc'] !== [] || $labels['opt'] !== []) {
                // A header of boiler cards is read by cards().
                $header = self::isCardHeader($cells, $r, $labels) ? null : ['labels' => $labels, 'row' => $row];
                continue;
            }
            if ($header === null) {
                continue;
            }
            $h = $header['row'];
            $nameCol = self::column($h, '/назван|модел|номенклат|позиц/iu') ?? 0;
            $articleCol = self::column($h, '/^\s*артикул\s*$/iu');
            $name = self::text($row[$nameCol] ?? '');
            $rrcCol = $header['labels']['rrc'][0] ?? null;
            $optCol = $header['labels']['opt'][0] ?? null;
            $rrc = $rrcCol !== null ? self::price($row[$rrcCol] ?? null) : 0.0;
            $opt = $optCol !== null ? self::price($row[$optCol] ?? null) : 0.0;
            // The specs of a single-model table («20 М», «TURBO-24») end it.
            if (preg_match(self::SPEC_LABEL, $name)) {
                $header = null;
                continue;
            }
            // «РАСПРОДАЖА!!!» under a model: its stock is sold off at these prices.
            if (preg_match('/распродажа/iu', $name)) {
                if ($items !== [] && $rrc > 0) {
                    $items[count($items) - 1] = array_merge($items[count($items) - 1], ['wholesale' => round($opt, 2), 'rrc' => round($rrc, 2), 'sale' => true]);
                }
                continue;
            }
            // A single-model table names the model in its header and leaves the first column of the price row empty.
            if ($name === '' && $nameCol === 0 && ($rrc > 0 || $opt > 0)) {
                $name = self::text($h[0] ?? '');
            }
            if (! self::isName($name) || mb_strlen($name) < 3) {
                continue;
            }
            if ($rrc <= 0 && $opt <= 0) {
                continue;
            }
            $article = $articleCol !== null ? self::text($row[$articleCol] ?? '') : '';
            // «AVE3690115 | — | … | Предохранительный клапан 1/2" 1,5 Бар»: the article stands for the name.
            $descriptionCol = self::column($h, '/полное описание/iu');
            if (preg_match('/^[A-Z0-9][A-Z0-9.\/-]{4,}$/u', $name) && $descriptionCol !== null && ($description = self::text($row[$descriptionCol] ?? '')) !== '' && mb_strlen($description) < 120) {
                [$article, $name] = [$name, $description.' '.$name];
            }
            // «Производитель» column of the fittings sheet; a KRATS row names its brand; the rest is LEMAX.
            $brandCol = self::column($h, '/^\s*производитель/iu');
            $brand = $brandCol !== null ? trim((string) preg_replace('/\(.*\)/u', '', self::text($row[$brandCol] ?? ''))) : self::brandOf($sheet, $name);
            $specs = [];
            foreach ($h as $c => $label) {
                $label = self::text($label);
                if ($label !== '' && ! in_array($c, [$nameCol, $articleCol, $rrcCol, $optCol, $brandCol], true) && ! preg_match('/фото|ссылка|количество|описание/iu', $label)
                    && ($value = self::text($row[$c] ?? '')) !== '' && $value !== '-') {
                    $specs[$label] = $value;
                }
            }
            $items[] = self::item($sheet, $brand, $name, $article, $opt, $rrc, preg_match('/евро/iu', self::text($h[$rrcCol] ?? '')) ? 'EUR' : 'BYN', $specs);
        }

        return $items;
    }

    /** Boiler card sheets: price column pairs per model in a header row, the prices below, the model names under them. */
    private static function cards(array $cells, string $sheet): array
    {
        $items = [];
        foreach ($cells as $r => $row) {
            $labels = self::priceColumns($row);
            $byLabel = self::isCardHeader($cells, $r, $labels);
            // «Стоимость в BYN с НДС (розница)» in the first column, the prices across, the names a row or two above.
            $priceRow = $byLabel ? ($cells[$r + 1] ?? []) : (preg_match('/^стоимость|^цена/iu', self::text($row[0] ?? '')) ? $row : null);
            if ($priceRow === null) {
                continue;
            }
            if ($byLabel) {
                $pairs = [];
                foreach ($labels['rrc'] as $rrcCol) {
                    $optCol = collect($labels['opt'])->filter(fn (int $c) => $c < $rrcCol)->max();
                    $pairs[] = [$optCol, $rrcCol, $optCol ?? $rrcCol - 1];
                }
                $names = $cells[$r + 2] ?? [];
            } else {
                $pairs = [];
                foreach ($priceRow as $c => $value) {
                    if ($c > 0 && self::price($value) > 0) {
                        $pairs[] = [null, $c, $c];
                    }
                }
                $names = [];
                for ($up = $r - 1; $up >= max(0, $r - 3); $up--) {
                    if (collect($pairs)->contains(fn (array $p) => self::text($cells[$up][$p[2]] ?? '') !== '' && ! is_numeric($cells[$up][$p[2]]))) {
                        $names = $cells[$up];
                        break;
                    }
                }
            }
            foreach ($pairs as [$optCol, $rrcCol, $nameCol]) {
                $name = self::text($names[$nameCol] ?? '');
                $rrc = self::price($priceRow[$rrcCol] ?? null);
                $opt = $optCol !== null ? self::price($priceRow[$optCol] ?? null) : 0.0;
                if (! self::isName($name) || ($rrc <= 0 && $opt <= 0)) {
                    continue;
                }
                // The rows under the card: «label | value» in the model's column.
                $specs = [];
                for ($s = $r + ($byLabel ? 3 : 1); $s < min(count($cells), $r + 30); $s++) {
                    $label = self::text($cells[$s][0] ?? '');
                    $value = self::text($cells[$s][$nameCol] ?? '');
                    if ($label === '' || preg_match('/модель|стоимость|^стр/iu', $label)) {
                        if ($label !== '' && preg_match('/модель|стоимость/iu', $label)) {
                            break;
                        }
                        continue;
                    }
                    if ($value !== '' && $value !== '-') {
                        $specs[$label] = $value;
                    }
                }
                $items[] = self::item($sheet, self::brandOf($sheet, $name), $name, '', $opt, $rrc, 'BYN', $specs);
            }
        }

        return $items;
    }

    /** LEMAX radiators: per length a row of heat outputs and a row of prices under the heights of each type. */
    private static function radiators(array $cells, string $sheet, string $kind): array
    {
        $connection = preg_match('/нижн/iu', $sheet) ? 'нижнее' : 'боковое';
        $types = [];
        $heights = [];
        $items = [];
        foreach ($cells as $r => $row) {
            if ($types === [] && collect($row)->filter(fn ($v) => in_array((string) $v, ['11', '21', '22', '33'], true))->count() >= 3) {
                foreach ($row as $c => $v) {
                    if (in_array((string) $v, ['11', '21', '22', '33'], true)) {
                        $types[$c] = (int) $v;
                    }
                }
                continue;
            }
            if ($types !== [] && $heights === [] && collect($row)->filter(fn ($v) => preg_match('/^\D*([2-9]00)\s*$/u', (string) $v))->count() >= 6) {
                foreach ($row as $c => $v) {
                    if (preg_match('/^\D*([2-9]00)\s*$/u', (string) $v, $m)) {
                        $heights[$c] = (int) $m[1];
                    }
                }
                continue;
            }
            $length = $row[1] ?? null;
            if ($heights === [] || ! is_numeric($length) || (int) $length < 300 || (int) $length > 3000) {
                continue;
            }
            $prices = $cells[$r + 1] ?? [];
            foreach ($heights as $c => $height) {
                $typeCols = array_filter(array_keys($types), fn (int $t) => $t <= $c);
                $price = self::price($prices[$c] ?? null);
                if ($typeCols === [] || $price <= 0) {
                    continue;
                }
                $type = $types[max($typeCols)];
                $name = sprintf('Радиатор стальной %s подключение LEMAX %s %dх%dх%d', $connection, $connection === 'нижнее' ? 'Valve Compact' : 'Compact', $type, $height, (int) $length);
                // The matrix of the opt file holds опт, of the RRC file the РРЦ.
                $item = self::item($sheet, 'LEMAX', $name, '', $kind === 'opt' ? $price : 0, $kind === 'rrc' ? $price : 0, 'BYN', [
                    'Тип радиатора' => (string) $type, 'Высота, мм' => (string) $height, 'Длина, мм' => (string) (int) $length,
                    'Подключение' => $connection, 'Тепловая мощность, Вт' => self::text($row[$c] ?? ''),
                ]);
                $item['code'] = sprintf('lemax-rad-%s-%d-%d-%d', $connection === 'нижнее' ? 'v' : 'c', $type, $height, (int) $length);
                $items[] = $item;
            }
        }

        return $items;
    }

    /** A model or goods name, not a number, a column title or a sale banner. */
    private static function isName(string $name): bool
    {
        return $name !== '' && ! is_numeric(str_replace([' ', ','], ['', '.'], $name))
            && preg_match('/^модель|стоимость|распродажа|прайс-лист|^стр\.?\s*\d/iu', $name) !== 1;
    }

    private static function item(string $sheet, string $brand, string $name, string $article, float $opt, float $rrc, string $currency, array $specs): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return [
            // A gas water heater «Classic-20» is not the boiler «Classic 20»: its own code.
            'code' => $article !== '' ? 'art-'.Str::slug($article) : Str::limit(Str::slug($brand.(preg_match('/водоногрев|водонагрев/iu', $sheet) ? ' kolonka' : '').' '.$name), 110, ''),
            'sheet' => $sheet,
            'brand' => $brand,
            'name' => $name,
            'article' => $article,
            'wholesale' => round($opt, 2),
            'rrc' => round($rrc, 2),
            'currency' => $currency,
            'specs' => $specs,
        ];
    }

    /**
     * Price columns over model cards: several price pairs, or one pair whose prices stand in the next row with no
     * name of their own and the model name under them («PRIME PRO 24»).
     */
    private static function isCardHeader(array $cells, int $r, array $labels): bool
    {
        if ($labels['rrc'] === []) {
            return false;
        }
        if (count($labels['rrc']) + count($labels['opt']) > 2) {
            return true;
        }
        $next = $cells[$r + 1] ?? [];
        $names = $cells[$r + 2] ?? [];
        $rrcCol = $labels['rrc'][0];
        $nameCol = $labels['opt'][0] ?? $rrcCol - 1;

        return self::price($next[$rrcCol] ?? null) > 0 && self::text($next[0] ?? '') === '' && self::text($names[$nameCol] ?? '') !== '' && ! is_numeric($names[$nameCol]);
    }

    /** @return array{opt:list<int>,rrc:list<int>} */
    private static function priceColumns(array $row): array
    {
        $opt = $rrc = [];
        foreach ($row as $c => $value) {
            $text = self::text($value);
            if ($text === '' || mb_strlen($text) > 40) {
                continue;
            }
            if (preg_match(self::OPT_LABEL, $text)) {
                $opt[] = $c;
            } elseif (preg_match(self::RRC_LABEL, $text)) {
                $rrc[] = $c;
            }
        }

        return ['opt' => $opt, 'rrc' => $rrc];
    }

    private static function column(array $row, string $pattern): ?int
    {
        foreach ($row as $c => $value) {
            if (preg_match($pattern, self::text($value))) {
                return $c;
            }
        }

        return null;
    }

    private static function brandOf(string $sheet, string $name): string
    {
        return match (true) {
            preg_match('/krats/iu', $sheet.' '.$name) === 1 => 'KRATS',
            preg_match('/\bekf\b/iu', $sheet.' '.$name) === 1 => 'EKF',
            preg_match('/danfoss|varmega|simplex|sanext|itap|av engineering|cewal/iu', $name, $m) === 1 => Str::title($m[0]),
            default => 'LEMAX',
        };
    }

    /** «25,96 руб.», «255 BYR», 1763 → a number; «по запросу», «-» → 0. */
    public static function price(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (preg_match('/^\s*([\d\s]+(?:[.,]\d+)?)\s*(руб|byr|byn|р\.)?/iu', (string) $value, $m) && trim($m[1]) !== '') {
            return (float) str_replace([' ', ','], ['', '.'], $m[1]);
        }

        return 0.0;
    }

    private static function text(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', (string) $value)));
    }

    public static function store(string $optUpload, string $rrcUpload): void
    {
        Storage::disk('local')->put(self::OPT_FILE, (string) file_get_contents($optUpload));
        Storage::disk('local')->put(self::RRC_FILE, (string) file_get_contents($rrcUpload));
    }

    /** @return array{0:string,1:string} the stored opt and RRC files, '' when missing */
    public static function storedPaths(): array
    {
        $disk = Storage::disk('local');

        return [$disk->exists(self::OPT_FILE) ? $disk->path(self::OPT_FILE) : '', $disk->exists(self::RRC_FILE) ? $disk->path(self::RRC_FILE) : ''];
    }

    /**
     * The model of a name as a set of words: «PRIME V 24», «Котел газовый Лемакс Prime-V24 - двухконтурный» and
     * «PROPLUS-7,5» / «ProPlus 7.5» meet; words that say what the item is are left out.
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

    private const DESCRIPTIVE = ['lemax', 'лемакс', 'котел', 'котёл', 'газовый', 'газовая', 'напольный', 'настенный', 'двухконтурный', 'одноконтурный',
        'электрический', 'электрокотел', 'водонагреватель', 'проточный', 'колонка', 'без', 'усд', 'с', 'и', 'для', 'в', 'на', 'lmx'];
}
