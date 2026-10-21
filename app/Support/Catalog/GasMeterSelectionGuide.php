<?php

namespace App\Support\Catalog;

use App\Models\Product;

final class GasMeterSelectionGuide
{
    public const PAGE_URL = '/baza-znaniy/kak-podobrat-schetchik-gaza';

    /** @var array<string, array{nominal: float, max: float, purpose: string}> */
    public const SIZES = [
        'G1,6' => ['nominal' => 1.6, 'max' => 2.5, 'purpose' => 'небольшая нагрузка — чаще всего одна газовая плита'],
        'G2,5' => ['nominal' => 2.5, 'max' => 4.0, 'purpose' => 'плита, колонка или несколько приборов с суммарным расходом до 4 м³/ч'],
        'G4' => ['nominal' => 4.0, 'max' => 6.0, 'purpose' => 'частный дом: котёл, плита или колонка с суммарным расходом до 6 м³/ч'],
        'G6' => ['nominal' => 6.0, 'max' => 10.0, 'purpose' => 'дом с котлом и несколькими газовыми приборами, суммарно до 10 м³/ч'],
    ];

    public static function forProduct(Product $product): ?array
    {
        $name = trim((string) $product->name);
        $categorySlug = (string) ($product->category?->slug ?? '');

        if ($categorySlug !== 'schetchiki-gaza' && ! preg_match('/сч[её]тчик\s+газа/ui', $name)) {
            return null;
        }

        $size = self::extractSize($name);
        if ($size === null) {
            return null;
        }

        $technology = str_contains(mb_strtolower($name), 'ультразвуков') ? 'ultrasonic' : 'diaphragm';
        preg_match('/\b(левый|правый)\b/ui', $name, $directionMatch);
        preg_match('/L\s*=\s*(110|200|250)\b/ui', $name, $lengthMatch);
        $thermal = str_contains(mb_strtolower($name), 'термокомпенс') || preg_match('/(?:^|[-\s])3т(?:[-\s]|$)/ui', $name) === 1;

        $direction = isset($directionMatch[1]) ? mb_strtolower($directionMatch[1]) : null;
        $length = isset($lengthMatch[1]) ? (int) $lengthMatch[1] : null;
        $sizeData = self::SIZES[$size];

        $facts = [
            $technology === 'ultrasonic' ? 'Ультразвуковой: электронное измерение без механической диафрагмы' : 'Диафрагменный: привычная механическая конструкция',
            'Пропускает не более '.self::number($sizeData['max']).' м³/ч',
        ];
        if ($direction) {
            $facts[] = mb_convert_case($direction, MB_CASE_TITLE, 'UTF-8').' вход газа, если смотреть на лицевую сторону';
        }
        if ($length) {
            $facts[] = 'Расстояние между осями патрубков — '.$length.' мм';
        }
        if ($thermal) {
            $facts[] = 'Есть термокомпенсация — полезна при заметных перепадах температуры';
        }

        return [
            'size' => $size,
            'nominal_flow' => $sizeData['nominal'],
            'max_flow' => $sizeData['max'],
            'purpose' => $sizeData['purpose'],
            'technology' => $technology,
            'technology_label' => $technology === 'ultrasonic' ? 'Ультразвуковой' : 'Диафрагменный',
            'direction' => $direction,
            'length' => $length,
            'thermal' => $thermal,
            'facts' => $facts,
            'article_url' => self::PAGE_URL,
        ];
    }

    public static function sizeForFlow(float $flow): ?string
    {
        foreach (self::SIZES as $size => $data) {
            if ($flow <= $data['max']) {
                return $size;
            }
        }

        return null;
    }

    public static function selectorModels(): array
    {
        return [
            self::model('8072-20', 'СГД-1-2-1-G1,6', 'G1,6', 'right', 110, false, 'diaphragm'),
            self::model('8072-21', 'СГД-1-2-2-G1,6', 'G1,6', 'left', 110, false, 'diaphragm'),
            self::model('8072-22', 'СГД-1-2-1-G2,5', 'G2,5', 'right', 110, false, 'diaphragm'),
            self::model('8072-23', 'СГД-1-2-2-G2,5', 'G2,5', 'left', 110, false, 'diaphragm'),
            self::model('8181-20', 'СГД-3Т-1-1-G4', 'G4', 'left', 200, true, 'diaphragm'),
            self::model('8181-00', 'СГД-3Т-1-1-G6', 'G6', 'left', 200, true, 'diaphragm'),
            self::model('8181-01', 'СГД-3Т-1-2-G6', 'G6', 'right', 200, true, 'diaphragm'),
            self::model('8181-10', 'СГД-3Т-2-1-G6', 'G6', 'left', 250, true, 'diaphragm'),
            self::model('8345-00', 'ВЕГА G1,6', 'G1,6', null, null, false, 'ultrasonic'),
            self::model('8345-04', 'ВЕГА G2,5', 'G2,5', null, null, false, 'ultrasonic'),
            self::model('8348-09', 'КАТА-G4 В-3', 'G4', null, null, false, 'ultrasonic'),
            self::model('8349-01', 'СКАТ-G6 В', 'G6', null, null, false, 'ultrasonic'),
        ];
    }

