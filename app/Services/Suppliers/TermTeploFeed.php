<?php

namespace App\Services\Suppliers;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class TermTeploFeed
{
    public const FOLDER = '1-5lJ7SkUMd2OmsT1z3Pcxz6ApCDTfUuK';

    public function sources(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $files = [];
        foreach ($xpath->query('//*[starts-with(@id,"entry-")]') as $entry) {
            $id = substr($entry->getAttribute('id'), 6);
            $name = trim($xpath->evaluate('string(.//*[contains(@class,"flip-entry-title")])', $entry));
            if (! preg_match('/^[\w-]+$/D', $id)) {
                continue;
            }
            if (preg_match('/^остатки\s+(\d{2}\.\d{2}\.\d{2})\.xlsx$/iu', $name, $match)) {
                $kind = 'stock';
            } elseif (preg_match('/^Прайс ООО ТермТеплоСистем.*?(\d{2}\.\d{2}\.\d{2})$/iu', $name, $match)) {
                $kind = 'price';
            } else {
                continue;
            }
            $date = CarbonImmutable::createFromFormat('!d.m.y', $match[1], 'Europe/Minsk');
            if ($date->format('d.m.y') !== $match[1]) {
                throw new RuntimeException("Проверьте дату файла {$name}");
            }
            if ($date->isFuture()) {
                continue;
            }
            $file = ['id' => $id, 'date' => $date->toDateString()];
            $modified = trim($xpath->evaluate('string(.//*[contains(@class,"flip-entry-last-modified")])', $entry));
            if ($modified !== '') {
                $file['modified'] = $modified;
            }
            $files[$kind][$date->toDateString()][] = $file;
        }
        $result = [];
        foreach (['price', 'stock'] as $kind) {
            $dates = $files[$kind] ?? [];
            krsort($dates);
            $date = array_key_first($dates);
            if ($date === null || count($dates[$date]) !== 1) {
                throw new RuntimeException("Не удалось однозначно определить файл {$kind}");
            }
            $result[$kind] = $dates[$date][0];
        }

        return $result;
    }

    public function download(array $file, string $kind, string $path): void
    {
        $id = $file['id'];
        $url = $kind === 'price' ? "https://docs.google.com/spreadsheets/d/{$id}/export?format=xlsx"
            : "https://drive.usercontent.google.com/download?id={$id}&export=download&confirm=t";
        Http::connectTimeout(10)->timeout(120)->retry(2, 500)->sink($path)->get($url)->throw();
    }

