<?php

namespace App\Support\Catalog;

use App\Models\Product;

class RadiatorCatalog extends SpecificationCatalog
{
    public const NAMES = [
        'radiatory' => 'Радиаторы отопления',
        'stalnye-radiatory' => 'Стальные радиаторы',
        'alyuminievye-radiatory' => 'Алюминиевые радиаторы',
        'bimetallicheskie-radiatory' => 'Биметаллические радиаторы',
        'chugunnye-radiatory' => 'Чугунные радиаторы',
        'vodyanye-konvektory' => 'Водяные конвекторы',
    ];

    public function dimensions(string $slug): array
    {
        return [
            'material' => ['Материал', ['steel' => 'Сталь', 'aluminium' => 'Алюминий', 'bimetal' => 'Биметалл', 'cast-iron' => 'Чугун']],
            'connection' => ['Подключение', ['side' => 'Боковое', 'bottom' => 'Нижнее']],
            'panel_type' => ['Тип панельного радиатора', ['10' => 'Тип 10', '11' => 'Тип 11', '11A' => 'Тип 11А', '12' => 'Тип 12', '20' => 'Тип 20', '20A' => 'Тип 20А', '21' => 'Тип 21', '22' => 'Тип 22', '30' => 'Тип 30', '30A' => 'Тип 30А', '33' => 'Тип 33']],
            'height' => ['Высота', ['to-300' => 'до 300 мм', 'to-500' => 'свыше 300 до 500 мм', 'to-600' => 'свыше 500 до 600 мм', 'over' => 'свыше 600 мм']],
            'width' => ['Ширина', ['to-500' => 'до 500 мм', 'to-1000' => 'свыше 500 до 1000 мм', 'to-1500' => 'свыше 1000 до 1500 мм', 'over' => 'свыше 1500 мм']],
            'sections' => ['Количество секций', ['to-6' => 'до 6', 'to-10' => '7–10', 'to-14' => '11–14', 'over' => 'больше 14']],
            'centres' => ['Межосевое расстояние', ['to-200' => 'до 200 мм', 'to-350' => 'свыше 200 до 350 мм', 'to-500' => 'свыше 350 до 500 мм', 'over' => 'свыше 500 мм']],
            'power70' => ['Теплоотдача при ΔT 70 °C', ['to-1000' => 'до 1000 Вт', 'to-2000' => 'свыше 1000 до 2000 Вт', 'over' => 'свыше 2000 Вт']],
            'power50' => ['Теплоотдача при ΔT 50 °C', ['to-1000' => 'до 1000 Вт', 'to-2000' => 'свыше 1000 до 2000 Вт', 'over' => 'свыше 2000 Вт']],
        ];
    }

    public static function records(): array
    {
        static $records;
        return $records ??= json_decode(file_get_contents(resource_path('data/radiator-manufacturer-facts.json')), true, flags: JSON_THROW_ON_ERROR)['products'];
    }

    public static function verified(Product $product): array
    {
        $record = self::records()[$product->slug] ?? [];
        // A slug reused for another model must never inherit the old model's specifications.
        return $record !== [] && trim((string) $product->name) === $record['name'] ? $record : [];
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        $record = self::verified($product);
        $options = $card = [];
        foreach (['material' => 'Материал', 'connection' => 'Подключение'] as $key => $label) {
            $values = array_values(array_filter((array) ($record[$key] ?? []), fn ($value) => isset($dimensions[$key][1][$value])));
            if ($values !== []) {
                $options[$key] = $values;
                $card[$label] = implode(' / ', array_map(fn ($value) => $dimensions[$key][1][$value], $values));
            }
        }
        foreach (['height' => ['height_mm', 'Высота', 'мм'], 'width' => ['width_mm', 'Ширина', 'мм'], 'sections' => ['sections', 'Количество секций', ''], 'centres' => ['centres_mm', 'Межосевое расстояние', 'мм'], 'power70' => ['power_dt70_w', 'Теплоотдача при ΔT 70 °C', 'Вт'], 'power50' => ['power_dt50_w', 'Теплоотдача при ΔT 50 °C', 'Вт']] as $key => [$field, $label, $unit]) {
            if (! isset($record[$field])) continue;
            $value = $record[$field];
            foreach ($dimensions[$key][1] as $option => $text) {
                if ($option === 'over' || $value <= (int) substr($option, 3)) {
                    $options[$key] = [$option];
                    break;
                }
            }
            $card[$label] = trim(str_replace('.', ',', (string) $value).' '.$unit);
        }
        if (! isset($record['power_dt70_w']) && isset($record['section_power_dt70_w'])) {
            $card['Теплоотдача секции при ΔT 70 °C'] = str_replace('.', ',', (string) $record['section_power_dt70_w']).' Вт/секц.';
        } elseif (! isset($record['power_dt70_w']) && ! isset($record['section_power_dt70_w']) && isset($record['section_power_dt50_w'])) {
            $card['Теплоотдача секции при ΔT 50 °C'] = str_replace('.', ',', (string) $record['section_power_dt50_w']).' Вт/секц.';
        }
        if (isset($record['panel_type'], $dimensions['panel_type'][1][$record['panel_type']])) $options['panel_type'] = [(string) $record['panel_type']];
        $ordered = [];
        foreach (['Материал', 'Подключение', 'Теплоотдача при ΔT 70 °C', 'Теплоотдача секции при ΔT 70 °C', 'Теплоотдача секции при ΔT 50 °C', 'Высота', 'Ширина', 'Количество секций', 'Межосевое расстояние'] as $label) {
            if (isset($card[$label])) $ordered[$label] = $card[$label];
        }
        return ['options' => $options, 'card' => array_slice($ordered, 0, 4, true)];
    }