    /**
     * Eight choices shown to a buyer. Several of them contain left/right or
     * different mounting-length executions from the twelve-item specification.
     */
    public static function selectorFamilies(): array
    {
        $definitions = [
            ['key' => 'sgd-g1-6', 'label' => 'СГД G1,6', 'size' => 'G1,6', 'technology' => 'diaphragm'],
            ['key' => 'sgd-g2-5', 'label' => 'СГД G2,5', 'size' => 'G2,5', 'technology' => 'diaphragm'],
            ['key' => 'sgd-3t-g4', 'label' => 'СГД-3Т G4', 'size' => 'G4', 'technology' => 'diaphragm'],
            ['key' => 'sgd-3t-g6', 'label' => 'СГД-3Т G6', 'size' => 'G6', 'technology' => 'diaphragm'],
            ['key' => 'vega-g1-6', 'label' => 'ВЕГА G1,6', 'size' => 'G1,6', 'technology' => 'ultrasonic'],
            ['key' => 'vega-g2-5', 'label' => 'ВЕГА G2,5', 'size' => 'G2,5', 'technology' => 'ultrasonic'],
            ['key' => 'kata-g4', 'label' => 'КАТА G4', 'size' => 'G4', 'technology' => 'ultrasonic'],
            ['key' => 'skat-g6', 'label' => 'СКАТ G6', 'size' => 'G6', 'technology' => 'ultrasonic'],
        ];

        $models = self::selectorModels();

        return array_map(function (array $family) use ($models): array {
            $familyModels = array_filter($models, fn (array $model): bool => $model['family_key'] === $family['key']);

            return $family + [
                'max_flow' => self::SIZES[$family['size']]['max'],
                'purpose' => self::SIZES[$family['size']]['purpose'],
                'technology_label' => $family['technology'] === 'ultrasonic' ? 'Ультразвуковой' : 'Диафрагменный',
                'executions' => count($familyModels),
            ];
        }, $definitions);
    }

    public static function productMatchesModel(Product $product, array $model): bool
    {
        $sku = preg_replace('/\D+/u', '', (string) $product->sku) ?? '';
        [$articlePrefix, $articleSuffix] = array_pad(explode('-', (string) $model['article'], 2), 2, '');

        if ($sku !== '' && $articlePrefix !== '' && str_starts_with($sku, $articlePrefix) && str_ends_with($sku, $articleSuffix)) {
            return true;
        }

        $haystack = self::searchable((string) $product->name);
        $needle = self::searchable((string) $model['name']);

        return $needle !== '' && str_contains($haystack, $needle);
    }

    public static function selectorProduct(Product $product, bool $stockProgram): ?array
    {
        $guide = self::forProduct($product);
        if ($guide === null) {
            return null;
        }

        $family = self::familyFor($product->name, $guide['size'], $guide['technology']);
        $price = (float) $product->price;
        $availabilityStatus = $stockProgram ? $product->publicAvailabilityStatus() : Product::AVAILABILITY_CHECK;
        $buyable = $stockProgram && $product->canBePurchased();
        $slug = trim((string) $product->slug);
        $categorySlug = trim((string) ($product->category?->slug ?? 'schetchiki-gaza'));
        $isTechnicalSeries = in_array($family['key'], ['kata-g4', 'skat-g6'], true);

        return [
            'article' => trim((string) $product->sku) ?: 'уточняется',
            'name' => (string) $product->name,
            'size' => $guide['size'],
            'max_flow' => $guide['max_flow'],
            'purpose' => $guide['purpose'],
            'direction' => $guide['direction'],
            'direction_label' => match ($guide['direction']) {
                'left' => 'левый',
                'right' => 'правый',
                default => 'направление не требуется уточнять',
            },
            'length' => $guide['length'],
            'thermal' => $guide['thermal'],
            'technology' => $guide['technology'],
            'technology_label' => mb_strtolower($guide['technology_label']),
            'family_key' => $family['key'],
            'family_label' => $family['label'],
            'stock_program' => $stockProgram,
            'availability_status' => $availabilityStatus,
            'availability_label' => $stockProgram ? $product->publicAvailabilityLabel() : 'Под заказ',
            'price' => $price > 0 ? $price : null,
            'price_label' => $price > 0 ? number_format($price, 2, '.', ' ').' BYN' : 'Цена уточняется',
            'buyable' => $buyable,
            'url' => $slug !== '' ? '/'.$categorySlug.'/'.$slug : '/search?q='.rawurlencode((string) $product->name),
            'technical_url' => $isTechnicalSeries ? '/baza-znaniy/belomo-kata-skat-oboznacheniya#variants' : null,
        ];
    }

