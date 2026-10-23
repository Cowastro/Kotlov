<?php

namespace App\Support\Marketplaces;

final class DealByMoney
{
    public static function parse(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            foreach (['value', 'amount', 'price'] as $key) {
                if (array_key_exists($key, $value)) {
                    return self::parse($value[$key]);
                }
            }

            return 0.0;
        }

        if (! is_string($value)) {
            return 0.0;
        }

        $normalized = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim($value));
        $normalized = preg_replace('/[^0-9,\.\-]/u', '', $normalized) ?? '';

        if ($normalized === '' || $normalized === '-') {
            return 0.0;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $normalized = str_replace($thousandsSeparator, '', $normalized);
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        } elseif ($lastComma !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (substr_count($normalized, '.') > 1) {
            $lastSeparator = strrpos($normalized, '.');
            $normalized = str_replace('.', '', substr($normalized, 0, $lastSeparator))
                .substr($normalized, $lastSeparator);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