    public static function managed(string $name): bool
    {
        return (bool) preg_match('~мощност|теплоотдач|тепловой поток|площадь|межосев|расстояни.*ос|высот|ширин|глубин|толщин|габарит|размер|давлен|температур.*теплоносител|рабочая температур|об[ъь]ем воды|количество секци|число секци|тип радиатора|^тип$|материал|подключени|резьб|диаметр|вес|масс~ui', $name);
    }

    public function details(Product $product, iterable $attributes, bool $preserveUnverified = false): array
    {
        $record = self::verified($product);
        $rows = $this->productFacts($product, $product->category->slug, $this->dimensions($product->category->slug))['card'];
        if (isset($record['panel_type'])) $rows['Тип панельного радиатора'] = (string) $record['panel_type'];
        foreach (['height_mm' => ['Высота', 'мм'], 'width_mm' => ['Ширина', 'мм'], 'depth_mm' => ['Глубина', 'мм'], 'centres_mm' => ['Межосевое расстояние', 'мм'], 'sections' => ['Количество секций', ''], 'power_dt70_w' => ['Теплоотдача при ΔT 70 °C', 'Вт'], 'power_dt50_w' => ['Теплоотдача при ΔT 50 °C', 'Вт'], 'power_dt60_w' => ['Теплоотдача при ΔT 60 °C', 'Вт'], 'section_power_dt70_w' => ['Теплоотдача секции при ΔT 70 °C', 'Вт/секц.'], 'section_power_dt60_w' => ['Теплоотдача секции при ΔT 60 °C', 'Вт/секц.'], 'section_power_dt50_w' => ['Теплоотдача секции при ΔT 50 °C', 'Вт/секц.'], 'section_width_mm' => ['Ширина секции', 'мм']] as $field => [$label, $unit]) {
            if (isset($record[$field])) $rows[$label] = trim(str_replace('.', ',', (string) $record[$field]).' '.$unit);
        }
        if (isset($record['connection'])) {
            $rows['Подключение'] = implode(' / ', array_map(fn ($value) => $this->dimensions('radiatory')['connection'][1][$value], (array) $record['connection']));
        }
        if (isset($record['connection_note'])) $rows['Исполнение подключения'] = $record['connection_note'];
        // Public tables contain manufacturer evidence only. Migrations can retain raw legacy
        // fields in storage without exposing contradictory or duplicated supplier attributes.
        if (! $preserveUnverified) return $rows;
        foreach ($attributes as $attribute) {
            $name = (string) $attribute->attribute?->name;
            $value = trim((string) $attribute->display_value);
            if ($name !== '' && ! self::managed($name) && $this->meaningful($value)) $rows[$name] = $value;
        }
        foreach ($product->specs ?: [] as $key => $raw) {
            if (is_array($raw)) {
                $key = $raw['key'] ?? $raw['name'] ?? '';
                $raw = ($raw['value'] ?? '').(! empty($raw['unit']) ? ' '.$raw['unit'] : '');
            }
            if (is_string($key) && $key !== '' && is_scalar($raw) && ! self::managed($key) && $this->meaningful((string) $raw)) $rows[$key] ??= (string) $raw;
        }
        return $rows;
    }

    private function meaningful(string $value): bool
    {
        return trim($value) !== '' && ! preg_match('~^(?:[-—–]|кг|г|л|бар|мм|см|м|м[²2]|Вт|кВт|°[СC])$~ui', trim($value));
    }

    public function cleanDescription(Product $product): array
    {
        $rows = $this->details($product, []);
        $short = rtrim($product->name, '.').'.';
        $content = '<p>'.e($short).'</p>'.($rows !== [] ? '<ul>' : '');
        foreach ($rows as $label => $value) {
            $short .= ' '.$label.': '.$value.'.';
            $content .= '<li>'.e($label).': '.e($value).'.</li>';
        }
        $content .= ($rows !== [] ? '</ul>' : '<p>Характеристики этого исполнения уточняйте у менеджера.</p>').'<p>Теплоотдача зависит от температурного напора. Для подбора учитывайте режим системы отопления и теплопотери помещения.</p>';
        foreach (self::verified($product)['sources'] ?? [] as $url) $content .= '<p><a href="'.e($url).'" target="_blank" rel="noopener">Документация производителя</a></p>';
        return ['short_description' => $short, 'content' => $content, 'meta_description' => \Illuminate\Support\Str::limit($short, 155, '…')];
    }

    public function forDisplay(Product $product): Product
    {
        $display = clone $product;
        $display->forceFill($this->cleanDescription($product) + ['specs' => $this->details($product, [])]);
        return $display;
    }
}
