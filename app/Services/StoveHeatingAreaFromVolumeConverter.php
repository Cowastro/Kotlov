<?php

namespace App\Services;

class StoveHeatingAreaFromVolumeConverter
{
    public const STANDARD_CEILING_HEIGHT = 2.5;

    /**
     * Convert the manufacturer's maximum heated volume to an indicative area
     * using the catalogue-wide standard ceiling height requested by KOTLOV.
     */
    public function convert(string $volume): ?float
    {
        $normalized = str_replace(',', '.', $volume);

        if (! preg_match_all('/\d+(?:\.\d+)?/u', $normalized, $matches) || $matches[0] === []) {
            return null;
        }

        $maximum = max(array_map('floatval', $matches[0]));
        if ($maximum <= 0 || $maximum > 5000) {
            return null;
        }

        return round($maximum / self::STANDARD_CEILING_HEIGHT, 1);
    }

    public function label(float $area): string
    {
        $formatted = rtrim(rtrim(number_format($area, 1, ',', ''), '0'), ',');

        return "до {$formatted} м² (при высоте потолка 2,5 м)";
    }
}
