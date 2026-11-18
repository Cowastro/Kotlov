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
                    $occupied[$this->key($spec['key'] ?? $spec['name'] ?? '')][] = $this->displayFact($spec);
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
            $review = app(TdKomplektReviewedSpecs::class)->forRow($row);
            $sourceFacts = collect($candidates)->keyBy(fn ($s) => $this->key($s['key']));
            foreach ($review['ignored_source_keys'] ?? [] as $ignored) {
                $sourceFacts->forget($ignored);
            }
            foreach ($review['facts'] ?? [] as $fact) {
                $sourceFacts->put($this->key($fact['key']), $fact);
            }
            $candidates = $sourceFacts->values()->all();
            $result['evidence'] = $review['sources'] ?? [];
            $attributes = Attribute::where('category_id', $product->category_id)->get();
            $writes = [];
            foreach ($candidates as $spec) {
                $spec['key'] = preg_replace('/^[\s•*–—-]+/u', '', trim($spec['key']));
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
                    if (! in_array($this->value($this->displayFact($spec)), array_map($this->value(...), $occupied[$key]), true)) {
                        $result['conflicts'][] = ['source' => $spec, 'existing' => $occupied[$key]];
                    }

                    continue;
                }
                $matching = $attributes->filter(fn ($a) => $this->key($a->name) === $key);
                $preferred = match ($key) {
                    'material' => 'Материал корпуса',
                    'length' => 'Длина трубки, м',
                    'inner_diameter' => 'Диаметр трубки внутренний, мм',
                    'fluid_temperature_max' => 'Максимальная температура рабочей среды, °С',
                    'pressure_max' => 'Максимальное рабочее давление, бар',
                    'flow' => 'Производительность, л/мин',
                    default => $spec['key'],
                };
                $exact = $matching->filter(fn ($a) => $a->name === $preferred);
                $attribute = $exact->count() === 1 ? $exact->first() : ($matching->count() === 1 ? $matching->first() : null);
                // Never alter a shared definition, guess a unit conversion, or populate selects by text.
                if (! $product->category_id || ($matching->count() > 1 && ! $attribute) || ($attribute && $this->attributePayload($attribute, $spec) === null)) {
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
                $occupied[$key] = [$this->displayFact($spec)];
                $result['added'][] = $spec;
            }
            if ($apply && $writes) {
                $backup = TdKomplektFeed::DIRECTORY.'/spec-backups/'.now()->format('Ymd-His-u').'-'.$product->id.'.json';
                if (! Storage::disk('local')->put($backup, json_encode(['product_id' => $product->id, 'before_specs' => $product->specs, 'before_attributes' => $product->allAttributeValues()->get()->toArray(), 'added' => $result['added']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Не удалось сохранить резервную копию характеристик.');
                }
                foreach ($writes as [$attribute, $spec]) {
                    $attribute ??= Attribute::create(['category_id' => $product->category_id, 'group_id' => 0, 'sort_order' => 100, 'name' => $spec['key'], 'type' => 'value', 'in_product' => true, 'in_filter' => false, 'in_sort' => false, 'in_brief' => false, 'is_comparable' => false]);
                    ProductAttributeValue::updateOrCreate(['product_id' => $product->id, 'attribute_id' => $attribute->id], $this->attributePayload($attribute, $spec));
                }
                $product->update(['specs' => $specs]);
                $result['backup'] = $backup;
            }

            return $result;
        });
    }

    public function matchesUnchangedAddition(ProductAttributeValue $value, array $fact): bool
    {
        if (! $value->attribute || $value->attribute->name !== $fact['key']) {
            return false;
        }
        $payload = $this->attributePayload($value->attribute, $fact);
        if ($payload === null) {
            return false;
        }
        foreach ($payload as $key => $expected) {
            if ($expected !== $value->$key) {
                return false;
            }
        }

        return true;
    }

    private function safeFact(array $spec): bool
    {
        // Do not turn malformed fractions, concatenated paragraphs or series-wide examples into facts.
        if (str_contains($spec['value'], '?') || str_contains($spec['value'], ':') || mb_strlen($spec['value']) > 150 || preg_match('/баков от|в зависимости/iu', $spec['key'].' '.$spec['value'])) {
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
            && preg_match('/^(?:материал|диаметр|внутренн|наружн|толщина|длина|длинна|ширина|высота|масса|вес|объем|объём|номинальн|максимальн|макс[. ]|минимальн|мин[. ]|рабочая температура|рабочее давление|температура|давление|класс|степень|присоедин|подключение|резьба|концевая присоединительная|уплотнительное кольцо|обжимная гильза|расчетный срок|расчётный срок|нормативный срок|срок службы|срок гарантии|гарантия|гарантийный срок|электропитание|потребляемая мощность|монтажная длина|монтажный комплект|крепление регулируемых ножек|наполнитель материала|мощность|производительность|напор|расход|пропускная способность|строительная|габарит|размеры|цвет|покрытие|тип(?: |$)|исполнение|серия|модель|сменная мембрана|среда применения|сфера применения|назначение|может использоваться для питьевой воды|выдерживает замерзание|поверхностная отделка|оплетка|оплётка|комплект поставки|гидрозатвор|термическая устойчивость|обрамление|область применения|номинальный размер|нормы(?: |$)|трубка дли|в бухтах длиной|сопротивление гидрозатвора|скорость перелива|объем смыва|объём смыва|длительность смыва)/iu', $key) === 1;
    }

    private function attributePayload(Attribute $attribute, array $spec): ?array
    {
        if ($attribute->type === 'check') {
            return match ($this->value($spec['value'])) {
                'да', 'yes' => ['is_checked' => true],
                'нет', 'no' => ['is_checked' => false],
                default => null,
            };
        }
        $value = $this->attributeValue($attribute, $spec);
        if ($value === null) {
            return null;
        }
        if ($attribute->type === 'select') {
            $options = $attribute->options()->get()->filter(fn ($o) => $this->value($o->name) === $this->value($value));

            return $options->count() === 1 ? ['option_id' => $options->first()->id] : null;
        }

        return $attribute->type === 'value' ? ['value' => $value] : null;
    }

    private function attributeValue(Attribute $attribute, array $spec): ?string
    {
        $unit = trim((string) $attribute->suffix);
        if (in_array($unit, ['C', 'С', '°C', '°С'], true)) {
            $unit = '°C';
            $spec['value'] = str_replace(['°С', 'градусов C', 'градусов С'], '°C', $spec['value']);
        }
        if ($unit === 'м') {
            $spec['value'] = preg_replace('/\s+метр(?:а|ов)?$/u', ' м', $spec['value']);
        }
        if ($unit === 'мм' && preg_match('/^\d+(?:[.,]\d+)?[хx×]\d+(?:[.,]\d+)?$/u', $spec['value']) && preg_match('/,\s*мм\.?$/u', $spec['key'])) {
            return $spec['value'];
        }
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

    public function key(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = preg_replace('~(?:,\s*|\s*\()(?:мм|см|кг|квт|вт|мпа|бар|л/мин|м³/ч|л|м|°с|°c|с|c)(?:\)|\.)?$~u', '', $key);
        $key = preg_replace('/[^\pL\pN]+/u', '', $key);

        return match ($key) {
            'артикулпроизводителя', 'кодпроизводителя', 'заводскойартикул' => 'article',
            'штрихкод', 'ean', 'ean13' => 'barcode',
            'бренд', 'торговаямарка' => 'brand',
            'странапроизводства', 'странапроисхождения', 'странаизготовитель' => 'country',
            'материал', 'материалкорпуса', 'материализготовления' => 'material',
            'длина', 'длинна', 'длинатрубки', 'трубкадлиной', 'трубкадлинной', 'вбухтахдлиной' => 'length',
            'внутреннийдиаметр', 'диаметрвнутренний', 'диаметртрубкивнутренний' => 'inner_diameter',
            'максрабочаятемпература', 'максрабочаятемператураc', 'максимальнаярабочаятемпература', 'максимальнаятемпературарабочейсреды', 'максимальнаятемпературажидкости', 'макстемпературажидкости', 'максимальнаятемператураперекачиваемойжидкости' => 'fluid_temperature_max',
            'максрабочеедавление', 'максимальноерабочеедавление' => 'pressure_max',
            'стокводы', 'производительность', 'пропускнаяспособность' => 'flow',
            'гидрозатвор' => 'trap_height',
            'монтажныйнабор', 'монтажныйкомплект' => 'assembly_kit',
            'монтажныйнабордлякреплениярегулируемыхножек', 'креплениерегулируемыхножек' => 'leg_fixing',
            default => $key,
        };
    }

    private function displayFact(array $spec): string
    {
        $value = trim((string) ($spec['value'] ?? ''));
        $unit = trim((string) ($spec['unit'] ?? ''));
        if ($unit === '' && preg_match('~[,\(]\s*(мм|см|кг|кВт|Вт|МПа|бар|л/мин|м³/ч|л|м|°[CС])\)?\.?$~iu', $spec['key'] ?? '', $m)) {
            $unit = $m[1];
        }

        return $value.($unit !== '' && preg_match('/^[+-]?\d+(?:[.,]\d+)?$/u', $value) ? ' '.$unit : '');
    }

    private function value(string $value): string
    {
        $value = mb_strtolower(str_replace(',', '.', trim($value)));
        $value = preg_replace('/\bметр(?:а|ов)?\b/u', 'м', $value);
        $value = str_replace(['°с', '°c'], '°c', $value);
        $value = str_replace('лмин', 'л/мин', $value);

        return preg_replace('/\s+/u', '', $value);
    }
}
