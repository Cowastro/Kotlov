<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Catalog\PumpCatalog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CirculationPumpCalculator
{
    public const GRUNDFOS_SOURCE = 'https://www.grundfos.com/uk/learn/ecademy/all-courses/the-magna3-circulator-pump-course1/magna3-operation-and-control-modes';

    public const WILO_SOURCE = 'https://wilo.com/gr/el/Υπηρεσία/Συχνές-ερωτήσεις/Centrifugal-pump/How-do-I-select-the-right-centrifugal-pump/';

    public function defaults(): array
    {
        return ['scenario' => 'new', 'load_method' => 'power', 'fluid' => 'water', 'system' => 'radiator', 'supply' => 70, 'return' => 50,
            'head_method' => 'estimate', 'resistance' => 100, 'factor' => '2.2', 'voltage' => '', 'length' => '', 'connection' => '', 'model' => '', 'reference' => null];
    }

    public function validate(array $raw): array
    {
        $input = array_replace($this->defaults(), $raw);
        foreach (['power', 'area', 'heat_loss', 'supply', 'return', 'flow', 'head', 'pipe_length', 'resistance'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = str_replace(',', '.', trim($input[$key]));
            }
        }
        $data = Validator::make($input, [
            'scenario' => ['required', Rule::in(['new', 'replacement'])],
            'load_method' => ['required', Rule::in(['power', 'area', 'direct'])],
            'fluid' => ['required', Rule::in(['water', 'glycol'])],
            'system' => ['required', Rule::in(['radiator', 'floor'])],
            'power' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'area' => ['nullable', 'numeric', 'gt:0', 'max:50000'],
            'heat_loss' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'supply' => ['nullable', 'numeric', 'min:5', 'max:110'],
            'return' => ['nullable', 'numeric', 'min:1', 'max:109'],
            'flow' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'head' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'head_method' => ['required', Rule::in(['estimate', 'known'])],
            'pipe_length' => ['nullable', 'numeric', 'gt:0', 'max:3000'],
            'resistance' => ['nullable', 'numeric', 'min:50', 'max:150'],
            'factor' => ['required', Rule::in(['1.3', '2.2', '2.6'])],
            'length' => ['nullable', Rule::in(array_map('strval', [80, 130, 150, 180, 200, 220, 240, 250, 280, 300, 320, 340, 360, 400]))],
            'voltage' => ['nullable', Rule::in(['220', '380', 'three220'])],
            'model' => ['nullable', 'string', 'min:2', 'max:100'],
            'connection' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'integer', 'min:1'],
        ], [], ['power' => 'тепловая нагрузка', 'area' => 'площадь', 'heat_loss' => 'удельные теплопотери', 'pipe_length' => 'длина контура', 'flow' => 'расход', 'head' => 'напор'])->validate();
        $required = $data['scenario'] === 'replacement' ? [] : match ($data['load_method']) {
            'direct' => ['flow', 'head'], 'area' => ['area', 'heat_loss', 'supply', 'return'], default => ['power', 'supply', 'return'],
        };
        if ($data['scenario'] === 'new' && $data['load_method'] !== 'direct') {
            if ($data['fluid'] !== 'water') {
                throw ValidationException::withMessages(['fluid' => 'Для антифриза выберите «Готовая рабочая точка» и укажите Q/H из расчёта для вашей смеси.']);
            }
            $required = array_merge($required, $data['head_method'] === 'known' ? ['head'] : ['pipe_length', 'resistance']);
        }
        foreach ($required as $key) {
            if (! isset($data[$key]) || $data[$key] === '') {
                throw ValidationException::withMessages([$key => 'Заполните параметр для расчёта.']);
            }
        }
        if ($data['scenario'] === 'new' && $data['load_method'] !== 'direct' && (float) $data['supply'] - (float) $data['return'] < 1) {
            throw ValidationException::withMessages(['return' => 'Обратка должна быть ниже подачи минимум на 1 °C.']);
        }
        if ($data['scenario'] === 'replacement' && empty($data['model']) && empty($data['reference']) && empty($data['length'])) {
            throw ValidationException::withMessages(['model' => 'Укажите старую модель или монтажную длину.']);
        }

        $unused = $data['scenario'] === 'replacement' ? ['power', 'area', 'heat_loss', 'supply', 'return', 'flow', 'head', 'pipe_length', 'resistance', 'factor']
            : match ($data['load_method']) {
                'direct' => ['power', 'area', 'heat_loss', 'supply', 'return', 'pipe_length', 'resistance', 'factor'],
                'area' => ['power', 'flow'], default => ['area', 'heat_loss', 'flow'],
            };
        if ($data['scenario'] === 'new' && $data['head_method'] === 'known') {
            $unused = array_merge($unused, ['pipe_length', 'resistance', 'factor']);
        }
        $data = array_diff_key($data, array_flip($unused));
        foreach (['power', 'area', 'heat_loss', 'supply', 'return', 'flow', 'head', 'pipe_length', 'resistance'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $data[$key] = (float) $data[$key];
            }
        }

        return $data;
    }

    public function calculate(array $data): array
    {
        if ($data['scenario'] === 'replacement') {
            return ['flow' => null, 'head' => null, 'power' => null, 'approximate' => true, 'summary' => 'Замена насоса: '.($data['model'] ?: 'по размерам').'. Требуется сверить рабочую кривую, присоединения и назначение.'];
        }
        $direct = $data['load_method'] === 'direct';
        $power = $direct ? null : ($data['load_method'] === 'area' ? (float) $data['area'] * (float) $data['heat_loss'] / 1000 : (float) $data['power']);
        if ($power !== null && $power > 1000) {
            throw ValidationException::withMessages(['area' => 'Расчёт ограничен тепловой нагрузкой до 1000 кВт.']);
        }
        // Grundfos MAGNA3 training: Q [m³/h] = 0.86 × P [kW] / ΔT [K], water approximation.
        $flow = $direct ? (float) $data['flow'] : 0.86 * $power / ((float) $data['supply'] - (float) $data['return']);
        // Wilo rough sizing: critical circuit only, R [Pa/m] × L [m] × ZF / 10,000.
        $head = $direct || $data['head_method'] === 'known' ? (float) $data['head'] : (float) $data['resistance'] * (float) $data['pipe_length'] * (float) $data['factor'] / 10000;
        $approximate = ! $direct && ($data['load_method'] === 'area' || $data['head_method'] === 'estimate');

        return ['flow' => $flow, 'head' => $head, 'power' => $power, 'approximate' => $approximate,
            'summary' => 'Q ≈ '.number_format($flow, 3, ',', ' ').' м³/ч; H ≈ '.number_format($head, 2, ',', ' ').' м. '.($approximate ? 'Предварительная оценка.' : 'По введённым расчётным данным.').' Рабочая кривая насоса и совместимость не подтверждены.'];
    }

    public function eligible(Product $product): bool
    {
        // Do not substitute boosters, DHW recirculation, accessories or spare parts for heating circulators.
        $purpose = $product->name;
        foreach ($product->specs ?? [] as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? '') : $key;
            $value = is_array($spec) ? ($spec['value'] ?? '') : $spec;
            if (is_scalar($value) && preg_match('/назначени|область применения|тип насоса/iu', (string) $name)) {
                $purpose .= ' '.$value;
            }
        }
        foreach ($product->allAttributeValues ?? [] as $value) {
            if (preg_match('/назначени|область применения|тип насоса/iu', $value->attribute?->name ?? '')) {
                $purpose .= ' '.($value->option?->name ?? $value->value);
            }
        }

        return $product->category?->slug === 'tsirkulyatsionnyie'
            && ! preg_match('/(?:\bгвс\b|горячего\s+водоснабжения|рециркуляц|повышени[яе]\s+давления|повышающ|повыситель|запчаст|комплектующ|запасн|кабел|адаптер|прокладк|реле|гайк|фланец\s+для|голов[аы]\s+насос)/iu', $purpose);
    }

    public function facts(Product $product): array
    {
        $catalog = app(PumpCatalog::class);

        $facts = $catalog->productFacts($product, 'tsirkulyatsionnyie', $catalog->dimensions('tsirkulyatsionnyie'));
        $connections = [];
        foreach ($product->specs ?? [] as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? '') : $key;
            $value = is_array($spec) ? ($spec['value'] ?? '') : $spec;
            if (is_scalar($value) && preg_match('/присоедин|подключени|размер резьбы/iu', (string) $name) && filled($value) && $value !== '—') {
                $connections[] = trim((string) $value.' '.(is_array($spec) ? ($spec['unit'] ?? '') : ''));
            }
        }
        foreach ($product->allAttributeValues ?? [] as $value) {
            if ($value->hasDisplayValue() && preg_match('/присоедин|подключени|размер резьбы/iu', $value->attribute?->name ?? '')) {
                $connections[] = $value->display_value;
            }
        }
        $connections = array_unique(array_map(fn ($value) => $this->connection($value), $connections));
        if (count($connections) === 1) {
            $facts['connection'] = reset($connections);
            $facts['card']['Присоединение'] = $facts['connection'];
        }

        return $facts;
    }

    public function matches(Product $product, array $facts, array $data, array $result): bool
    {
        if (! $this->eligible($product)) {
            return false;
        }
        foreach (['flow', 'head'] as $key) {
            // Necessary envelope check only. Independent maxima never prove a duty point.
            if ($result[$key] !== null && (! isset($facts['numbers'][$key]) || $facts['numbers'][$key] <= $result[$key])) {
                return false;
            }
        }
        if (! empty($data['length']) && (float) ($facts['numbers']['length'] ?? 0) !== (float) $data['length']) {
            return false;
        }
        if (! empty($data['voltage']) && ! in_array($data['voltage'], $facts['options']['voltage'] ?? [], true)) {
            return false;
        }
        if (! empty($data['connection']) && ($facts['connection'] ?? '') !== $this->connection($data['connection'])) {
            return false;
        }

        return true;
    }

    private function connection(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', str_replace(['"', '″', '“', '”'], ['″', '″', '″', '″'], trim($value))));
    }
}
