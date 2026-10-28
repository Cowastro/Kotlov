<?php

namespace App\Services\Suppliers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class HomeTechnologiesPriceList
{
    public const WEBSITE = 'https://domteh.by/';

    private const SHEETS = [
        'Электрический теплый пол',
        'Терморегуляторы',
        'Труба Stahlmann',
        'Системы Neptun',
        'Арх. обогрев',
    ];

    /** @return array{source_date:string,source_sha256:string,supplier:array<string,mixed>,rows:array<int,array<string,mixed>>} */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Прайс Домашних технологий не найден: {$path}");
        }

        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->validate($payload);

            return $payload;
        }

        $book = IOFactory::load($path);
        $rows = [];
        foreach (self::SHEETS as $sheetName) {
            $sheet = $book->getSheetByName($sheetName);
            if (! $sheet) {
                throw new RuntimeException("В прайсе отсутствует лист «{$sheetName}».");
            }

            $description = '';
            $blankRows = 0;
            for ($rowNumber = 3; $rowNumber <= $sheet->getHighestRow(); $rowNumber++) {
                $name = $this->clean((string) $sheet->getCell("C{$rowNumber}")->getCalculatedValue());
                $currentDescription = $this->clean((string) $sheet->getCell("B{$rowNumber}")->getCalculatedValue());
                if ($currentDescription !== '') {
                    $description = $currentDescription;
                }
                if ($name === '') {
                    if (++$blankRows >= 30) {
                        break;
                    }

                    continue;
                }
                $blankRows = 0;

                $retailProvided = in_array($sheetName, ['Электрический теплый пол', 'Терморегуляторы', 'Системы Neptun'], true);
                $firstPrice = $this->number($sheet->getCell("E{$rowNumber}")->getCalculatedValue());
                $secondPrice = $this->number($sheet->getCell("F{$rowNumber}")->getCalculatedValue());
                $wholesale = $secondPrice;
                $retail = $retailProvided ? $firstPrice : null;
                if ($wholesale <= 0 || ($retailProvided && ($retail ?? 0) <= 0)) {
                    continue;
                }

                $article = $this->article($name);
                $rows[] = [
                    'article' => $article,
                    'article_key' => $this->key($article),
                    'name' => $name,
                    'name_key' => $this->key($name),
                    'description' => $description,
                    'section' => $sheetName,
                    'unit' => $this->unit((string) $sheet->getCell("D{$rowNumber}")->getCalculatedValue()),
                    'wholesale' => round($wholesale, 2),
                    'retail' => $retail !== null ? round($retail, 2) : null,
                    'retail_method' => $retail !== null ? 'supplier_rrp' : 'standard_target_margin_20',
                    'discontinued' => preg_match('/вывод\s+из\s+ассортимента/ui', $name) === 1,
                    'source_row' => $rowNumber,
                ];
            }
        }

        $payload = [
            'source_date' => '2026-09-01',
            'source_sha256' => hash_file('sha256', $path),
            'supplier' => [
                'code' => 'home-technologies',
                'name' => 'ООО «Домашние технологии»',
                'website' => self::WEBSITE,
                'email' => 'info@domteh.by',
                'address' => '220068, Минск, ул. Некрасова, 114, пом. 18',
                'unp' => '193729764',
                'phones' => [
                    ['value' => '+375 (44) 750-90-81', 'label' => 'Евгений'],
                    ['value' => '+375 (29) 181-22-62', 'label' => 'Денис'],
                    ['value' => '+375 (33) 340-24-50', 'label' => 'Константин, директор'],
                ],
            ],
            'rows' => $rows,
        ];
        $this->validate($payload);

        return $payload;
    }

    public function key(?string $value): string
    {
        $value = mb_strtolower($this->clean((string) $value));
        $value = strtr($value, ['ё' => 'е', 'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'х' => 'x', 'у' => 'y']);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    /** @return array<int,string> */
    public function modelKeys(string $name): array
    {
        preg_match_all('/(?=[\p{L}\p{N}-]*\d)[\p{L}\p{N}]+(?:[-\/.][\p{L}\p{N},]+)+|[A-ZА-ЯЁ]{2,}\s*-?\s*\d[\p{L}\p{N}-]*/ui', $name, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $value): string => $this->key($value))
            ->filter(fn (string $value): bool => mb_strlen($value) >= 4)
            ->unique()->values()->all();
    }

    private function article(string $name): string
    {
        $models = $this->modelKeys($name);
        $prefix = $models[0] ?? 'DT';

        return mb_strtoupper($prefix.'-'.substr(hash('sha256', $this->key($name)), 0, 10));
    }

    private function unit(string $value): string
    {
        $value = mb_strtolower($this->clean($value));

        return str_starts_with($value, 'м') ? 'м' : 'шт';
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace(',', '.', preg_replace('/[^\d,.-]+/u', '', (string) $value) ?? '');

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $value)) ?? $value);
    }

    private function validate(array $payload): void
    {
        if (($payload['source_date'] ?? '') === '' || count($payload['rows'] ?? []) !== 229) {
            throw new RuntimeException('Прайс Домашних технологий не прошёл контроль полноты: ожидается 229 строк.');
        }
        $names = collect($payload['rows'])->pluck('name_key');
        if ($names->unique()->count() !== $names->count()) {
            throw new RuntimeException('В прайсе Домашних технологий повторяются товарные наименования.');
        }
        $articles = collect($payload['rows'])->pluck('article_key');
        if ($articles->unique()->count() !== $articles->count()) {
            throw new RuntimeException('В прайсе Домашних технологий повторяются внутренние ключи строк.');
        }
        foreach ($payload['rows'] as $row) {
            if (($row['wholesale'] ?? 0) <= 0 || (($row['retail'] ?? null) !== null && $row['retail'] <= 0)) {
                throw new RuntimeException('Некорректная цена: '.($row['name'] ?? '?'));
            }
        }
    }
}