    public function read(string $price, string $stock): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $book = $reader->load($stock);
        $rows = $book->getSheet(0)->toArray(null, false, false);
        $book->disconnectWorksheets();
        if (($rows[0][0] ?? null) !== 'Склад' || ($rows[0][3] ?? null) !== 'Свободный остаток'
            || ($rows[1][1] ?? '') !== 'Артикул') {
            throw new RuntimeException('Изменились заголовки ведомости остатков');
        }
        $quantities = [];
        $stockRows = [];
        $total = null;
        $sum = 0;
        foreach (array_slice($rows, 2) as [$name, $article, $unit, $qty]) {
            [$article, $unit, $qty] = array_map(fn ($v) => $v === '#NULL!' ? null : $v, [$article, $unit, $qty]);
            if (trim((string) $name) === 'Итого') {
                $total = $qty;
            }
            if (! filled($unit)) {
                continue;
            }
            if ($qty !== null && ! is_numeric($qty)) {
                throw new RuntimeException("Нечисловой остаток {$name}");
            }
            $sum += (float) $qty;
            $article = TermTeploModel::article(trim((string) $article), $name);
            $key = self::key($article, $name);
            $stockRows[$key] = ['article' => trim((string) $article), 'name' => trim($name), 'unit' => trim($unit)];
            if ($qty !== null && trim($unit) === 'шт') {
                $quantities[$key] = ($quantities[$key] ?? 0) + (int) $qty;
            }
        }
        if (! is_numeric($total) || abs($sum - $total) > 0.001 || ! $quantities) {
            throw new RuntimeException('Сумма складских строк расходится с итогом или ведомость пуста');
        }
        $book = $reader->load($price);
        foreach (['Котлы и бойлеры Haier', 'ЭВН Haier', 'Котлы ARDERIA', 'Стальные радиаторы ROYAL THERMO'] as $expected) {
            if (! $book->sheetNameExists($expected)) {
                throw new RuntimeException("Прайс неполный, отсутствует лист {$expected}");
            }
        }
        $legacy = [];
        $offers = $conflicts = $review = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = $sheet->getTitle();
            if ($title === 'Стальные радиаторы ROYAL THERMO') {
                $matrix = $this->radiators($sheet->toArray(null, true, false), $title);
                if (! $matrix) {
                    throw new RuntimeException('Изменился формат матрицы ROYAL THERMO');
                }
                $offers += $matrix;

                continue;
            }
            if ($title === 'Распродажа') {
                $previousSeries = false;
                foreach ($sheet->toArray(null, true, false) as $i => $cells) {
                    if (($cells[3] ?? '') === 'Отпускная, BYN' && ($cells[4] ?? '') === 'РРЦ, BYN') {
                        $previousSeries = true;

                        continue;
                    }
                    if (! $previousSeries) {
                        continue;
                    }
                    if (! preg_match('/^(.+?)\s*·\s*арт\.\s*(\w+)\s*\R(.+)$/us', (string) ($cells[1] ?? ''), $match)
                        || ! is_numeric($cells[3]) || ! is_numeric($cells[4])) {
                        $previousSeries = false;

                        continue;
                    }
                    $key = self::key($match[2], $match[1]);
                    $legacy[$key] = ['name' => trim($match[1]), 'article' => $match[2], 'specs' => ['Описание' => trim($match[3])],
                        'cost' => $cells[3], 'retail' => $cells[4], 'row' => $i + 1, 'title' => $title,
                        'quantity' => isset($quantities[$key]) ? max(0, $quantities[$key]) : null];
                }

                continue;
            }
            if ($title === 'Содержание') {
                continue;
            }
            $columns = [];
            $parsed = 0;
            foreach ($sheet->toArray(null, true, false) as $i => $cells) {
                $cells = array_map(fn ($v) => trim((string) $v), $cells);
                if (in_array('ОПТ, BYN', $cells, true)) {
                    $columns = array_flip($cells);

                    continue;
                }
                $get = fn ($key) => isset($columns[$key]) ? $cells[$columns[$key]] : '';
                $name = $get('Модель') ?: $get('Наименование');
                $cost = $get('ОПТ, BYN');
                $retail = $get('РРЦ, BYN');
                if ($name === '' || ! is_numeric($cost) || ! is_numeric($retail) || $cost <= 0 || $retail <= 0) {
                    continue;
                }
                $parsed++;
                $sourceArticle = $get('Артикул');
                $article = TermTeploModel::article($sourceArticle, $name);
                $key = self::key($article, $name);
                if (isset($offers[$key]) || isset($conflicts[$key])) {
                    $conflicts[$key] = true;
                    unset($offers[$key]);

                    continue;
                }
                $specs = [];
                foreach ($columns as $label => $index) {
                    if ($label !== '' && ! in_array($label, ['Фото', 'Артикул', 'Наименование', 'Модель', 'ОПТ, BYN', 'РРЦ, BYN'], true) && $cells[$index] !== '') {
                        $specs[$label] = $cells[$index];
                    }
                }
                $offers[$key] = compact('article', 'name', 'cost', 'retail', 'specs', 'title') + [
                    'source_article' => $sourceArticle,
                    'row' => $i + 1, 'quantity' => isset($quantities[$key]) ? max(0, $quantities[$key]) : null,
                ];
            }
            if (! $parsed) {
                $review[] = $title;
            }
        }
        $book->disconnectWorksheets();
        if (! $offers) {
            throw new RuntimeException('В прайсе не найдено товаров');
        }

        $offers += array_diff_key($legacy, $conflicts);
        foreach (array_diff_key($stockRows, $offers, $conflicts) as $key => $row) {
            $offers[$key] = $row + ['quantity' => isset($quantities[$key]) ? max(0, $quantities[$key]) : null,
                'specs' => [], 'cost' => null, 'retail' => null, 'title' => 'Ведомость остатков', 'row' => null];
        }

        return [$offers, array_keys($conflicts), $review];
    }

    private function radiators(array $rows, string $title): array
    {
        $offers = $columns = [];
        $type = null;
        foreach ($rows as $i => $cells) {
            if (preg_match('/ROYAL THERMO\s*·\s*тип (\d+)/u', (string) $cells[0], $match)) {
                $type = $match[1];
                $columns = [];

                continue;
            }
            if ($type && in_array('ОПТ, BYN', $cells, true)) {
                $height = $powerColumn = null;
                foreach ($cells as $c => $label) {
                    if (preg_match('/Высота (\d+) мм/u', (string) ($rows[$i - 3][$c] ?? ''), $match)) {
                        $height = $match[1];
                    }
                    if (str_contains((string) ($rows[$i - 2][$c] ?? ''), 'Вт')) {
                        $powerColumn = $c;
                    }
                    $connection = $rows[$i - 1][$c] ?? '';
                    if ($height && $powerColumn !== null && $label === 'ОПТ, BYN' && ($cells[$c + 1] ?? '') === 'РРЦ, BYN'
                        && preg_match('/\((V?C)\)/', (string) $connection, $match)) {
                        $columns[$c] = [$height, $powerColumn, $match[1]];
                    }
                }

                continue;
            }
            if (! is_numeric($cells[0])) {
                continue;
            }
            foreach ($columns as $c => [$height, $power, $connection]) {
                if (! is_numeric($cells[$c]) || ! is_numeric($cells[$c + 1]) || $cells[$c] <= 0 || $cells[$c + 1] <= 0) {
                    continue;
                }
                $name = "Royal Thermo {$connection} {$type} {$height}×{$cells[0]} мм";
                $key = self::key(null, $name);
                if (isset($offers[$key])) {
                    throw new RuntimeException("Повтор варианта радиатора {$name}");
                }
                $offers[$key] = [
                    'article' => '', 'name' => $name, 'title' => $title, 'row' => $i + 1, 'quantity' => null,
                    // The matrix publishes whole BYN, although its formulas retain fractional intermediate results.
                    'cost' => round($cells[$c]), 'retail' => round($cells[$c + 1]),
                    'specs' => ['Тип' => $type, 'Высота, мм' => $height, 'Длина, мм' => $cells[0],
                        'Подключение' => $connection, 'Теплоотдача при ΔТ 70, Вт' => $cells[$power]],
                ];
            }
        }

        return $offers;
    }

    private static function key($article, $name): string
    {
        $article = trim((string) $article);

        return $article !== '' && $article !== '—' ? mb_strtoupper($article)
            : 'model-'.substr(hash('sha256', mb_strtolower(trim((string) $name))), 0, 32);
    }
}