    public static function recommend(float $flow, array $filters = []): array
    {
        $size = self::sizeForFlow($flow);
        if ($size === null) {
            return [];
        }

        return array_values(array_filter(self::selectorModels(), function (array $model) use ($size, $filters): bool {
            if ($model['size'] !== $size) {
                return false;
            }
            foreach (['direction', 'technology'] as $key) {
                if (! empty($filters[$key]) && $model[$key] !== $filters[$key]) {
                    return false;
                }
            }
            if (! empty($filters['length']) && $model['length'] !== (int) $filters['length']) {
                return false;
            }
            if (($filters['thermal'] ?? false) && ! $model['thermal']) {
                return false;
            }

            return true;
        }));
    }

    private static function extractSize(string $name): ?string
    {
        if (! preg_match('/\bG\s*[-]?\s*(1[,.]6|2[,.]5|4|6|10)\b/ui', $name, $match)) {
            return null;
        }

        $normalized = str_replace('.', ',', $match[1]);

        return isset(self::SIZES['G'.$normalized]) ? 'G'.$normalized : null;
    }

    private static function model(string $article, string $name, string $size, ?string $direction, ?int $length, bool $thermal, string $technology): array
    {
        $family = self::familyFor($name, $size, $technology);
        $isTechnicalSeries = in_array($family['key'], ['kata-g4', 'skat-g6'], true);

        return [
            'article' => $article,
            'name' => $name,
            'size' => $size,
            'max_flow' => self::SIZES[$size]['max'],
            'purpose' => self::SIZES[$size]['purpose'],
            'direction' => $direction,
            'direction_label' => match ($direction) {
                'left' => 'левый',
                'right' => 'правый',
                default => 'уточняется по исполнению',
            },
            'length' => $length,
            'thermal' => $thermal,
            'technology' => $technology,
            'technology_label' => $technology === 'ultrasonic' ? 'ультразвуковой' : 'диафрагменный',
            'family_key' => $family['key'],
            'family_label' => $family['label'],
            'stock_program' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'availability_label' => 'Наличие уточняется',
            'price' => null,
            'price_label' => 'Цена уточняется',
            'buyable' => false,
            'url' => '/search?q='.rawurlencode($name),
            'technical_url' => $isTechnicalSeries ? '/baza-znaniy/belomo-kata-skat-oboznacheniya#variants' : null,
        ];
    }

    /** @return array{key: string, label: string} */
    private static function familyFor(string $name, string $size, string $technology): array
    {
        $normalized = mb_strtolower($name);
        $sizeKey = str_replace([',', '.'], '-', mb_strtolower($size));

        if ($technology === 'diaphragm') {
            $prefix = str_contains($normalized, '3т') ? 'sgd-3t-' : 'sgd-';

            return [
                'key' => $prefix.$sizeKey,
                'label' => (str_contains($normalized, '3т') ? 'СГД-3Т ' : 'СГД ').$size,
            ];
        }

        $series = str_contains($normalized, 'ката') ? 'kata' : (str_contains($normalized, 'скат') ? 'skat' : 'vega');
        $label = match ($series) {
            'kata' => 'КАТА ',
            'skat' => 'СКАТ ',
            default => 'ВЕГА ',
        };

        return ['key' => $series.'-'.$sizeKey, 'label' => $label.$size];
    }

    private static function searchable(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(str_replace(',', '.', $value))) ?? '';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }
}
