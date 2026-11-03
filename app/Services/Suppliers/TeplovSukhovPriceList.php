<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Our own price list of Теплов и Сухов chimneys (СанБизнесГруп, «ТиС_ОПТ_<date>_SBG.xlsx»). Every sheet («ТиС 0.8»,
 * «Тис Феррит 430-0,5», «MC Black», «УПШ, ТермоВент, ППУ», «Феррит 1 мм (430)»…) has a header row with «Артикул»,
 * «Розница» and «Опт»; the name is the column right before the article (the MC Black sheet keeps an old matrix
 * on its left). A row without a retail price («-») is not sold. The file stays on the server only.
 */
class TeplovSukhovPriceList
{
    public const FILE = 'suppliers/teplov-sukhov/latest.xlsx';

    /**
     * Rows in the shape of the earlier prepared JSON, so the sync reads either.
     *
     * @return array{price_date:?string,source_file:string,price_column:string,rows:list<array{name:string,sku:string,original_sku:string,retail:string,wholesale:?float,sheet:string}>}
     */
    public static function parse(string $path, ?string $sourceName = null): array
    {
        $book = IOFactory::createReaderForFile($path)->setReadDataOnly(true)->load($path);
        $rows = [];
        $date = null;
        foreach ($book->getWorksheetIterator() as $sheet) {
            $columns = null;
            foreach ($sheet->toArray(null, true, false, false) as $line) {
                // Names as they are: the -01/-02 articles of the earlier imports were made from them.
                $text = fn (int $index): string => trim((string) ($line[$index] ?? ''));
                if ($date === null && preg_match('/прайс-лист от (\d{2})\.(\d{2})\.(\d{4})/iu', implode(' ', array_map('strval', $line)), $m)) {
                    $date = "{$m[3]}-{$m[2]}-{$m[1]}";
                }
                if ($columns === null) {
                    $find = fn (string $needle): ?int => collect($line)->keys()->first(fn ($i) => str_contains(mb_strtolower($text($i)), $needle));
                    $article = $find('артикул');
                    $retail = $find('розница');
                    if ($article !== null && $article > 0 && $retail !== null) {
                        $columns = ['name' => $article - 1, 'article' => $article, 'retail' => $retail, 'opt' => $find('опт')];
                    }

                    continue;
                }
                $article = $text($columns['article']);
                $name = $text($columns['name']);
                $retail = $line[$columns['retail']] ?? null;
                if ($article === '' || $name === '' || ! is_numeric($retail) || (float) $retail <= 0) {
                    continue;
                }
                $opt = $columns['opt'] !== null ? ($line[$columns['opt']] ?? null) : null;
                $rows[] = [
                    'name' => $name,
                    'sku' => $article,
                    'original_sku' => $article,
                    'retail' => number_format((float) $retail, 2, '.', ''),
                    'wholesale' => is_numeric($opt) && (float) $opt > 0 ? round((float) $opt, 2) : null,
                    'sheet' => $sheet->getTitle(),
                ];
            }
        }

        // A few article values are reused by different rows: each keeps a stable -01/-02 suffix (fully identical
        // copies stay one row for the sync's own de-duplication).
        $byArticle = [];
        foreach ($rows as $index => $row) {
            $byArticle[$row['original_sku']][] = $index;
        }
        foreach ($byArticle as $article => $indexes) {
            if (count(array_unique(array_map(fn (int $i) => $rows[$i]['name'].'|'.$rows[$i]['retail'], $indexes))) > 1) {
                foreach ($indexes as $n => $index) {
                    $rows[$index]['sku'] = sprintf('%s-%02d', $article, $n + 1);
                }
            }
        }

        return ['price_date' => $date, 'source_file' => $sourceName ?? basename($path), 'price_column' => 'retail', 'rows' => $rows];
    }

    public static function store(string $uploadedPath): void
    {
        Storage::disk('local')->put(self::FILE, (string) file_get_contents($uploadedPath));
    }

    public static function storedPath(): string
    {
        return Storage::disk('local')->exists(self::FILE) ? Storage::disk('local')->path(self::FILE) : '';
    }
}
