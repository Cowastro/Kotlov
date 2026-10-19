<?php

namespace App\Services\Suppliers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SantehskladPriceList
{
    public const SOURCE_DATE = '2026-09-17';

    /** @return array{source_date:string,source_sha256:string,supplier:array<string,string>,manufacturer:array<string,string>,rows:array<int,array<string,mixed>>} */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Прайс Сантехсклада не найден: {$path}");
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            foreach ($payload['rows'] ?? [] as $index => $row) {
                $label = (string) (($row['variant'] ?? null) ?: ($row['name'] ?? ''));
                $payload['rows'][$index]['name'] = $this->displayName(
                    (string) ($row['source_sheet'] ?? ''),
                    (string) ($row['group'] ?? ''),
                    $label,
                    (string) ($row['power'] ?? '')
                );
            }
            $this->validate($payload);

            return $payload;
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $rows = [];

        foreach (['DROVA', 'PELLET', 'ПЕЧИ'] as $sheetName) {
            $sheet = $book->getSheetByName($sheetName);
            if (! $sheet) {
                throw new RuntimeException("В прайсе отсутствует обязательный лист {$sheetName}.");
            }

            $group = '';
            for ($rowNumber = 1; $rowNumber <= $sheet->getHighestRow(); $rowNumber++) {
                $label = $this->cell($sheet, 'A', $rowNumber);
                $power = $this->cell($sheet, 'B', $rowNumber);
                $retail = $this->number($sheet->getCell("C{$rowNumber}")->getCalculatedValue());
                $wholesale = $this->number($sheet->getCell("D{$rowNumber}")->getCalculatedValue());

                if ($this->isGroupRow($label, $power, $retail, $sheetName, $rowNumber)) {
                    $group = $label;
                }
                if ($retail <= 0 || $wholesale <= 0 || $retail <= $wholesale) {
                    continue;
                }

                $identity = $this->rowIdentity($sheetName, $group, $label, $power);
                if ($identity === '') {
                    throw new RuntimeException("Не удалось определить позицию {$sheetName}!{$rowNumber}.");
                }

                $rows[] = [
                    'article' => 'SANTEHSKLAD-'.mb_strtoupper(str_replace('_', '-', $identity)),
                    'article_key' => mb_strtoupper(str_replace('_', '-', $identity)),
                    'identity' => $identity,
                    'name' => $this->displayName($sheetName, $group, $label, $power),
                    'group' => $group,
                    'variant' => $label ?: null,
                    'power' => $power ?: null,
                    'retail' => round($retail, 2),
                    'wholesale' => round($wholesale, 2),
                    'margin_percent' => round((($retail - $wholesale) / $retail) * 100, 2),
                    'discount_percent' => round((1 - ($wholesale / $retail)) * 100, 2),
                    'source_sheet' => $sheetName,
                    'source_row' => $rowNumber,
                ];
            }
        }

        $payload = [
            'source_date' => self::SOURCE_DATE,
            'source_sha256' => hash_file('sha256', $path),
            'supplier' => ['name' => 'Сантехсклад', 'code' => 'santehsklad'],
            'manufacturer' => [
                'name' => 'ООО «Производственно-техническая компания Сакович»',
                'phone' => '+375 33 378-15-35',
                'email' => 'pramen-ovaluev@mail.ru',
            ],
            'rows' => $rows,
        ];
        $this->validate($payload);

