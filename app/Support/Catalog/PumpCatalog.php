<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PumpCatalog extends SpecificationCatalog
{
    protected const FACTS_VERSION = 3;
    public const ROOT = 'vodosnabzhenie';

    public const MANUFACTURER_CATEGORIES = ['skvajinnye-nasosy', 'poverhnostnyie', 'drenajnyie', 'nasosnyie-stantsii'];

    public const PICKER_CATEGORIES = ['tsirkulyatsionnyie', 'mnogostupenchatye-nasosy', ...self::MANUFACTURER_CATEGORIES];

    public const NAMES = [
        self::ROOT => 'Насосы и водоснабжение',
        'promyshlennye-nasosy' => 'Промышленные насосы',
        'tsirkulyatsionnyie' => 'Циркуляционные насосы',
        'poverhnostnyie' => 'Поверхностные насосы',
        'nasosnyie-stantsii' => 'Насосные станции',
        'skvajinnye-nasosy' => 'Скважинные насосы',
        'drenajnyie' => 'Дренажные насосы',
        'mnogostupenchatye-nasosy' => 'Многоступенчатые насосы',
        'konsolnye-i-monoblochnye-nasosy' => 'Консольные и моноблочные насосы',
        'in-lain-nasosy' => 'Ин-лайн насосы',
        'kanalizatsionnye-nasosy-sukhoy-ustanovki' => 'Канализационные насосы сухой установки',
    ];

    public function dimensions(string $slug): array
    {
        $dimensions = [
            'voltage' => ['Питание', ['220' => '220–240 В', '380' => '380–415 В', 'three220' => '3~ 220–240 В']],
        ];
        if (in_array($slug, [self::ROOT, 'tsirkulyatsionnyie', 'promyshlennye-nasosy', 'in-lain-nasosy'], true)) {
            $dimensions['length'] = ['Монтажная длина', array_combine(
                array_map('strval', [80, 130, 150, 180, 200, 220, 240, 250, 280, 300, 320, 340, 360, 400]),
                array_map(fn ($value) => $value.' мм', [80, 130, 150, 180, 200, 220, 240, 250, 280, 300, 320, 340, 360, 400]),
            )];
        }
        if (in_array($slug, [self::ROOT, 'skvajinnye-nasosy'], true)) {
            $dimensions['diameter'] = ['Наружный диаметр насоса', ['to-76' => 'до 76 мм', 'to-102' => 'свыше 76 до 102 мм', 'over' => 'свыше 102 мм']];
        }
        if (in_array($slug, self::MANUFACTURER_CATEGORIES, true)) {
            $dimensions['verified'] = ['Данные производителя', ['yes' => 'Подтверждены', 'no' => 'Пока не подтверждены']];
            if ($slug === 'skvajinnye-nasosy') {
                $dimensions['bore'] = ['Минимальный диаметр скважины', ['75' => 'Для скважины 75 мм', '85' => 'Для скважины 85 мм', '98' => 'Для скважины 98 мм', '110' => 'Для скважины 110 мм']];
            }
            if (in_array($slug, ['skvajinnye-nasosy', 'drenajnyie'], true)) {
                $dimensions['immersion'] = ['Допустимое погружение под воду', ['5' => 'Не менее 5 м', '7' => 'Не менее 7 м', '20' => 'Не менее 20 м', '40' => 'Не менее 40 м', '80' => 'Не менее 80 м', '100' => 'Не менее 100 м']];
            }
            if (in_array($slug, ['poverhnostnyie', 'nasosnyie-stantsii'], true)) {
                $dimensions['suction'] = ['Максимальная высота всасывания', ['5' => 'Не менее 5 м', '8' => 'Не менее 8 м', '15' => 'Не менее 15 м']];
            }
            if ($slug === 'nasosnyie-stantsii') {
                $dimensions['tank'] = ['Объём гидроаккумулятора', ['2' => '2 л', '5' => '5 л', '6' => '6 л', '18' => '18 л', '20' => '20 л', '24' => '24 л', '50' => '50 л']];
            }
            if ($slug === 'drenajnyie') {
                $dimensions['solids'] = ['Допустимый размер твёрдых частиц', ['5' => 'Не менее 5 мм', '10' => 'Не менее 10 мм', '20' => 'Не менее 20 мм', '35' => 'Не менее 35 мм', '50' => 'Не менее 50 мм']];
            }
        }
        return $dimensions;
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        if (in_array($slug, self::MANUFACTURER_CATEGORIES, true)) {
            return HouseholdPumpFacts::facts($product, $slug, $dimensions);
        }
        if (! isset(self::NAMES[$product->category?->slug ?? $slug])) return ['options' => [], 'card' => [], 'numbers' => []];
        $rows = [];
        foreach (($product->specs ?? []) as $key => $spec) {
            if (is_array($spec)) $rows[] = [$spec['key'] ?? '', $spec['value'] ?? '', $spec['unit'] ?? ''];
            elseif (is_scalar($spec)) $rows[] = [$key, $spec, ''];
        }
        if ($product->relationLoaded('allAttributeValues')) {
            foreach ($product->allAttributeValues as $value) {
                $rows[] = [$value->attribute?->name ?? '', $value->option?->name ?? $value->value ?? '', $value->attribute?->suffix ?? ''];
            }
        }
        $values = ['head' => [], 'flow' => [], 'length' => [], 'diameter' => []];
        $networks = [];
        $networkLabels = [];
        foreach ($rows as [$name, $value, $suffix]) {
            if (! is_scalar($value)) continue;
            $name = $this->normalise((string) $name);
            $text = $this->normalise((string) $value);
            $unit = $this->normalise((string) $suffix);
            if (preg_match('/(?:напряжение|параметры электросети)/u', $name)) {
                $three = preg_match('/(?:^|\s)3\s*(?:~|x|х|ф)/u', $text);
                if (preg_match('/\b(?:380|400|415)\b/u', $text)) $networks[] = '380';
                elseif (preg_match('/\b(?:220|230|240)\b/u', $text)) $networks[] = $three ? 'three220' : '220';
                if ($text !== '') $networkLabels[] = trim((string) $value);
            }
            $field = match (true) {
                preg_match('/(?:максимальн\S*|макс\.?).*напор|напор.*(?:макс|hmax)/u', $name) === 1 => 'head',
                ! str_contains($name, 'номиналь') && preg_match('/(?:производительность|расход|подача)/u', $name) === 1
                    && preg_match('/макс|max|qmax/u', $name) === 1 => 'flow',
                str_contains($name, 'монтажная длина') => 'length',
                preg_match('/(?:диаметр насоса|ø насоса)/u', $name) === 1 => 'diameter',
                default => null,
            };
            if ($field === null) continue;
            // Only an explicitly stated unit is accepted. Nominal Q/H, pipe
            // diameters, casing dimensions and model-name digits are not maxima.
            $number = $this->quantity($text, $name.' '.$unit, $field);
            if ($number !== null) $values[$field][] = $number;
        }
        $numbers = [];
        foreach ($values as $key => $candidates) {
            if ($candidates !== [] && max($candidates) - min($candidates) <= max(0.01, max($candidates) * 0.015)) {
                $numbers[$key] = round(min($candidates), 3);
            }
        }
        // The manufacturer's UPC 25/32-40/60/80 table tops out at 8 m:
        // https://unipump.ru/upload/iblock/da2/x7rbbfd0ljt5oi9s9spzrcj7lladq5ab/circularnye_leaflet_2025_V2411-_1_.pdf
        // Legacy imports contain 36/55/76 m. Reject those readings; do not
        // infer a replacement or a production revision from the model name.
        $headNeedsReview = isset($numbers['head']) && $numbers['head'] > 8
            && preg_match('/unipump.*\bupc\s+(?:25|32)\s*[-–]\s*(?:40|60|80)\b/iu', (string) $product->name);
        if ($headNeedsReview) unset($numbers['head']);
        $options = [];
        $card = [];
        if (isset($numbers['head'])) $card['Макс. напор'] = $this->format($numbers['head']).' м';
        if (isset($numbers['flow'])) $card['Макс. расход'] = $this->format($numbers['flow']).' м³/ч';
        $networks = array_unique($networks);
        if (count($networks) === 1) {
            $options['voltage'] = array_values($networks);
            $card['Питание'] = in_array('380', $networks, true) && collect($networkLabels)->contains(fn ($text) => preg_match('/3\s*[~xхф]/u', $text))
                ? '3~ 380–415 В' : $dimensions['voltage'][1][$networks[0]];
        }
        if (isset($numbers['length']) && isset($dimensions['length'][1][(string) $numbers['length']])) {
            $options['length'] = [(string) $numbers['length']];
            $card['Монтажная длина'] = $this->format($numbers['length']).' мм';
        }
        if (isset($numbers['diameter']) && isset($dimensions['diameter'])) {
            $options['diameter'] = [$numbers['diameter'] <= 76 ? 'to-76' : ($numbers['diameter'] <= 102 ? 'to-102' : 'over')];
            $card['Ø насоса'] = $this->format($numbers['diameter']).' мм';
        }
        if ($headNeedsReview) $card['Напор'] = 'Требует проверки';
        return ['options' => $options, 'card' => $card, 'numbers' => $numbers];
    }

    public static function bound(mixed $raw, float $max): ?float
    {
        if (! is_string($raw) || ! preg_match('/^\d{1,5}(?:[.,]\d{1,3})?$/D', trim($raw))) return null;
        $number = (float) str_replace(',', '.', trim($raw));
        return $number > 0 && $number <= $max ? $number : null;
    }

    public function applyBounds(Builder $query, string $slug, Collection $ids, ?float $head, ?float $flow, ?float $headTo = null, ?float $flowTo = null): void
    {
        if ($head === null && $flow === null && $headTo === null && $flowTo === null) return;
        $query->whereIntegerInRaw('products.id', $this->selectedIds($slug, $ids, $head, $flow, [], $headTo, $flowTo));
    }

    public function selectedIds(string $slug, Collection $ids, ?float $head, ?float $flow, array $selection = [], ?float $headTo = null, ?float $flowTo = null): array
    {
        $dimensions = $this->dimensions($slug);
        return collect($this->facts($slug, $ids))->filter(function ($fact) use ($head, $flow, $headTo, $flowTo, $selection, $dimensions) {
            if ($head !== null && (! isset($fact['numbers']['head']) || $fact['numbers']['head'] < $head)) return false;
            if ($flow !== null && (! isset($fact['numbers']['flow']) || $fact['numbers']['flow'] < $flow)) return false;
            if ($headTo !== null && (! isset($fact['numbers']['head']) || $fact['numbers']['head'] > $headTo)) return false;
            if ($flowTo !== null && (! isset($fact['numbers']['flow']) || $fact['numbers']['flow'] > $flowTo)) return false;
            foreach ($selection as $key => $value) {
                if (is_scalar($value) && isset($dimensions[$key][1][$value]) && ! in_array((string) $value, $fact['options'][$key] ?? [], true)) return false;
            }
            return true;
        })->keys()->all();
    }

    private function quantity(string $value, string $units, string $field): ?float
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*(.*)$/u', $value, $match)) return null;
        $number = (float) str_replace(',', '.', $match[1]);
        if ($number <= 0) return null;
        $tail = trim(preg_replace('/\(.*$/u', '', $match[2]));
        // A range or another bare number is ambiguous, even in a max field.
        if (preg_match('/^(?:[–—\-\/]|\.\.|\d)/u', $tail)) return null;
        $units = $tail !== '' ? $tail : $units;
        if ($field === 'flow') {
            if (preg_match('/(?:м(?:3|³|\.куб)|куб\.?\s*м).*\/(?:ч|час)|m3\/h/u', $units)) return $number;
            if (preg_match('/л\s*\/(?:мин)|l\/min/u', $units)) return $number * 0.06;
            if (preg_match('/л\s*\/(?:ч|час)/u', $units)) return $number / 1000;
            if (preg_match('/л\s*\/с/u', $units)) return $number * 3.6;
        } elseif ($field === 'head') {
            if (preg_match('/(?:^|[\s,(])м(?:$|[\s,)])/u', $units) && ! preg_match('/мм|см|м3|м³|\/|бар/u', $units)) return $number;
        } else {
            if (str_contains($units, 'мм')) return $number;
            if ($field === 'diameter' && preg_match('/дюйм|["\']|inch/u', $units)) return $number * 25.4;
        }
        return null;
    }

    private function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'))));
    }

    private function format(float $value): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.'));
    }
}
