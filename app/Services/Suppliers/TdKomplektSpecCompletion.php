<?php

namespace App\Services\Suppliers;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Additive enrichment only: the caller must first establish an exact supplier identity. */
class TdKomplektSpecCompletion
{
    public function complete(Product $product, array $row, bool $apply = false): array
    {
        return DB::transaction(function () use ($product, $row, $apply) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $specs = $product->specs ?? [];
            $occupied = [];
            foreach ($specs as $spec) {
                if (trim((string) ($spec['value'] ?? '')) !== '') {
                    $occupied[$this->key($spec['key'] ?? $spec['name'] ?? '')][] = trim((string) $spec['value'].' '.($spec['unit'] ?? ''));
                }
            }
            foreach ($product->allAttributeValues()->with(['attribute', 'option'])->get() as $value) {
                if ($value->attribute && ($value->value !== null && trim((string) $value->value) !== '' || $value->option_id !== null || $value->is_checked !== null)) {
                    $occupied[$this->key($value->attribute->name)][] = $value->display_value;
                }
            }
            $result = ['product_id' => $product->id, 'added' => [], 'conflicts' => [], 'skipped' => []];
            // Empty name suppresses the feed parser's title-based material inference.
            $feed = app(TdKomplektFeed::class);
            $candidates = $feed->specs(array_replace($row, ['name' => '', 'description' => $feed->descriptionText($row)]), 4);
            $attributes = Attribute::where('category_id', $product->category_id)->get();
            $writes = [];
            foreach ($candidates as $spec) {
                $spec['key'] = trim($spec['key']);
                $spec['value'] = trim($spec['value']);
                $key = $this->key($spec['key']);
                if (in_array($key, ['article', 'barcode', 'brand', 'country'], true)) {
                    continue;
                }
                if (! $this->technicalKey($spec['key']) || ! $this->safeFact($spec)) {
                    $result['skipped'][] = $spec;

                    continue;
                }
                if (isset($occupied[$key])) {
                    if (! in_array($this->value($spec['value']), array_map($this->value(...), $occupied[$key]), true)) {
                        $result['conflicts'][] = ['source' => $spec, 'existing' => $occupied[$key]];
                    }

                    continue;
                }
                $matching = $attributes->filter(fn ($a) => $this->key($a->name) === $key);
                $attribute = $matching->first();
                // Never alter a shared definition, guess a unit conversion, or populate selects by text.
                if (! $product->category_id || $matching->count() > 1 || ($attribute && ($attribute->type !== 'value' || $this->attributeValue($attribute, $spec) === null))) {
                    $result['skipped'][] = $spec;

                    continue;
                }
                $empty = null;
                foreach ($specs as $index => $existing) {
                    if ($this->key($existing['key'] ?? $existing['name'] ?? '') === $key) {
                        $empty = $index;
                        break;
                    }
                }
                if ($empty !== null) {
                    $specs[$empty] = $spec;
                } else {
                    $specs[] = $spec;
                }
                $writes[] = [$attribute, $spec];
                $occupied[$key] = [$spec['value']];
                $result['added'][] = $spec;
            }
            if ($apply && $writes) {
                $backup = TdKomplektFeed::DIRECTORY.'/spec-backups/'.now()->format('Ymd-His-u').'-'.$product->id.'.json';
                if (! Storage::disk('local')->put($backup, json_encode(['product_id' => $product->id, 'before_specs' => $product->specs, 'before_attributes' => $product->allAttributeValues()->get()->toArray(), 'added' => $result['added']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Не удалось сохранить резервную копию характеристик.');
                }
                foreach ($writes as [$attribute, $spec]) {
                    $attribute ??= Attribute::create(['category_id' => $product->category_id, 'group_id' => 0, 'sort_order' => 100, 'name' => $spec['key'], 'type' => 'value', 'in_product' => true, 'in_filter' => false, 'in_sort' => false, 'in_brief' => false, 'is_comparable' => false]);
                    ProductAttributeValue::updateOrCreate(['product_id' => $product->id, 'attribute_id' => $attribute->id], ['value' => $this->attributeValue($attribute, $spec)]);
                }
                $product->update(['specs' => $specs]);
                $result['backup'] = $backup;
            }

            return $result;
        });
    }

    private function safeFact(array $spec): bool
    {
        // Do not turn malformed fractions, concatenated paragraphs or series-wide examples into facts.
        if (str_contains($spec['value'], ':') || mb_strlen($spec['value']) > 150 || preg_match('/баков от|в зависимости/iu', $spec['key'].' '.$spec['value'])) {
            return false;
        }
        if (preg_match('/резьб|разъема соединения|дюйм/iu', $spec['key']) && preg_match('/^(?:G)?(?:12|14|34|54|114|112|214|212)(?:-B)?$/iu', $spec['value'])) {
            return false;
        }

        return true;
    }

    private function technicalKey(string $key): bool
    {
        // Descriptions also contain examples, assembly prose and tables of OTHER models.
        // Accept explicit technical labels, leaving all other pairs in the review report.
        return ! preg_match('/[\t\r\n]/u', $key)
            && preg_match('/^(?:материал|диаметр|внутренн|наружн|толщина|длина|длинна|ширина|высота|масса|вес|объем|объём|номинальн|максимальн|макс[. ]|минимальн|мин[. ]|рабочая температура|рабочее давление|температура|давление|класс|степень|присоедин|подключение|резьба|концевая присоединительная|уплотнительное кольцо|обжимная гильза|расчетный срок|расчётный срок|нормативный срок|срок службы|срок гарантии|гарантия|гарантийный срок|мощность|производительность|напор|расход|пропускная способность|строительная|габарит|размеры|цвет|покрытие|тип(?: |$)|исполнение|серия|модель|сменная мембрана|среда применения|сфера применения|назначение|может использоваться для питьевой воды|выдерживает замерзание|поверхностная отделка|оплетка|оплётка|комплект поставки|номинальный размер|нормы(?: |$)|сопротивление гидрозатвора|скорость перелива|объем смыва|объём смыва|длительность смыва)/iu', $key) === 1;
    }

    private function attributeValue(Attribute $attribute, array $spec): ?string
    {
        $unit = trim((string) $attribute->suffix);
        if ($unit === '') {
            return $spec['value'];
        }
        $number = '[+-]?\d+(?:[.,]\d+)?';
        if (preg_match('/^('.$number.')\s*'.preg_quote($unit, '/').'$/iu', $spec['value'], $m)) {
            return str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(?:,\s*|\()'.preg_quote($unit, '/').'\)?\.?$/iu', $spec['key']) && preg_match('/^'.$number.'$/u', $spec['value'])) {
            return str_replace(',', '.', $spec['value']);
        }

        return null;
    }

    private function key(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = preg_replace('/(?:,\s*|\s*\()(?:мм|см|кг|квт|вт|мпа|бар|л|м)(?:\)|\.)?$/u', '', $key);
        $key = preg_replace('/[^\pL\pN]+/u', '', $key);

        return match ($key) {
            'артикулпроизводителя', 'кодпроизводителя', 'заводскойартикул' => 'article',
            'штрихкод', 'ean', 'ean13' => 'barcode',
            'бренд', 'торговаямарка' => 'brand',
            'странапроизводства', 'странапроисхождения', 'странаизготовитель' => 'country',
            'материал', 'материалкорпуса', 'материализготовления' => 'material',
            default => $key,
        };
    }

    private function value(string $value): string
    {
        return preg_replace('/\s+/u', '', mb_strtolower(str_replace(',', '.', trim($value))));
    }
}
