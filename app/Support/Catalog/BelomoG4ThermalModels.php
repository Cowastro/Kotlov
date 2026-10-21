<?php

namespace App\Support\Catalog;

final class BelomoG4ThermalModels
{
    /**
     * G4 executions with temperature compensation. The short article is used
     * for matching old catalogue SKUs; article_label is shown to the buyer.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::model('8181-20', '8181.00.00.000-20', 'СГД-3Т-1-1-G4', 'left', 200, true),
            self::model('8181-21', '8181.00.00.000-21', 'СГД-3Т-1-2-G4', 'right', 200, false),
            self::model('8336-06', '8336.00.00.000-06', 'СГД4-3-1-G4ТИ', 'left', 110, false),
            self::model('8336-07', '8336.00.00.000-07', 'СГД4-3-2-G4ТИ', 'right', 110, false),
        ];
    }

    private static function model(
        string $article,
        string $articleLabel,
        string $name,
        string $direction,
        int $length,
        bool $stockProgram,
    ): array {
        return [
            'article' => $article,
            'article_label' => $articleLabel,
            'name' => $name,
            'size' => 'G4',
            'max_flow' => 6.0,
            'purpose' => GasMeterSelectionGuide::SIZES['G4']['purpose'],
            'direction' => $direction,
            'direction_label' => $direction === 'left' ? 'левый' : 'правый',
            'length' => $length,
            'thermal' => true,
            'technology' => 'diaphragm',
            'technology_label' => 'диафрагменный',
            'family_key' => 'sgd-3t-g4',
            'family_label' => 'G4 с термокомпенсатором',
            'stock_program' => $stockProgram,
            'availability_status' => 'check',
            'availability_label' => $stockProgram ? 'Складская программа' : 'Под заказ',
            'price' => null,
            'price_label' => 'Цена уточняется',
            'buyable' => false,
            'url' => '/search?q='.rawurlencode($name),
            'technical_url' => null,
        ];
    }
}
