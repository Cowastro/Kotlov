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

        $value = str_replace(["\u{00A0}", "\u{202F}"], ' ', trim($value));

        // Deal.by sometimes formats fractional BYN amounts as
        // "505 руб. 40 коп." instead of "505.40 BYN". Removing all words
        // first would incorrectly turn that value into 50 540.
        if (preg_match(
            '/(-?\d[\d ]*)\s*(?:byn|руб(?:\.|ля|лей|ль)?|р\.?)\s*(\d{1,2})\s*коп(?:\.|ейки?|еек)?/iu',
            $value,
            $matches,
        ) === 1) {
            $major = (int) str_replace(' ', '', $matches[1]);
            $minor = (int) str_pad($matches[2], 2, '0', STR_PAD_RIGHT);

            return $major < 0 ? $major - ($minor / 100) : $major + ($minor / 100);
        }

        $normalized = str_replace(' ', '', $value);
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