        return $payload;
    }

    public function productIdentity(string $name): string
    {
        $value = $this->normalize($name);
        if ((str_contains($value, 'kipi') || str_contains($value, 'rotary'))
            && preg_match('/(\d+)\s*(?:[-–]|\s)\s*(\d+)\s*(?:квт|kw)/u', $value, $range)) {
            return 'kipi_rotary_'.$this->canonicalKipiRange($range[1].'-'.$range[2]);
        }
        if (! str_contains($value, 'sakovich')
            && ! str_contains($value, 'сакович')
            && ! str_contains($value, 'pramen')
            && ! str_contains($value, 'прамен')) {
            return '';
        }

        $family = match (true) {
            str_contains($value, 'pellet standart') && preg_match('/prakti[ckк]/u', $value) && str_contains($value, 'бункер') => 'pellet_standart_praktik',
            str_contains($value, 'pellet universal') => 'pellet_universal',
            str_contains($value, 'pellet eco'), str_contains($value, 'pellet есо') => 'pellet_eco',
            str_contains($value, 'pellet max') => 'pellet_max',
            str_contains($value, 'pellet standart') && preg_match('/\b4\s*мм\b/u', $value) => 'pellet_standart_4mm',
            str_contains($value, 'pellet standart') && preg_match('/\b5\s*мм\b/u', $value) => 'pellet_standart_5mm',
            str_contains($value, 'standart pro') => 'standart_pro',
            str_contains($value, 'pramen green') => 'pramen_green',
            str_contains($value, 'promka') => 'promka',
            str_contains($value, 'madera') => 'madera',
            ! str_contains($value, 'pellet') && str_contains($value, 'universal') => 'universal',
            ! str_contains($value, 'pellet') && str_contains($value, 'max') => 'max',
            ! str_contains($value, 'pellet') && str_contains($value, 'standart') => 'standart',
            default => '',
        };
        if ($family === '') {
            return '';
        }

        $power = $this->powerFromName($value);
        if ($power === '') {
            return '';
        }

        $variant = '';
        if ($family === 'promka') {
            $variant = str_contains($value, 'без стекла') ? '_no_glass' : (str_contains($value, 'со стеклом') ? '_glass' : '_unknown');
        }

        return $family.'_'.$power.$variant;
    }

    public function nameKey(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $this->normalize($value));
    }

    /** Match identity may normalize legacy supplier nomenclature without changing its stable article. */
    public function rowMatchIdentity(array $row): string
    {
        if (str_contains($this->normalize((string) ($row['group'] ?? '')), 'rotary')
            && preg_match('/(\d+)\s*[-–]\s*(\d+)\s*(?:квт|kw)/ui', (string) (($row['variant'] ?? null) ?: ($row['name'] ?? '')), $range)) {
            return 'kipi_rotary_'.$this->canonicalKipiRange($range[1].'-'.$range[2]);
        }

        return (string) ($row['identity'] ?? '');
    }

    public function catalogName(array $row): string
    {
        $identity = $this->rowMatchIdentity($row);
        if (preg_match('/^kipi_rotary_(\d+)_(\d+)$/', $identity, $range)) {
            return "Пеллетная горелка KIPI Rotary {$range[1]}–{$range[2]} кВт с подающим устройством и автоматикой PLUM ecoMAX 361";
        }

        return trim((string) ($row['name'] ?? ''));
    }

    private function rowIdentity(string $sheet, string $group, string $label, string $power): string
    {
        if ($sheet === 'ПЕЧИ') {
            $variant = str_contains($this->normalize($label), 'без стекла') ? 'no_glass' : 'glass';

            return 'promka_'.$this->powerKey($power).'_'.$variant;
        }

        $normalized = $this->normalize($group);
        $family = match (true) {
            str_contains($normalized, 'pramen green') => 'pramen_green',
            str_contains($normalized, 'standart pro') => 'standart_pro',
            str_contains($normalized, 'pellet universal') => 'pellet_universal',
            str_contains($normalized, 'pellet eco'), str_contains($normalized, 'pellet есо') => 'pellet_eco',
            str_contains($normalized, 'pellet max') => 'pellet_max',
            str_contains($normalized, 'pellet standart') && preg_match('/prakti[ckк]/u', $normalized) => 'pellet_standart_praktik',
            str_contains($normalized, 'pellet standart') && str_contains($normalized, '4мм') => 'pellet_standart_4mm',
            str_contains($normalized, 'pellet standart') && str_contains($normalized, '5мм') => 'pellet_standart_5mm',
            str_contains($normalized, 'sakovich universal') => 'universal',
            str_contains($normalized, 'sakovich max') => 'max',
            str_contains($normalized, 'sakovich madera') => 'madera',
            str_contains($normalized, 'sakovich standart') => 'standart',
            default => '',
        };
        if ($family !== '') {
            return $family.'_'.$this->powerKey($power);
        }

        return 'accessory_'.substr(hash('sha256', $this->normalize($group.' '.$label.' '.$power)), 0, 20);
    }

    private function displayName(string $sheet, string $group, string $label, string $power): string
    {
        if ($sheet === 'ПЕЧИ') {
            return trim("Печь Sakovich Promka {$power} кВт ({$label})");
        }

        $identity = $this->rowIdentity($sheet, $group, $label, $power);
        $names = [
            'pramen_green' => 'Твердотопливный котел Sakovich Pramen Green',
            'standart_pro' => 'Твердотопливный котел Sakovich Standart Pro',
            'standart' => 'Твердотопливный котел Sakovich Standart',
            'universal' => 'Твердотопливный котел Sakovich Universal',
            'max' => 'Твердотопливный котел Sakovich Max',
            'madera' => 'Отопительная печь Sakovich Madera',
            'pellet_universal' => 'Пеллетный котел Sakovich Pellet Universal',
            'pellet_eco' => 'Пеллетный котел Sakovich Pellet Eco',
            'pellet_max' => 'Пеллетный котел Sakovich Pellet Max',
            'pellet_standart_4mm' => 'Пеллетный котел Sakovich Pellet Standart 4 мм',
            'pellet_standart_5mm' => 'Пеллетный котел Sakovich Pellet Standart 5 мм',
            'pellet_standart_praktik' => 'Пеллетный котел Sakovich Pellet Standart с горелкой Praktik и бункером',
        ];
        foreach ($names as $prefix => $name) {
            if (str_starts_with($identity, $prefix.'_')) {
                return trim("{$name} {$power} кВт");
            }
        }

        if (str_contains($this->normalize($group), 'rotary')
            && preg_match('/\d+\s*[-–]\s*\d+\s*(?:квт|kw)/ui', $label)) {
            return 'Пеллетная горелка KIPI Rotary '.str_replace('kW', 'кВт', trim($label))
                .' с подающим устройством и автоматикой PLUM ecoMAX 361';
        }

        return trim(str_replace(
            ['STANDAR 350L.', 'STANDART) 450L.', 'пеллета'],
            ['STANDART 350 л', 'STANDART 450 л', 'пеллет'],
            $label.' '.$power
        ));
    }

    private function canonicalKipiRange(string $range): string
    {
        return str_replace('-', '_', match ($range) {
            '5-16' => '4-16',
            '35-200' => '30-200',
            '50-250' => '35-250',
            '70-300' => '50-300',
            default => $range,
        });
    }

    private function isGroupRow(string $label, string $power, float $retail, string $sheet, int $row): bool
    {
        if ($sheet === 'ПЕЧИ' && $row === 1) {
            return true;
        }
        if ($label === '') {
            return false;
        }

        return ($power === '' && $retail <= 0)
            || preg_match('/^(?:PRAMEN|SAKOVICH|Печь SAKOVICH|Комплект |ДОПОЛНИТЕЛЬНЫЕ |ПОВОРОТНАЯ)/ui', $label) === 1;
    }

    private function powerFromName(string $value): string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:квт|kw)\b/u', $value, $match)) {
            return $this->powerKey($match[1]);
        }
        if (preg_match('/(?:green|standart(?: pro)?|universal|max|madera|promka)\s+(\d+(?:[.,]\d+)?)(?:\b|$)/u', $value, $match)) {
            return $this->powerKey($match[1]);
        }

        return '';
    }

    private function powerKey(string $value): string
    {
        $number = (float) str_replace(',', '.', $value);

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '–', '—'], ['е', '-', '-'], $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}.,]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function cell($sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell($column.$row)->getCalculatedValue());
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.-]+/u', '', (string) $value));
    }

    private function validate(array $payload): void
    {
        if (($payload['source_date'] ?? null) !== self::SOURCE_DATE || count($payload['rows'] ?? []) !== 103) {
            throw new RuntimeException('Прайс Сантехсклада не прошёл контроль полноты или даты.');
        }
        $articles = collect($payload['rows'])->pluck('article_key');
        if ($articles->unique()->count() !== $articles->count()) {
            $duplicates = $articles->countBy()->filter(fn (int $count): bool => $count > 1)->keys()->implode(', ');
            throw new RuntimeException('В прайсе Сантехсклада обнаружены повторяющиеся идентификаторы позиций: '.$duplicates);
        }
        foreach ($payload['rows'] as $row) {
            if (($row['wholesale'] ?? 0) <= 0 || ($row['retail'] ?? 0) <= ($row['wholesale'] ?? 0)) {
                throw new RuntimeException('В прайсе Сантехсклада обнаружена некорректная пара опт/РРЦ.');
            }
        }
    }
}
